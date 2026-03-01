import argparse
import json
import logging
import os
import re
import sys
import time
import unicodedata
from dataclasses import dataclass
from datetime import UTC, datetime
from typing import Any, Dict, List, Optional, Tuple

import pandas as pd
from dotenv import load_dotenv

try:
	from rapidfuzz import fuzz
except Exception as exc:  # pragma: no cover
	raise RuntimeError(
		"Missing dependency 'rapidfuzz'. Install it with: pip install rapidfuzz"
	) from exc


# -------------------------
# Normalization
# -------------------------


HOSPITAL_PREFIXES = {
	"rs",
	"rsu",
	"rsud",
	"rsi",
	"rsia",
	"rsk",
}


FACILITY_PREFIXES = {
	"apotek",
	"ap",
	"klinik",
}


PUBLIC_MARKERS = {
	"rsud",
	"pemda",
	"pemerintah",
	"dinkes",
	"kabupaten",
	"kota",
	"provinsi",
}


def _normalize_unicode(text: str) -> str:
	text = unicodedata.normalize("NFKC", str(text))
	text = text.replace("\u200b", " ")
	return text


def _clean_punct_to_space(text: str) -> str:
	text = re.sub(r"[\.,;:/\\\-\(\)\[\]\{\}_\+\|\"'`]+", " ", text)
	text = re.sub(r"\s+", " ", text)
	return text.strip()


def normalize_text(text: Any) -> str:
	if text is None or (isinstance(text, float) and pd.isna(text)):
		return ""
	text = _normalize_unicode(str(text)).lower().strip()
	text = _clean_punct_to_space(text)
	# common Indonesian variants
	text = text.replace("jalan ", "jl ")
	text = text.replace("jln ", "jl ")
	text = text.replace("no ", "no ")
	text = re.sub(r"\bnomor\b", "no", text)
	return text


def normalize_city(text: Any) -> str:
	s = normalize_text(text)
	# normalize common administrative words
	s = re.sub(r"\b(kab\.?|kabupaten)\b", "kab", s)
	s = re.sub(r"\b(kota)\b", "kota", s)
	s = re.sub(r"\s+", " ", s).strip()
	return s


def strip_hospital_prefix(name: str) -> str:
	tokens = normalize_text(name).split()
	if tokens and tokens[0] in HOSPITAL_PREFIXES:
		tokens = tokens[1:]
	return " ".join(tokens)


def strip_common_prefixes(name: str) -> str:
	"""Strip common leading facility words (conservative).

	This helps with cases like:
	- "Apotek RSUD X" vs "RSUD X"
	- "Klinik RS X" vs "RS X"
	"""

	tokens = normalize_text(name).split()
	while tokens and tokens[0] in (HOSPITAL_PREFIXES | FACILITY_PREFIXES):
		tokens = tokens[1:]
	return " ".join(tokens)


def system_looks_like_hospital(name: str, address: str) -> bool:
	text = normalize_text(f"{name} {address}")
	if not text:
		return False
	if "rumah sakit" in text:
		return True
	# token-level check
	tokens = set(text.split())
	if tokens & HOSPITAL_PREFIXES:
		return True
	return False


def classify_public_private(name: str) -> str:
	"""Very conservative classifier.

	Returns:
	  - 'public' if strong signals of government/public hospital
	  - 'unknown' otherwise (we avoid calling things private unless very clear)
	"""

	n = normalize_text(name)
	tokens = set(n.split())
	if tokens & PUBLIC_MARKERS:
		return "public"
	return "unknown"


def is_gov_rsud(name: str) -> bool:
	tokens = set(normalize_text(name).split())
	return "rsud" in tokens


# -------------------------
# Candidate generation (deterministic)
# -------------------------


@dataclass(frozen=True)
class Candidate:
	system_number: str
	system_name: str
	system_address: str
	system_city: str
	system_type: str
	system_within_hospital: bool
	score: float
	name_score: float
	addr_score: float
	city_score: float


def _similarity(a: str, b: str) -> float:
	if not a or not b:
		return 0.0
	return float(fuzz.token_set_ratio(a, b))


