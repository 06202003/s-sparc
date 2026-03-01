import os
from dotenv import load_dotenv
from neo4j import GraphDatabase
from datetime import datetime
from typing import List, Dict
import mysql.connector
import json
import logging
import matplotlib.pyplot as plt
from sklearn.manifold import TSNE
import numpy as np
from mpl_toolkits.mplot3d import Axes3D

# Load environment variables from .env file
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class Neo4jGraphDB:
    def __init__(self, uri: str, user: str, password: str, vector_dimensions: int):
        """
        Initialize the Neo4jGraphDB class with connection details and vector dimensions.
        """
        self.uri = uri
        self.user = user
        self.password = password
        self.vector_dimensions = vector_dimensions
        self.driver = GraphDatabase.driver(uri, auth=(user, password))

    def close(self):
        """
        Close the connection to the Neo4j database.
        """
        if self.driver:
            self.driver.close()

    def create_schema(self, mysql_config: dict):
        """
        Create schema constraints and vector index if they do not already exist.
        Automatically detects vector dimensions from MySQL database.
        """
        logging.info("Starting schema creation...")
        # Detect vector dimensions from MySQL database
        self.vector_dimensions = self.detect_dimension_from_mysql(mysql_config)
        logging.info(f"Detected vector dimensions: {self.vector_dimensions}")

        with self.driver.session() as session:
            # Create unique constraint for User.id
            logging.info("Creating unique constraint for User.id...")
            session.execute_write(self._create_constraint, "User", "id")
            logging.info("Unique constraint for User.id created.")

            # Create unique constraint for CodeSnippet.id
            logging.info("Creating unique constraint for CodeSnippet.id...")
            session.execute_write(self._create_constraint, "CodeSnippet", "id")
            logging.info("Unique constraint for CodeSnippet.id created.")

            # Create vector index for CodeSnippet.embedding
            logging.info("Creating vector index for CodeSnippet.embedding...")
            session.execute_write(self._create_vector_index, "CodeSnippet", "embedding", self.vector_dimensions)
            logging.info("Vector index for CodeSnippet.embedding created.")

        logging.info("Schema creation completed.")

    def detect_dimension_from_mysql(self, mysql_config: dict) -> int:
        """
        Detect the dimension of the embedding from the MySQL database.

        Args:
            mysql_config (dict): MySQL connection configuration.

        Returns:
            int: The dimension of the embedding.
        """
        conn = mysql.connector.connect(**mysql_config)
        cursor = conn.cursor()
        cursor.execute("SELECT embedding FROM code_embeddings LIMIT 1")
        row = cursor.fetchone()
        conn.close()

        if row:
            emb = row[0]
            embedding_list = [float(x.strip()) for x in emb.replace("[", "").replace("]", "").split(",") if x.strip()]
            return len(embedding_list)

        raise ValueError("No embedding found in the database.")

    @staticmethod
    def _create_constraint(tx, label: str, property_name: str):
        """
        Create a unique constraint on a label and property if it doesn't exist.
        """
        query = f"CREATE CONSTRAINT IF NOT EXISTS FOR (n:{label}) REQUIRE n.{property_name} IS UNIQUE"
        tx.run(query)

    @staticmethod
    def _create_vector_index(tx, label: str, property_name: str, dimensions: int):
        """
        Create a vector index on a property with the specified dimensions.
        Updated for Neo4j Aura 5.x syntax.
        """
        index_name = f"vector_index_{label}_{property_name}"
        query = (
            f"CREATE VECTOR INDEX {index_name} IF NOT EXISTS "
            f"FOR (n:{label}) "
            f"ON (n.{property_name}) "
            f"OPTIONS {{indexConfig: {{`vector.dimensions`: {dimensions}, `vector.similarity_function`: 'cosine'}}}}"
        )
        tx.run(query)

    def insert_user(self, user_id: str):
        """
        Insert a User node with a unique ID.
        """
        with self.driver.session() as session:
            session.execute_write(self._insert_user, user_id)

    @staticmethod
    def _insert_user(tx, user_id: str):
        """
        Create a User node if it doesn't already exist.
        """
        query = """
        MERGE (u:User {id: $user_id})
        RETURN u
        """
        tx.run(query, user_id=user_id)

    def insert_code_snippet(self, user_id: str, snippet_id: str, prompt: str, code: str, embedding: List[float]):
        """
        Insert a CodeSnippet node and create a relationship to a User node.
        """
        if len(embedding) != self.vector_dimensions:
            raise ValueError(f"Embedding dimension mismatch. Expected {self.vector_dimensions}, got {len(embedding)}")

        with self.driver.session() as session:
            session.execute_write(
                self._insert_code_snippet, user_id, snippet_id, prompt, code, embedding
            )

    @staticmethod
    def _insert_code_snippet(tx, user_id: str, snippet_id: str, prompt: str, code: str, embedding: List[float]):
        """
        Create a CodeSnippet node and a relationship to a User node.
        Ensures the User node is created if it does not exist.
        """
        query = """
        MERGE (u:User {id: $user_id})
        MERGE (c:CodeSnippet {id: $snippet_id})
        ON CREATE SET 
            c.prompt = $prompt,
            c.code = $code,
            c.embedding = $embedding,
            c.created_at = $created_at
        MERGE (u)-[:CREATED]->(c)
        """
        tx.run(query, user_id=user_id, snippet_id=snippet_id, prompt=prompt, code=code, embedding=embedding, created_at=datetime.utcnow().isoformat())

    def vector_search(self, embedding: List[float], top_k: int) -> List[Dict]:
        """
        Perform a vector search on CodeSnippet nodes using cosine similarity.
        Updated for Neo4j Aura 5.x syntax.
        """
        if len(embedding) != self.vector_dimensions:
            raise ValueError(f"Embedding dimension mismatch. Expected {self.vector_dimensions}, got {len(embedding)}")

        index_name = f"vector_index_CodeSnippet_embedding"

        with self.driver.session() as session:
            results = session.execute_read(self._vector_search, index_name, embedding, top_k)
        return results

    @staticmethod
    def _vector_search(tx, index_name: str, embedding: List[float], top_k: int) -> List[Dict]:
        """
        Execute the vector search query using the correct Neo4j 5.x syntax.
        """
        query = """
        CALL db.index.vector.queryNodes($index_name, $top_k, $embedding)
        YIELD node, score
        RETURN node.id AS id, node.prompt AS prompt, node.code AS code, score
        ORDER BY score DESC
        """
        result = tx.run(query, index_name=index_name, embedding=embedding, top_k=top_k)
        return [record.data() for record in result]

    def import_code_embeddings(self, mysql_config: dict):
        """
        Import all code embeddings from a MySQL database into the Neo4j database.

        Args:
            mysql_config (dict): MySQL connection configuration containing host, user, password, database, and port.
        """
        logging.info("Starting code embeddings import...")

        # Connect to the MySQL database
        conn = mysql.connector.connect(
            host=mysql_config["host"],
            user=mysql_config["user"],
            password=mysql_config["password"],
            database=mysql_config["database"],
            port=mysql_config.get("port", 3306)
        )
        cursor = conn.cursor()

        # Execute the query to fetch embeddings
        cursor.execute("SELECT id AS snippet_id, prompt, code, embedding, created_at, user_id FROM code_embeddings")

        # Fetch code embeddings in batches from the MySQL database
        batch_size = 1000
        total_rows = 0
        while True:
            rows = cursor.fetchmany(batch_size)
            if not rows:
                break

            logging.info(f"Processing batch of {len(rows)} rows...")

            # Batch insert into Neo4j using UNWIND for better performance
            with self.driver.session() as session:
                session.execute_write(self._batch_insert_code_snippets, rows, self.vector_dimensions)

            total_rows += len(rows)
            logging.info(f"Total rows processed: {total_rows}")

        # Close the MySQL connection
        conn.close()
        logging.info("Code embeddings import completed.")

    @staticmethod
    def _batch_insert_code_snippets(tx, rows: List[tuple], vector_dimensions: int):
        """
        Batch insert code snippets into Neo4j using UNWIND for better performance.
        Validates embedding dimensions before inserting.
        """
        query = """
        UNWIND $rows AS row
        MERGE (u:User {id: row.user_id})
        MERGE (c:CodeSnippet {id: row.snippet_id})
        ON CREATE SET 
            c.prompt = row.prompt,
            c.code = row.code,
            c.embedding = row.embedding,
            c.created_at = row.created_at
        MERGE (u)-[:CREATED]->(c)
        """
        formatted_rows = []
        for row in rows:
            try:
                embedding_list = json.loads(row[3])

                # Validate embedding dimensions
                if len(embedding_list) != vector_dimensions:
                    logging.warning(f"Skipping row with invalid embedding dimensions: {len(embedding_list)}")
                    continue  # Skip invalid rows or handle as needed

                formatted_rows.append({
                    "user_id": row[5],
                    "snippet_id": row[0],
                    "prompt": row[1],
                    "code": row[2],
                    "embedding": embedding_list,
                    "created_at": row[4]
                })
            except json.JSONDecodeError as e:
                logging.error(f"Error decoding JSON for row: {row}. Error: {e}")
                continue

        if formatted_rows:
            logging.info(f"Inserting {len(formatted_rows)} valid rows into Neo4j...")
            tx.run(query, rows=formatted_rows)
        else:
            logging.warning("No valid rows to insert in this batch.")

