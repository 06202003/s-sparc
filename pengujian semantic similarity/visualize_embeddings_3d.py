import argparse
import json
from pathlib import Path
import time
import sys

import matplotlib.pyplot as plt
import numpy as np
from mpl_toolkits.mplot3d import Axes3D  # noqa: F401
from matplotlib.widgets import Slider
from sklearn.decomposition import PCA
from sklearn.cluster import KMeans
from sklearn.manifold import TSNE


def info(message: str):
    print(message, flush=True)


try:
    sys.stdout.reconfigure(line_buffering=True)
except Exception:
    pass


def load_embeddings(json_path: Path):
    with json_path.open("r", encoding="utf-8") as file:
        data = json.load(file)

    if not isinstance(data, list) or len(data) == 0:
        raise ValueError("Dataset JSON harus berupa list dan tidak boleh kosong.")

    ids = []
    prompts = []
    embeddings = []

    for index, row in enumerate(data):
        embedding = row.get("embedding")
        if embedding is None:
            continue

        try:
            vector = np.array(embedding, dtype=np.float32)
        except Exception:
            continue

        if vector.ndim != 1 or vector.size == 0:
            continue

        row_id = str(row.get("id") or row.get("snippet_id") or index)
        prompt = str(row.get("prompt") or "")

        ids.append(row_id)
        prompts.append(prompt)
        embeddings.append(vector)

    if not embeddings:
        raise ValueError("Tidak ada embedding valid di dataset.")

    emb = np.vstack(embeddings)
    return ids, prompts, emb


def print_embedding_analysis(ids, embeddings: np.ndarray):
    norms = np.linalg.norm(embeddings, axis=1)
    info(f"[INFO] Total titik valid         : {len(ids)}")
    info(f"[INFO] Dimensi embedding         : {embeddings.shape[1]}")
    info(
        f"[INFO] Norm embedding (min/avg/max): "
        f"{norms.min():.4f} / {norms.mean():.4f} / {norms.max():.4f}"
    )

    safe_norms = np.where(norms == 0, 1.0, norms)
    normalized = embeddings / safe_norms[:, None]
    sim = normalized @ normalized.T
    np.fill_diagonal(sim, -np.inf)
    top1 = sim.max(axis=1)

    info(
        f"[INFO] Cosine top-1 (min/avg/max): "
        f"{top1.min():.4f} / {top1.mean():.4f} / {top1.max():.4f}"
    )


def print_reduction_analysis(points: np.ndarray, method: str):
    mins = points.min(axis=0)
    maxs = points.max(axis=0)
    spreads = maxs - mins
    info(f"[INFO] Metode reduksi            : {method.upper()}")
    info(
        "[INFO] Rentang dimensi 3D        : "
        f"x[{mins[0]:.3f}, {maxs[0]:.3f}] "
        f"y[{mins[1]:.3f}, {maxs[1]:.3f}] "
        f"z[{mins[2]:.3f}, {maxs[2]:.3f}]"
    )
    info(
        "[INFO] Spread dimensi            : "
        f"dx={spreads[0]:.3f}, dy={spreads[1]:.3f}, dz={spreads[2]:.3f}"
    )


def choose_subset(ids, prompts, embeddings: np.ndarray, volume: int, top_k: int, random_state: int, sample_mode: str):
    total = len(ids)
    volume = max(1, min(100, volume))
    volume_count = max(10, int(round(total * (volume / 100.0))))

    if top_k <= 0:
        final_count = min(total, volume_count)
    else:
        final_count = min(total, top_k, volume_count)

    if final_count >= total:
        info(f"[INFO] Volume data               : {volume}% (menampilkan semua {total} data)")
        return ids, prompts, embeddings

    info(f"[INFO] Volume data               : {volume}% -> {final_count} dari {total} data")

    if sample_mode == "first":
        selected_indices = np.arange(final_count)
    else:
        rng = np.random.default_rng(seed=random_state)
        selected_indices = np.sort(rng.choice(total, size=final_count, replace=False))

    selected_ids = [ids[i] for i in selected_indices]
    selected_prompts = [prompts[i] for i in selected_indices]
    selected_embeddings = embeddings[selected_indices]
    return selected_ids, selected_prompts, selected_embeddings