def build_candidates_for_gov_row(
	gov_name: str,
	gov_address: str,
	gov_city: str,
	system_df: pd.DataFrame,
	*,
	max_city_pool: int = 300,
	top_k: int = 8,
) -> List[Candidate]:
	"""Return sorted candidates (best first) using deterministic scoring.

	IMPORTANT: This function is intentionally conservative.
	"""

	gov_name_n = normalize_text(gov_name)
	gov_addr_n = normalize_text(gov_address)
	gov_city_n = normalize_city(gov_city)

	gov_name_core = strip_common_prefixes(gov_name)

	# 1) City filter: exact first; otherwise fuzzy pool
	sys_city_series = system_df["area_name_norm"]
	exact_city_mask = sys_city_series.eq(gov_city_n) & (gov_city_n != "")
	city_df = system_df[exact_city_mask]

	if city_df.empty:
		# fuzzy city match to reduce false negatives but keep it bounded
		# compute similarity against distinct cities and pick best few
		unique_cities = (
			system_df[["area_name_norm"]]
			.dropna()
			.drop_duplicates()
			.sort_values("area_name_norm")
		)
		if not unique_cities.empty and gov_city_n:
			unique_cities["city_score"] = unique_cities["area_name_norm"].apply(
				lambda x: _similarity(gov_city_n, x)
			)
			best_cities = unique_cities.sort_values("city_score", ascending=False).head(3)
			city_threshold = 85.0
			best_city_values = best_cities[best_cities["city_score"] >= city_threshold][
				"area_name_norm"
			].tolist()
			if best_city_values:
				city_df = system_df[system_df["area_name_norm"].isin(best_city_values)]

	if city_df.empty:
		# As a last resort, still try globally but cap pool size.
		city_df = system_df

	if len(city_df) > max_city_pool:
		# If too big, prefilter by rough name similarity to keep deterministic & fast.
		tmp = city_df.copy()
		tmp["rough_name"] = tmp["name_core_norm"]
		tmp["rough_score"] = tmp["rough_name"].apply(lambda x: _similarity(gov_name_core, x))
		city_df = tmp.sort_values("rough_score", ascending=False).head(max_city_pool)

	# 2) Score each candidate
	candidates: List[Candidate] = []
	for _, row in city_df.iterrows():
		sys_number = str(row.get("number", "")).strip()
		sys_name = str(row.get("name", "")).strip()
		sys_addr = str(row.get("address", "")).strip()
		sys_city = str(row.get("area_name", "")).strip()
		sys_type = str(row.get("system_type", "")).strip()
		sys_within_hospital = bool(row.get("within_hospital", False))

		sys_name_n = row.get("name_norm", "")
		sys_name_core = row.get("name_core_norm", "")
		sys_addr_n = row.get("address_norm", "")
		sys_city_n = row.get("area_name_norm", "")

		name_score = max(
			_similarity(gov_name_n, sys_name_n),
			_similarity(gov_name_core, sys_name_core),
		)
		addr_score = _similarity(gov_addr_n, sys_addr_n)
		city_score = _similarity(gov_city_n, sys_city_n) if gov_city_n and sys_city_n else 0.0

		# Conservative weighted score
		score = 0.62 * name_score + 0.30 * addr_score + 0.08 * city_score

		candidates.append(
			Candidate(
				system_number=sys_number,
				system_name=sys_name,
				system_address=sys_addr,
				system_city=sys_city,
				system_type=sys_type,
				system_within_hospital=sys_within_hospital,
				score=score,
				name_score=name_score,
				addr_score=addr_score,
				city_score=city_score,
			)
		)

	candidates.sort(key=lambda c: c.score, reverse=True)
	return candidates[:top_k]


def candidate_is_type_compatible(gov_name: str, sys_name: str) -> bool:
	"""Hard rule: avoid matching RSUD (gov) to non-public-looking system names."""
	if is_gov_rsud(gov_name):
		# system must look public OR at least include rsud
		if is_gov_rsud(sys_name):
			return True
		return classify_public_private(sys_name) == "public"
	return True


def candidate_is_facility_compatible(gov_name: str, c: Candidate) -> bool:
	"""Reject obvious non-hospital facilities unless clearly inside a hospital."""
	# If system has type info and it's a pharmacy/clinic, require hospital markers.
	st = normalize_text(c.system_type)
	if st:
		if "apotek" in st or st == "ap":
			return c.system_within_hospital
		if "klinik" in st:
			return c.system_within_hospital
	# If no type info, keep original behavior (conservative elsewhere).
	return True


def candidate_is_strong_enough(
	candidates: List[Candidate],
	*,
	min_best_score: float = 86.0,
	min_name_score: float = 88.0,
	min_margin: float = 6.0,
) -> Tuple[bool, Optional[Candidate], str]:
	if not candidates:
		return False, None, "no_candidates"
	best = candidates[0]
	second = candidates[1] if len(candidates) > 1 else None
	margin = best.score - (second.score if second else 0.0)

	if best.score < min_best_score:
		return False, best, "best_score_too_low"
	if best.name_score < min_name_score:
		return False, best, "name_score_too_low"
	if second and margin < min_margin:
		return False, best, "ambiguous_margin"
	return True, best, "strong"


def _best_by_address(candidates: List[Candidate]) -> Optional[Candidate]:
	if not candidates:
		return None
	# Prefer highest address similarity; tie-break by name score then overall.
	return sorted(
		candidates,
		key=lambda c: (c.addr_score, c.name_score, c.score),
		reverse=True,
	)[0]