def visualize_vector_similarity(db: Neo4jGraphDB, top_k: int = 100):
    """
    Visualize the similarity of vectors using t-SNE in 3D with zoom and dynamic axis adjustment.

    Args:
        db (Neo4jGraphDB): The Neo4j database instance.
        top_k (int): Number of vectors to visualize.
    """
    logging.info("Fetching vectors from Neo4j for visualization...")

    # Query Neo4j to fetch embeddings and their IDs
    query = f"""
    MATCH (c:CodeSnippet)
    RETURN c.id AS id, c.embedding AS embedding
    LIMIT {top_k}
    """
    with db.driver.session() as session:
        results = session.run(query)
        data = [(record["id"], record["embedding"]) for record in results]

    if not data:
        logging.warning("No data found for visualization.")
        return

    # Extract IDs and embeddings
    ids, embeddings = zip(*data)
    embeddings = np.array(embeddings)  # Convert embeddings to a NumPy array

    # Apply t-SNE for dimensionality reduction to 3D
    logging.info("Applying t-SNE for 3D dimensionality reduction...")
    tsne = TSNE(n_components=3, random_state=42)
    reduced_embeddings = tsne.fit_transform(embeddings)

    # Plot the reduced embeddings in 3D with interactive zoom and dynamic axis adjustment
    logging.info("Generating the 3D visualization plot with scroll zoom and dynamic axes...")
    fig = plt.figure(figsize=(10, 8))
    ax = fig.add_subplot(111, projection='3d')
    scatter = ax.scatter(
        reduced_embeddings[:, 0],
        reduced_embeddings[:, 1],
        reduced_embeddings[:, 2],
        c='blue', alpha=0.6, label='Code Snippets'
    )

    # Annotate points with shortened IDs
    for i, id in enumerate(ids):
        short_id = id[:8]  # Shorten the ID to the first 8 characters
        ax.text(reduced_embeddings[i, 0], reduced_embeddings[i, 1], reduced_embeddings[i, 2], short_id, fontsize=8, alpha=0.7)

    # Set axis labels and title
    ax.set_title("3D t-SNE Visualization of Code Snippet Embeddings")
    ax.set_xlabel("t-SNE Dimension 1")
    ax.set_ylabel("t-SNE Dimension 2")
    ax.set_zlabel("t-SNE Dimension 3")

    # Adjust the limits of the axes dynamically based on the data range
    x_min, x_max = reduced_embeddings[:, 0].min(), reduced_embeddings[:, 0].max()
    y_min, y_max = reduced_embeddings[:, 1].min(), reduced_embeddings[:, 1].max()
    z_min, z_max = reduced_embeddings[:, 2].min(), reduced_embeddings[:, 2].max()

    max_range = max(x_max - x_min, y_max - y_min, z_max - z_min) / 2.0
    mid_x = (x_max + x_min) / 2.0
    mid_y = (y_max + y_min) / 2.0
    mid_z = (z_max + z_min) / 2.0

    ax.set_xlim(mid_x - max_range, mid_x + max_range)
    ax.set_ylim(mid_y - max_range, mid_y + max_range)
    ax.set_zlim(mid_z - max_range, mid_z + max_range)

    # Enable interactive zooming and dynamic axis adjustment
    def on_scroll(event):
        if event.button == 'up':
            ax.set_xlim([ax.get_xlim()[0] * 0.9, ax.get_xlim()[1] * 0.9])
            ax.set_ylim([ax.get_ylim()[0] * 0.9, ax.get_ylim()[1] * 0.9])
            ax.set_zlim([ax.get_zlim()[0] * 0.9, ax.get_zlim()[1] * 0.9])
        elif event.button == 'down':
            ax.set_xlim([ax.get_xlim()[0] * 1.1, ax.get_xlim()[1] * 1.1])
            ax.set_ylim([ax.get_ylim()[0] * 1.1, ax.get_ylim()[1] * 1.1])
            ax.set_zlim([ax.get_zlim()[0] * 1.1, ax.get_zlim()[1] * 1.1])
        fig.canvas.draw_idle()

    fig.canvas.mpl_connect('scroll_event', on_scroll)

    plt.legend()
    plt.show()