def build_clusters(embeddings: np.ndarray, n_clusters: int, random_state: int):
    n_samples = embeddings.shape[0]
    n_clusters = max(2, min(n_clusters, n_samples))
    model = KMeans(n_clusters=n_clusters, n_init=10, random_state=random_state)
    labels = model.fit_predict(embeddings)
    return labels, n_clusters


def print_cluster_analysis(cluster_labels: np.ndarray, n_clusters: int):
    counts = np.bincount(cluster_labels, minlength=n_clusters)
    info(f"[INFO] Jumlah cluster             : {n_clusters}")
    for idx, count in enumerate(counts):
        info(f"[INFO]  - Cluster {idx:02d}            : {int(count)} titik")


def build_order(n_items: int, sample_mode: str, random_state: int):
    if sample_mode == "first":
        return np.arange(n_items)
    rng = np.random.default_rng(seed=random_state)
    return rng.permutation(n_items)


def count_from_volume(total: int, volume: int):
    volume = max(1, min(100, int(volume)))
    return max(10, min(total, int(round(total * (volume / 100.0)))))


def resolve_input_path(input_value: str):
    raw_path = Path(input_value)
    if raw_path.is_absolute() and raw_path.exists():
        return raw_path

    script_dir = Path(__file__).resolve().parent
    project_root = script_dir.parent
    candidates = [
        raw_path,
        Path.cwd() / raw_path,
        script_dir / raw_path,
        project_root / raw_path,
    ]

    for candidate in candidates:
        if candidate.exists():
            return candidate.resolve()

    raise FileNotFoundError(f"File dataset tidak ditemukan: {raw_path}")


def reduce_to_3d(embeddings: np.ndarray, method: str, random_state: int, perplexity: float):
    if method == "pca":
        reducer = PCA(n_components=3, random_state=random_state)
        return reducer.fit_transform(embeddings)

    n_samples = embeddings.shape[0]
    max_perplexity = max(2.0, min(50.0, float(n_samples - 1) / 3.0))
    effective_perplexity = min(perplexity, max_perplexity)

    reducer = TSNE(
        n_components=3,
        random_state=random_state,
        perplexity=effective_perplexity,
        init="pca",
        learning_rate="auto",
        verbose=1,
    )
    return reducer.fit_transform(embeddings)