def candidate_is_strong_enough_address(
	candidates: List[Candidate],
	*,
	min_addr_score: float = 92.0,
	min_margin: float = 6.0,
) -> Tuple[bool, Optional[Candidate], str]:
	"""Fallback rule when name is unreliable but address is strong.

	This is intentionally strict because address-only matching risks false positives.
	"""
	if not candidates:
		return False, None, "no_candidates"
	best = _best_by_address(candidates)
	if best is None:
		return False, None, "no_candidates"
	# Compute margin in address space
	sorted_by_addr = sorted(candidates, key=lambda c: c.addr_score, reverse=True)
	second = sorted_by_addr[1] if len(sorted_by_addr) > 1 else None
	margin = best.addr_score - (second.addr_score if second else 0.0)

	if best.addr_score < min_addr_score:
		return False, best, "addr_score_too_low"
	if second and margin < min_margin:
		return False, best, "ambiguous_addr_margin"
	return True, best, "strong_by_address"


# -------------------------
# OpenAI semantic verification (only for strong candidates)
# -------------------------


PROMPT_VERSION = "v2"  # bump when verifier instructions/payload meaningfully change


def setup_logging(*, debug: bool, log_file: Optional[str]) -> logging.Logger:
	logger = logging.getLogger("hospital_match")
	logger.setLevel(logging.DEBUG if debug else logging.INFO)
	logger.handlers.clear()
	logger.propagate = False

	formatter = logging.Formatter(
		fmt="%(asctime)s | %(levelname)s | %(message)s",
		datefmt="%Y-%m-%d %H:%M:%S",
	)

	stream_handler = logging.StreamHandler(stream=sys.stdout)
	stream_handler.setLevel(logging.DEBUG if debug else logging.INFO)
	stream_handler.setFormatter(formatter)
	logger.addHandler(stream_handler)

	if log_file:
		os.makedirs(os.path.dirname(os.path.abspath(log_file)), exist_ok=True)
		file_handler = logging.FileHandler(log_file, encoding="utf-8")
		file_handler.setLevel(logging.DEBUG)
		file_handler.setFormatter(formatter)
		logger.addHandler(file_handler)

	return logger


def _format_candidate(c: Candidate) -> str:
	return (
		f"number={c.system_number} | name={c.system_name} | city={c.system_city} | "
		f"score={c.score:.1f} (name={c.name_score:.1f}, addr={c.addr_score:.1f}, city={c.city_score:.1f}) | "
		f"type={c.system_type or '-'} | within_hospital={c.system_within_hospital}"
	)


def _load_openai_client():
	import openai

	api_key = os.getenv("OPENAI_API_KEY")
	if api_key:
		return openai.OpenAI(api_key=api_key)
	return openai.OpenAI()