# Example usage
if __name__ == "__main__":
    # Load configuration from .env file
    CONFIG = {
        "uri": os.getenv("NEO4J_URI", "neo4j+s://2409ffe7.databases.neo4j.io"),
        "user": os.getenv("NEO4J_USER", "2409ffe7"),
        "password": os.getenv("NEO4J_PASSWORD", "5evLS2LqpS1gLf8rpHk_L5RzWOcVhUnAYEdzOvyqikQ"),
        "vector_dimensions": int(os.getenv("VECTOR_DIMENSIONS", 768)),  # Default to 768 dimensions
        "mysql_config": {
            "host": os.getenv("MYSQL_HOST", "127.0.0.1"),
            "user": os.getenv("MYSQL_USER", "david"),
            "password": os.getenv("MYSQL_PASSWORD", "david20juni2003#"),
            "database": os.getenv("MYSQL_DB", "db_semantic_v3"),
            "port": int(os.getenv("MYSQL_PORT", 3306))
        }
    }

    # Initialize the database connection
    db = Neo4jGraphDB(
        uri=CONFIG["uri"],
        user=CONFIG["user"],
        password=CONFIG["password"],
        vector_dimensions=CONFIG["vector_dimensions"]
    )

    try:
        # Step 1: Create schema
        db.create_schema(CONFIG["mysql_config"])

        # Step 2: Import code embeddings from MySQL database
        db.import_code_embeddings(CONFIG["mysql_config"])

        # Step 3: Visualize vector similarity
        visualize_vector_similarity(db, top_k=100)

    finally:
        # Close the database connection
        db.close()