from __future__ import annotations

import json
import logging
from contextlib import contextmanager
from datetime import datetime
from typing import Any, Generator

import pymysql

from .config import Settings


class DatabaseClient:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.logger = logging.getLogger(__name__)
        self._column_cache: dict[str, Any] | None = None

    @contextmanager
    def connection(self):
        conn = pymysql.connect(
            host=self.settings.mysql_host,
            port=self.settings.mysql_port,
            user=self.settings.mysql_user,
            password=self.settings.mysql_password,
            database=self.settings.mysql_db,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=False,
        )
        try:
            yield conn
        finally:
            conn.close()

    def _load_column_cache(self) -> dict[str, Any]:
        if self._column_cache is not None:
            return self._column_cache

        with self.connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SHOW COLUMNS FROM code_embeddings")
                columns = [row["Field"] for row in cur.fetchall() or []]

        if not columns:
            raise RuntimeError("Table code_embeddings was not found or has no columns")

        code_column = "generated_code" if "generated_code" in columns else "code" if "code" in columns else None
        if not code_column:
            raise RuntimeError("Table code_embeddings must contain either 'generated_code' or 'code'")

        self._column_cache = {
            "columns": set(columns),
            "code_column": code_column,
            "has_metadata": "metadata" in columns,
            "has_embedding": "embedding" in columns,
            "has_user_id": "user_id" in columns,
        }
        return self._column_cache

    def count_entries(self) -> int:
        with self.connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS total FROM code_embeddings")
                row = cur.fetchone() or {}
                return int(row.get("total") or 0)

    def iterate_entries(self, batch_size: int) -> Generator[list[dict[str, Any]], None, None]:
        cache = self._load_column_cache()
        code_column = cache["code_column"]
        metadata_select = "metadata" if cache["has_metadata"] else "CAST(NULL AS CHAR) AS metadata"
        embedding_select = "embedding" if cache["has_embedding"] else "CAST(NULL AS CHAR) AS embedding"
        user_select = "user_id" if cache["has_user_id"] else "CAST(NULL AS CHAR) AS user_id"

        last_created_at: datetime | None = None
        last_id: str | None = None
        processed = 0

        while True:
            query = (
                "SELECT id, prompt, "
                f"{code_column} AS generated_code, "
                f"{embedding_select}, {metadata_select}, {user_select}, created_at "
                "FROM code_embeddings "
            )
            params: list[Any] = []

            if last_created_at is not None and last_id is not None:
                query += (
                    "WHERE (created_at > %s) OR (created_at = %s AND id > %s) "
                    "ORDER BY created_at ASC, id ASC LIMIT %s"
                )
                params.extend([last_created_at, last_created_at, last_id, batch_size])
            else:
                query += "ORDER BY created_at ASC, id ASC LIMIT %s"
                params.append(batch_size)

            with self.connection() as conn:
                with conn.cursor() as cur:
                    cur.execute(query, params)
                    rows = cur.fetchall() or []

            if not rows:
                break

            for row in rows:
                metadata = row.get("metadata")
                if isinstance(metadata, str) and metadata.strip():
                    try:
                        row["metadata"] = json.loads(metadata)
                    except json.JSONDecodeError:
                        row["metadata"] = {"raw": metadata}
                elif metadata is None:
                    row["metadata"] = {}

            yield rows
            processed += len(rows)
            last_row = rows[-1]
            last_created_at = last_row["created_at"]
            last_id = str(last_row["id"])

            if self.settings.max_rows_per_run and processed >= self.settings.max_rows_per_run:
                break

    def delete_entries(self, ids: list[str]) -> int:
        if not ids:
            return 0

        placeholders = ", ".join(["%s"] * len(ids))
        query = f"DELETE FROM code_embeddings WHERE id IN ({placeholders})"

        with self.connection() as conn:
            with conn.cursor() as cur:
                cur.execute(query, ids)
                deleted = cur.rowcount
            conn.commit()
        self.logger.info("Deleted %s rows from code_embeddings", deleted)
        return int(deleted)

    def ping(self) -> bool:
        try:
            with self.connection() as conn:
                with conn.cursor() as cur:
                    cur.execute("SELECT 1 AS ok")
                    row = cur.fetchone() or {}
                    return bool(row.get("ok"))
        except Exception as exc:
            self.logger.error("Database ping failed: %s", exc)
            return False