def ai_verify_match(
	*,
	client: Any,
	model: str,
	gov_name: str,
	gov_address: str,
	gov_city: str,
	candidate: Candidate,
	extra_context: Optional[str] = None,
	timeout_s: float = 30.0,
) -> Dict[str, Any]:
	"""Ask the model to verify if gov record matches the given candidate.

	Returns dict with keys: match (bool), confidence (0-100), reason (str)
	"""

	schema = {
		"type": "object",
		"additionalProperties": False,
		"properties": {
			"match": {"type": "boolean"},
			"confidence": {"type": "integer", "minimum": 0, "maximum": 100},
			"reason": {"type": "string"},
		},
		"required": ["match", "confidence", "reason"],
	}

	rules = [
		"You are verifying a hospital entity match between two registries.",
		"The ministry registry may contain BOTH public and private hospitals; do NOT assume it is public by default.",
		"Be conservative: false positives are worse than false negatives.",
		"Do not guess; if unsure, return match=false with low confidence.",
		"Only if the ministry record clearly indicates RSUD (public), it must not be matched to clearly private hospitals.",
		"City/regency and address fields may be missing, outdated, or partially incorrect; treat them as secondary signals.",
		"Do NOT reject solely because of address mismatch if the hospital name is very distinctive and consistent.",
		"Abbreviations/aliases: RSIA is 'Rumah Sakit Ibu dan Anak'. 'RS Ibu dan Anak' often refers to RSIA.",
		"Base your decision on name, address, and city/regency consistency.",
		"Return ONLY the JSON object with keys match, confidence, reason.",
		"Keep reason short (1-2 sentences).",
	]
	if extra_context:
		rules.append(f"Extra context: {extra_context}")

	user_payload = {
		"gov": {
			"name": str(gov_name),
			"name_norm": normalize_text(gov_name),
			"name_core_norm": strip_common_prefixes(gov_name),
			"address": str(gov_address),
			"address_norm": normalize_text(gov_address),
			"city": str(gov_city),
			"city_norm": normalize_city(gov_city),
		},
		"candidate_system": {
			"number": candidate.system_number,
			"name": candidate.system_name,
			"name_norm": normalize_text(candidate.system_name),
			"name_core_norm": strip_common_prefixes(candidate.system_name),
			"address": candidate.system_address,
			"address_norm": normalize_text(candidate.system_address),
			"city": candidate.system_city,
			"city_norm": normalize_city(candidate.system_city),
			"system_type": candidate.system_type,
			"within_hospital": candidate.system_within_hospital,
			"deterministic_scores": {
				"overall": round(candidate.score, 2),
				"name": round(candidate.name_score, 2),
				"address": round(candidate.addr_score, 2),
				"city": round(candidate.city_score, 2),
			},
		},
	}

	# Note: use Responses API behavior via client.chat.completions for compatibility
	# with the installed openai package patterns in this repo.
	try:
		resp = client.chat.completions.create(
			model=model,
			messages=[
				{"role": "system", "content": "\n".join(rules)},
				{"role": "user", "content": json.dumps(user_payload, ensure_ascii=False)},
			],
			response_format={
				"type": "json_schema",
				"json_schema": {"name": "match_decision", "schema": schema},
			},
			temperature=0,
			timeout=timeout_s,
		)
	except Exception as exc:
		# Fail safe: do not block the pipeline.
		return {
			"match": False,
			"confidence": 0,
			"reason": f"AI error: {type(exc).__name__}",
		}

	content = resp.choices[0].message.content
	try:
		parsed = json.loads(content)
	except Exception:
		# Hard fail-safe: treat as no match
		return {"match": False, "confidence": 0, "reason": "AI returned invalid JSON"}

	# defensive normalization
	match = bool(parsed.get("match", False))
	confidence = int(parsed.get("confidence", 0))
	confidence = max(0, min(100, confidence))
	reason = str(parsed.get("reason", "")).strip()[:400]

	return {"match": match, "confidence": confidence, "reason": reason}


# -------------------------
# I/O + Orchestration
# -------------------------


def read_input_excel(path: str) -> Tuple[pd.DataFrame, pd.DataFrame]:
	xls = pd.ExcelFile(path)
	available = set(xls.sheet_names)

	# Accept common variants without requiring users to rename sheets.
	system_sheet_candidates = ["outlet_sistem", "tbl_outlet_sistem"]
	gov_sheet_candidates = ["outlet_pemerintah", "tbl_outlet_pemerintah"]

	system_sheet = next((s for s in system_sheet_candidates if s in available), None)
	gov_sheet = next((s for s in gov_sheet_candidates if s in available), None)

	if not system_sheet or not gov_sheet:
		raise ValueError(
			"Missing required sheets. Expected one of: "
			f"system={system_sheet_candidates}, gov={gov_sheet_candidates}. "
			f"Found: {xls.sheet_names}"
		)

	system_df = pd.read_excel(path, sheet_name=system_sheet)
	gov_df = pd.read_excel(path, sheet_name=gov_sheet)
	return system_df, gov_df


def prepare_system_df(system_df: pd.DataFrame) -> pd.DataFrame:
	needed = ["number", "name", "address", "area_name"]
	for col in needed:
		if col not in system_df.columns:
			raise ValueError(f"outlet_sistem is missing required column: {col}")

	df = system_df.copy()
	df["name_norm"] = df["name"].apply(normalize_text)
	df["name_core_norm"] = df["name"].apply(strip_common_prefixes)
	df["address_norm"] = df["address"].apply(normalize_text)
	df["area_name_norm"] = df["area_name"].apply(normalize_city)

	# Optional: detect a type/category column if present
	type_col = None
	for c in ["type", "tipe", "outlet_type", "kategori", "category"]:
		if c in df.columns:
			type_col = c
			break
	if type_col:
		df["system_type"] = df[type_col].astype(str).fillna("")
	else:
		df["system_type"] = ""

	# Detect if the record looks like it's in/part of a hospital
	df["within_hospital"] = df.apply(
		lambda r: system_looks_like_hospital(str(r.get("name", "")), str(r.get("address", ""))),
		axis=1,
	)
	return df


def prepare_gov_df(gov_df: pd.DataFrame) -> pd.DataFrame:
	needed = ["nama", "alamat", "kabkota_nama"]
	for col in needed:
		if col not in gov_df.columns:
			raise ValueError(f"outlet_pemerintah is missing required column: {col}")
	return gov_df.copy()


def load_cache(path: str) -> Dict[str, Any]:
	if not path:
		return {}
	if not os.path.exists(path):
		return {}
	try:
		with open(path, "r", encoding="utf-8") as f:
			return json.load(f)
	except Exception:
		return {}