def plot_3d(
    points: np.ndarray,
    ids,
    prompts,
    embeddings: np.ndarray,
    order_indices: np.ndarray,
    init_volume: int,
    n_clusters: int,
    random_state: int,
    title: str,
    annotate: bool,
    id_length: int,
    use_slider: bool,
    save_path: str,
    no_show: bool,
):
    fig = plt.figure(figsize=(11, 8))
    if use_slider and not no_show:
        plt.subplots_adjust(bottom=0.18)
    ax = fig.add_subplot(111, projection="3d")

    text_labels = []

    def build_subset(volume_value: int):
        count = count_from_volume(len(order_indices), volume_value)
        selected = np.sort(order_indices[:count])
        sub_points = points[selected]
        sub_ids = [ids[i] for i in selected]
        sub_prompts = [prompts[i] for i in selected]
        sub_embeddings = embeddings[selected]
        labels, effective_clusters = build_clusters(
            embeddings=sub_embeddings,
            n_clusters=n_clusters,
            random_state=random_state,
        )
        return sub_points, sub_ids, sub_prompts, labels, effective_clusters, count

    initial_points, initial_ids, initial_prompts, initial_labels, initial_clusters, initial_count = build_subset(init_volume)
    info(
        f"[INFO] Initial view             : volume={max(1, min(100, init_volume))}% | "
        f"points={initial_count} | clusters={initial_clusters}"
    )

    scatter = ax.scatter(
        initial_points[:, 0],
        initial_points[:, 1],
        initial_points[:, 2],
        c=initial_labels,
        cmap="tab20",
        s=24,
        alpha=0.8,
    )

    def draw_texts(sub_points, sub_ids):
        nonlocal text_labels
        for txt in text_labels:
            txt.remove()
        text_labels = []
        if annotate:
            for i, row_id in enumerate(sub_ids):
                short_id = str(row_id)[:id_length]
                txt = ax.text(sub_points[i, 0], sub_points[i, 1], sub_points[i, 2], short_id, fontsize=7, alpha=0.75)
                text_labels.append(txt)

    draw_texts(initial_points, initial_ids)

    ax.set_title(title)
    ax.set_xlabel("Dim 1")
    ax.set_ylabel("Dim 2")
    ax.set_zlabel("Dim 3")

    cbar = plt.colorbar(scatter, ax=ax, pad=0.08)
    cbar.set_label("Label Cluster")

    current_prompts = list(initial_prompts)
    current_ids = list(initial_ids)

    def set_axes_equal(sub_points):
        x_min, x_max = sub_points[:, 0].min(), sub_points[:, 0].max()
        y_min, y_max = sub_points[:, 1].min(), sub_points[:, 1].max()
        z_min, z_max = sub_points[:, 2].min(), sub_points[:, 2].max()

        max_range = max(x_max - x_min, y_max - y_min, z_max - z_min) / 2.0
        mid_x = (x_max + x_min) / 2.0
        mid_y = (y_max + y_min) / 2.0
        mid_z = (z_max + z_min) / 2.0

        ax.set_xlim(mid_x - max_range, mid_x + max_range)
        ax.set_ylim(mid_y - max_range, mid_y + max_range)
        ax.set_zlim(mid_z - max_range, mid_z + max_range)

    set_axes_equal(initial_points)

    def on_pick(event):
        idx = event.ind[0]
        prompt = current_prompts[idx][:200].replace("\n", " ")
        info(f"[PICK] id={current_ids[idx]} | prompt={prompt}")

    def update_plot(volume_value: int):
        nonlocal current_prompts, current_ids
        sub_points, sub_ids, sub_prompts, sub_labels, effective_clusters, count = build_subset(volume_value)
        scatter._offsets3d = (sub_points[:, 0], sub_points[:, 1], sub_points[:, 2])
        scatter.set_array(np.asarray(sub_labels, dtype=float))
        draw_texts(sub_points, sub_ids)
        set_axes_equal(sub_points)
        ax.set_title(title)
        cbar.update_normal(scatter)
        current_prompts = sub_prompts
        current_ids = sub_ids
        cluster_counts = np.bincount(sub_labels, minlength=effective_clusters)
        info(
            f"[INFO] Slider update            : volume={int(volume_value)}% | "
            f"points={count} | clusters={effective_clusters} | "
            f"cluster_size(min/max)={int(cluster_counts.min())}/{int(cluster_counts.max())}"
        )
        fig.canvas.draw_idle()

    if use_slider and not no_show:
        slider_ax = fig.add_axes([0.20, 0.06, 0.60, 0.03])
        volume_slider = Slider(
            ax=slider_ax,
            label="Volume Data (%)",
            valmin=1,
            valmax=100,
            valinit=max(1, min(100, init_volume)),
            valstep=1,
        )

        def on_slider_change(val):
            update_plot(int(val))

        volume_slider.on_changed(on_slider_change)
        info("[INFO] Slider aktif             : geser Volume Data (%) untuk ubah jumlah titik")

    fig.canvas.mpl_connect("pick_event", on_pick)
    scatter.set_picker(True)
    if not (use_slider and not no_show):
        plt.tight_layout()
    if save_path:
        output_path = Path(save_path)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        fig.savefig(output_path, dpi=220, bbox_inches="tight")
        info(f"[INFO] Plot disimpan             : {output_path}")

    if no_show:
        plt.close(fig)
    else:
        plt.show()