def save_cache(path: str, cache: Dict[str, Any]) -> None:
	if not path:
		return
	tmp = f"{path}.tmp"
	with open(tmp, "w", encoding="utf-8") as f:
		json.dump(cache, f, ensure_ascii=False, indent=2)
	os.replace(tmp, path)


def flatten_cache_for_export(cache: Dict[str, Any]) -> pd.DataFrame:
	rows: List[Dict[str, Any]] = []
	for k, v in (cache or {}).items():
		try:
			key_obj = json.loads(k)
		except Exception:
			key_obj = {"raw_key": k}
		decision = v if isinstance(v, dict) else {}
		rows.append(
			{
				"prompt_version": key_obj.get("prompt_version", ""),
				"model": key_obj.get("model", ""),
				"gov_name": key_obj.get("gov_name", ""),
				"gov_address": key_obj.get("gov_address", ""),
				"gov_city": key_obj.get("gov_city", ""),
				"system_number": key_obj.get("sys_number", ""),
				"system_name": key_obj.get("sys_name", ""),
				"match": decision.get("match", None),
				"confidence": decision.get("confidence", None),
				"reason": decision.get("reason", ""),
				"raw_key": k if "raw_key" in key_obj else "",
			}
		)

	if not rows:
		return pd.DataFrame(
			columns=[
				"prompt_version",
				"model",
				"gov_name",
				"gov_address",
				"gov_city",
				"system_number",
				"system_name",
				"match",
				"confidence",
				"reason",
				"raw_key",
			]
		)

	df = pd.DataFrame(rows)
	# Best-effort sort: higher confidence first, then match true.
	if "confidence" in df.columns:
		df["confidence_sort"] = pd.to_numeric(df["confidence"], errors="coerce")
		df = df.sort_values(
			by=["match", "confidence_sort"],
			ascending=[False, False],
			na_position="last",
		)
		df = df.drop(columns=["confidence_sort"], errors="ignore")
	return df


def export_from_cache_only(
	*,
	cache_path: str,
	output_excel: str,
	min_assign_confidence: int,
) -> None:
	cache = load_cache(cache_path)
	cache_df = flatten_cache_for_export(cache)

	# matched = only cache entries where the AI said match=true (no threshold gating)
	matched_df = cache_df.copy()
	if not matched_df.empty and "match" in matched_df.columns:
		matched_df = matched_df[(matched_df["match"] == True)].copy()  # noqa: E712

	with pd.ExcelWriter(output_excel, engine="openpyxl") as writer:
		matched_df.to_excel(writer, index=False, sheet_name="matched")
		cache_df.to_excel(writer, index=False, sheet_name="cache_all")
		meta = pd.DataFrame(
			[
				{
					"created_at": datetime.now(UTC).isoformat(),
					"source": "cache_only",
					"cache_file": os.path.abspath(cache_path),
					"min_assign_confidence": min_assign_confidence,
					"rows_written_matched": int(len(matched_df)),
					"cache_entries": int(len(cache_df)),
				}
			]
		)
		meta.to_excel(writer, index=False, sheet_name="run_meta")


def cache_key(
	gov_name: str, gov_address: str, gov_city: str, candidate: Candidate, model: str
) -> str:
	base = {
		"prompt_version": PROMPT_VERSION,
		"gov_name": normalize_text(gov_name),
		"gov_address": normalize_text(gov_address),
		"gov_city": normalize_city(gov_city),
		"sys_number": candidate.system_number,
		"sys_name": normalize_text(candidate.system_name),
		"model": model,
	}
	return json.dumps(base, sort_keys=True, ensure_ascii=False)


def run_matching(
	*,
	input_excel: str,
	output_excel: str,
	model: str,
	min_assign_confidence: int = 85,
	cache_path: str = "hospital_match_cache_v2.json",
	max_rows: Optional[int] = None,
	dry_run: bool = False,
	debug: bool = False,
	log_file: Optional[str] = None,
	debug_top_k: int = 5,
	progress_every: int = 50,
	ai_timeout_s: float = 30.0,
	ai_retries: int = 2,
) -> None:
	system_raw, gov_raw = read_input_excel(input_excel)
	system_df = prepare_system_df(system_raw)
	gov_df = prepare_gov_df(gov_raw)

	logger = setup_logging(debug=debug, log_file=log_file)
	logger.info(
		"Starting run | input=%s | output=%s | model=%s | dry_run=%s",
		os.path.abspath(input_excel),
		os.path.abspath(output_excel),
		model,
		dry_run,
	)

	# Precompute uniqueness for conservative direct name matches.
	core_name_counts = (
		system_df["name_core_norm"].fillna("").value_counts(dropna=False).to_dict()
	)

	cache = load_cache(cache_path)
	client = None
	if not dry_run:
		client = _load_openai_client()

	results: List[Dict[str, Any]] = []
	n = len(gov_df) if max_rows is None else min(len(gov_df), max_rows)

	strong_rows = 0
	ai_calls = 0
	cache_hits = 0
	assigned = 0

	for idx in range(n):
		row = gov_df.iloc[idx]
		gov_name = row.get("nama", "")
		gov_address = row.get("alamat", "")
		gov_city = row.get("kabkota_nama", "")

		if debug:
			logger.debug(
				"Row %s/%s | gov_name=%s | gov_city=%s | gov_address=%s",
				idx + 1,
				n,
				str(gov_name),
				str(gov_city),
				str(gov_address),
			)

		# 0) Fast path: if core name is UNIQUE in master, auto-match.
		gov_core = strip_common_prefixes(str(gov_name))
		if gov_core and core_name_counts.get(gov_core, 0) == 1:
			match_row = system_df[system_df["name_core_norm"] == gov_core].iloc[0]
			cand = Candidate(
				system_number=str(match_row.get("number", "")).strip(),
				system_name=str(match_row.get("name", "")).strip(),
				system_address=str(match_row.get("address", "")).strip(),
				system_city=str(match_row.get("area_name", "")).strip(),
				system_type=str(match_row.get("system_type", "")).strip(),
				system_within_hospital=bool(match_row.get("within_hospital", False)),
				score=100.0,
				name_score=100.0,
				addr_score=0.0,
				city_score=0.0,
			)

			# Apply compatibility rules even for direct matches.
			if candidate_is_type_compatible(str(gov_name), cand.system_name) and candidate_is_facility_compatible(
				str(gov_name), cand
			):
				if debug:
					logger.debug(
						"Auto-match (unique core name) | gov_core=%s | %s",
						gov_core,
						_format_candidate(cand),
					)
				results.append(
					{
						"gov_name": str(gov_name),
						"gov_address": str(gov_address),
						"gov_city": str(gov_city),
						"ai_evaluated": False,
						"ai_match": True,
						"auto_recommended": True,
						# assigned (threshold-protected)
						"system_number": cand.system_number,
						"system_name": cand.system_name,
						# suggestion (always visible)
						"suggested_system_number": cand.system_number,
						"suggested_system_name": cand.system_name,
						"confidence": 100,
						"reason": "deterministic: unique core name match in master",
						"strategy": "unique_name",
						"human_final": "",
					}
				)
				assigned += 1
				continue
			else:
				if debug:
					logger.debug(
						"Auto-match blocked by compatibility rules | gov_core=%s | %s",
						gov_core,
						_format_candidate(cand),
					)

		candidates = build_candidates_for_gov_row(
			gov_name=str(gov_name),
			gov_address=str(gov_address),
			gov_city=str(gov_city),
			system_df=system_df,
		)

		if debug and candidates:
			logger.debug("Top %s candidates:", min(debug_top_k, len(candidates)))
			for c in candidates[:debug_top_k]:
				logger.debug("  %s", _format_candidate(c))

		# 1) Name-first (default)
		strong, best, strong_reason = candidate_is_strong_enough(candidates)
		strategy = "name"

		# 2) Address fallback (only if name is not strong)
		if not strong and normalize_text(gov_address):
			strong_addr, best_addr, addr_reason = candidate_is_strong_enough_address(
				candidates
			)
			if strong_addr and best_addr is not None:
				strong, best, strong_reason = True, best_addr, addr_reason
				strategy = "address"
				if debug and best is not None:
					logger.debug(
						"Fallback activated: address | reason=%s | best=%s",
						strong_reason,
						_format_candidate(best),
					)
		ai_evaluated = False
		ai_match = False
		auto_recommended = False
		system_number = ""  # always fill with best suggestion (no threshold gating)
		system_name = ""
		suggested_system_number = ""
		suggested_system_name = ""
		confidence = 0
		reason = ""

		if best and not candidate_is_type_compatible(str(gov_name), best.system_name):
			strong = False
			strong_reason = "type_incompatible_rsud"
		if best and strong and not candidate_is_facility_compatible(str(gov_name), best):
			strong = False
			strong_reason = "facility_incompatible_apotek_klinik"
		if debug and best is not None:
			logger.debug(
				"Deterministic decision | strong=%s | strategy=%s | reason=%s | best=%s",
				strong,
				strategy,
				strong_reason,
				_format_candidate(best),
			)

		# Always expose the best deterministic suggestion (even if not strong)
		if best is not None:
			suggested_system_number = best.system_number
			suggested_system_name = best.system_name
			system_number = best.system_number
			system_name = best.system_name
			confidence = int(round(float(best.score)))
			assigned += 1

		if dry_run:
			reason = f"dry_run: {strong_reason}"
		elif strong and best is not None:
			strong_rows += 1
			ck = cache_key(str(gov_name), str(gov_address), str(gov_city), best, model)
			if ck in cache:
				decision = cache[ck]
				cache_hits += 1
				if debug:
					logger.debug("Cache hit | decision=%s", decision)
			else:
				ai_calls += 1
				attempt = 0
				decision = None
				while attempt <= max(0, ai_retries):
					attempt += 1
					start_t = time.time()
					if debug:
						logger.debug(
							"AI call start | attempt=%s/%s | timeout_s=%s | best=%s",
							attempt,
							max(1, ai_retries + 1),
							ai_timeout_s,
							_format_candidate(best),
						)
					decision = ai_verify_match(
						client=client,
						model=model,
						gov_name=str(gov_name),
						gov_address=str(gov_address),
						gov_city=str(gov_city),
						candidate=best,
						extra_context=(
							"Primary matching strategy: address; name may be unreliable."
							if strategy == "address"
							else "Primary matching strategy: name."
						),
						timeout_s=ai_timeout_s,
					)
					duration = time.time() - start_t
					if debug:
						logger.debug(
							"AI call done | duration_s=%.2f | decision=%s",
							duration,
							decision,
						)

					# If we got a structured response, stop retrying.
					if isinstance(decision, dict) and "match" in decision and "confidence" in decision:
						break

					# Otherwise backoff and retry.
					if attempt <= max(0, ai_retries):
						time.sleep(min(8.0, 0.5 * (2 ** (attempt - 1))))

				decision = decision or {"match": False, "confidence": 0, "reason": "AI error"}
				cache[ck] = decision
				if debug:
					logger.debug("AI decision | decision=%s", decision)

			ai_evaluated = True
			ai_match = bool(decision.get("match", False))
			auto_recommended = ai_match
			confidence = int(decision.get("confidence", confidence))
			reason = str(decision.get("reason", "")).strip()
			if debug:
				logger.debug(
					"AI evaluated | ai_match=%s | confidence=%s | auto_recommended=%s | reason=%s",
					ai_match,
					confidence,
					auto_recommended,
					reason,
				)
		else:
			# Not strong enough deterministically -> no AI; keep deterministic confidence
			reason = f"deterministic_only: {strong_reason}"
			if debug:
				logger.debug("SKIP (no AI) | %s", reason)

		results.append(
			{
				"gov_name": str(gov_name),
				"gov_address": str(gov_address),
				"gov_city": str(gov_city),
				"ai_evaluated": bool(ai_evaluated),
				"ai_match": bool(ai_match),
				"auto_recommended": bool(auto_recommended),
				# assignment fields now always contain the best suggestion (no threshold)
				"system_number": system_number,
				"system_name": system_name,
				# suggestion (for review even if below threshold)
				"suggested_system_number": suggested_system_number,
				"suggested_system_name": suggested_system_name,
				"confidence": confidence,
				"reason": reason,
				"strategy": strategy,
				"human_final": "",
			}
		)

		if progress_every > 0 and (idx + 1) % progress_every == 0:
			save_cache(cache_path, cache)
			logger.info("Progress: %s/%s rows", idx + 1, n)

	save_cache(cache_path, cache)

	all_results_df = pd.DataFrame(results)
	# Excel output:
	# - all_results: ALL rows (including unmatched, for auditing)
	# - matched: only rows auto-recommended by deterministic unique-name or AI match
	matched_df = all_results_df[all_results_df.get("auto_recommended", False) == True].copy()
	cache_df = flatten_cache_for_export(cache)

	# Do not modify input excel; create a new output file only.
	with pd.ExcelWriter(output_excel, engine="openpyxl") as writer:
		all_results_df.to_excel(writer, index=False, sheet_name="all_results")
		matched_df.to_excel(writer, index=False, sheet_name="matched")
		cache_df.to_excel(writer, index=False, sheet_name="cache_all")
		# Optional: dump run metadata for auditing
		meta = pd.DataFrame(
			[
				{
					"created_at": datetime.now(UTC).isoformat(),
					"input_excel": os.path.abspath(input_excel),
					"model": model,
					"min_assign_confidence": min_assign_confidence,
					"dry_run": dry_run,
					"rows_processed": n,
					"rows_written_all_results": int(len(all_results_df)),
					"rows_written_matched": int(len(matched_df)),
					"cache_entries": int(len(cache_df)),
				}
			]
		)
		meta.to_excel(writer, index=False, sheet_name="run_meta")

	logger.info(
		"Run summary | rows=%s | strong_rows=%s | ai_calls=%s | cache_hits=%s | assigned=%s | output=%s | cache=%s",
		n,
		strong_rows if not dry_run else "n/a",
		ai_calls if not dry_run else 0,
		cache_hits if not dry_run else 0,
		assigned,
		os.path.abspath(output_excel),
		os.path.abspath(cache_path),
	)