def main():
    parser = argparse.ArgumentParser(description="Visualisasi 3D embedding tanpa Neo4j")
    parser.add_argument(
        "--input",
        type=str,
        default="semantic_similarity/mbpp_all_with_embedding_and_relevance_v2.json",
        help="Path file JSON dataset yang berisi kolom embedding",
    )
    parser.add_argument("--top-k", type=int, default=0, help="Batas jumlah data (0 = tidak dibatasi)")
    parser.add_argument("--volume", type=int, default=100, help="Intensitas volume data 1-100 (default 100 = semua)")
    parser.add_argument("--ask-volume", action="store_true", help="Minta input volume data interaktif sebelum proses")
    parser.add_argument("--sample-mode", choices=["random", "first"], default="random", help="Cara memilih subset data jika tidak full")
    parser.add_argument("--method", choices=["tsne", "pca"], default="tsne", help="Metode reduksi dimensi")
    parser.add_argument("--perplexity", type=float, default=30.0, help="Perplexity untuk t-SNE")
    parser.add_argument("--clusters", type=int, default=8, help="Jumlah cluster KMeans")
    parser.add_argument("--random-state", type=int, default=42, help="Random seed")
    parser.add_argument("--annotate", action="store_true", help="Tampilkan label ID per titik")
    parser.add_argument("--id-length", type=int, default=10, help="Jumlah karakter awal ID yang ditampilkan")
    parser.add_argument("--slider", action="store_true", help="Aktifkan slider volume data di plot")
    parser.add_argument("--save-path", type=str, default="", help="Simpan plot ke file PNG/JPG")
    parser.add_argument("--no-show", action="store_true", help="Tidak membuka window plot (headless)")
    args = parser.parse_args()

    start_time = time.time()
    info("[INFO] Memulai visualisasi embedding 3D...")

    input_path = resolve_input_path(args.input)

    info(f"[INFO] Dataset input             : {input_path}")
    ids, prompts, embeddings = load_embeddings(input_path)

    volume = args.volume
    if args.ask_volume:
        try:
            raw_volume = input("[INPUT] Masukkan volume data (1-100, default 100): ").strip()
            if raw_volume:
                volume = int(raw_volume)
        except Exception:
            pass

    if args.top_k > 0:
        cap = min(len(ids), max(10, args.top_k))
        ids = ids[:cap]
        prompts = prompts[:cap]
        embeddings = embeddings[:cap]
        info(f"[INFO] Batas top-k aktif         : {cap} data")

    order_indices = build_order(
        n_items=len(ids),
        sample_mode=args.sample_mode,
        random_state=args.random_state,
    )

    init_count = count_from_volume(len(ids), volume)
    preview_selected = np.sort(order_indices[:init_count])
    preview_ids = [ids[i] for i in preview_selected]
    preview_embeddings = embeddings[preview_selected]
    info(f"[INFO] Volume data               : {volume}% -> {init_count} dari {len(ids)} data")
    print_embedding_analysis(preview_ids, preview_embeddings)

    preview_labels, preview_clusters = build_clusters(
        embeddings=preview_embeddings,
        n_clusters=args.clusters,
        random_state=args.random_state,
    )
    print_cluster_analysis(preview_labels, preview_clusters)

    reduce_start = time.time()
    points = reduce_to_3d(
        embeddings=embeddings,
        method=args.method,
        random_state=args.random_state,
        perplexity=args.perplexity,
    )
    info(f"[INFO] Waktu reduksi dimensi     : {time.time() - reduce_start:.2f}s")
    print_reduction_analysis(points, args.method)

    title = "3D Embedding Visualization"
    plot_3d(
        points,
        ids,
        prompts,
        embeddings=embeddings,
        order_indices=order_indices,
        init_volume=volume,
        n_clusters=args.clusters,
        random_state=args.random_state,
        title=title,
        annotate=args.annotate,
        id_length=max(1, args.id_length),
        use_slider=args.slider,
        save_path=args.save_path,
        no_show=args.no_show,
    )
    info(f"[INFO] Total waktu proses        : {time.time() - start_time:.2f}s")


if __name__ == "__main__":
    main()