def build_arg_parser() -> argparse.ArgumentParser:
	p = argparse.ArgumentParser(
		description=(
			"Match government hospital registry (outlet_pemerintah) to internal master (outlet_sistem).\n"
			"Deterministic candidate generation + OpenAI semantic verification (conservative)."
		)
	)
	p.add_argument(
		"--input",
		default=None,
		help=(
			"Path to Excel containing both sheets. If omitted, the script will try to use "
			"'datrs.xlsx' in the same folder as this script."
		),
	)
	p.add_argument(
		"--output",
		default=None,
		help=(
			"Path to output Excel. If omitted, a timestamped file will be created next to the input."
		),
	)
	p.add_argument(
		"--model",
		default=os.getenv("OPENAI_MODEL", "gpt-4o-mini"),
		help="OpenAI model name (default from OPENAI_MODEL or gpt-4o-mini)",
	)
	p.add_argument(
		"--min-confidence",
		type=int,
		default=85,
		help=(
			"Legacy option (default: 85). This script no longer gates assignments by threshold; "
			"use the output 'confidence' column to filter manually."
		),
	)
	p.add_argument(
		"--cache",
		default="hospital_match_cache_v2.json",
		help="JSON cache path for AI decisions (default: hospital_match_cache_v2.json)",
	)
	p.add_argument(
		"--max-rows",
		type=int,
		default=None,
		help="Process only first N government rows (debug)",
	)
	p.add_argument(
		"--dry-run",
		action="store_true",
		help="Run deterministic steps only (no OpenAI calls)",
	)
	p.add_argument(
		"--debug",
		action="store_true",
		help="Enable verbose debug logging (candidates, decisions, cache hits)",
	)
	p.add_argument(
		"--log-file",
		default=None,
		help="Write logs to this file (in addition to terminal)",
	)
	p.add_argument(
		"--debug-top-k",
		type=int,
		default=5,
		help="How many top candidates to print per row in debug mode (default: 5)",
	)
	p.add_argument(
		"--progress-every",
		type=int,
		default=50,
		help="Print progress every N rows (default: 50). Set 0 to disable.",
	)
	p.add_argument(
		"--ai-timeout",
		type=float,
		default=30.0,
		help="Timeout (seconds) for each OpenAI call (default: 30)",
	)
	p.add_argument(
		"--ai-retries",
		type=int,
		default=2,
		help="Retry count for OpenAI call failures/timeouts (default: 2)",
	)
	p.add_argument(
		"--export-cache-only",
		action="store_true",
		help=(
			"Do not run matching. Only export existing cache JSON to Excel (matched + cache_all). "
			"The 'matched' sheet includes cache entries with match=true (no threshold gating). "
			"Requires --cache and optionally --output."
		),
	)
	return p


def main() -> int:
	load_dotenv()
	args = build_arg_parser().parse_args()

	# Make it easy to run from VS Code without passing args.
	script_dir = os.path.dirname(os.path.abspath(__file__))
	input_excel = args.input
	if not input_excel:
		default_input = os.path.join(script_dir, "datrs.xlsx")
		if os.path.exists(default_input):
			input_excel = default_input
		else:
			print(
				"ERROR: Missing --input. Example:\n"
				"  python RMP/main.py --input datrs.xlsx --output hasil_match.xlsx\n",
				file=sys.stderr,
			)
			return 2

	output_excel = args.output
	if not output_excel:
		base_dir = os.path.dirname(os.path.abspath(input_excel)) if input_excel else os.path.dirname(os.path.abspath(__file__))
		ts = datetime.now(UTC).strftime("%Y%m%d_%H%M%S")
		output_excel = os.path.join(base_dir, f"hasil_match_{ts}.xlsx")

	if args.export_cache_only:
		try:
			export_from_cache_only(
				cache_path=args.cache,
				output_excel=output_excel,
				min_assign_confidence=args.min_confidence,
			)
		except Exception as exc:
			print(f"ERROR: {exc}", file=sys.stderr)
			return 1
		print(f"Cache exported to: {os.path.abspath(output_excel)}")
		return 0

	try:
		run_matching(
			input_excel=input_excel,
			output_excel=output_excel,
			model=args.model,
			min_assign_confidence=args.min_confidence,
			cache_path=args.cache,
			max_rows=args.max_rows,
			dry_run=args.dry_run,
			debug=args.debug,
			log_file=args.log_file,
			debug_top_k=args.debug_top_k,
			progress_every=args.progress_every,
			ai_timeout_s=args.ai_timeout,
			ai_retries=args.ai_retries,
		)
	except KeyboardInterrupt:
		return 130
	except Exception as exc:
		print(f"ERROR: {exc}", file=sys.stderr)
		return 1

	return 0


if __name__ == "__main__":
	raise SystemExit(main())

