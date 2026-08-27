#!/usr/bin/env python3
"""Batch trainer (SPEC 51.16 step 7): manifest of approved samples → new
model version files. Never overwrites an existing model file — training
writes a timestamped .h5 you register in the admin screen.

Usage:
  python3 train.py --manifest /path/to/manifest.json --storage-root /path/to/storage/app
"""

from __future__ import annotations

import argparse
import json
import os
import time


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--storage-root", required=True, help="Laravel storage/app root the manifest paths are relative to")
    parser.add_argument("--epochs", type=int, default=30)
    args = parser.parse_args()

    import numpy as np
    from sklearn.model_selection import train_test_split

    from audio_processor import load_features
    from model import build_model

    with open(args.manifest, encoding="utf-8") as fh:
        rows = json.load(fh)
    if not rows:
        raise SystemExit("Manifest is empty — approve samples first.")

    letters = sorted({row["letter"] for row in rows})
    harakas = sorted({row["haraka"] for row in rows})
    letter_index = {name: i for i, name in enumerate(letters)}
    haraka_index = {name: i for i, name in enumerate(harakas)}

    features, letter_y, haraka_y = [], [], []
    for row in rows:
        path = os.path.join(args.storage_root, row["path"])
        try:
            features.append(load_features(path))
            letter_y.append(letter_index[row["letter"]])
            haraka_y.append(haraka_index[row["haraka"]])
        except Exception as exc:  # skip undecodable clips, keep training
            print(f"skip {path}: {exc}")

    x = np.stack(features)
    letter_y = np.array(letter_y)
    haraka_y = np.array(haraka_y)
    x_train, x_val, ly_train, ly_val, hy_train, hy_val = train_test_split(
        x, letter_y, haraka_y, test_size=0.2, random_state=42
    )

    model = build_model(x.shape[1:], len(letters), len(harakas))
    model.fit(
        x_train, {"letter": ly_train, "haraka": hy_train},
        validation_data=(x_val, {"letter": ly_val, "haraka": hy_val}),
        epochs=args.epochs,
        batch_size=16,
    )
    metrics = model.evaluate(x_val, {"letter": ly_val, "haraka": hy_val}, return_dict=True, verbose=0)

    models_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "models")
    os.makedirs(models_dir, exist_ok=True)
    stamp = time.strftime("%Y%m%d-%H%M%S")
    model_path = os.path.join(models_dir, f"arabic_letter_haraka_model-{stamp}.h5")
    model.save(model_path)
    with open(os.path.join(models_dir, "letter_labels.json"), "w", encoding="utf-8") as fh:
        json.dump(letters, fh)
    with open(os.path.join(models_dir, "haraka_labels.json"), "w", encoding="utf-8") as fh:
        json.dump(harakas, fh)

    print(json.dumps({
        "model_path": model_path,
        "training_sample_count": len(rows),
        "validation_letter_accuracy": round(float(metrics.get("letter_accuracy", 0)), 4),
        "validation_haraka_accuracy": round(float(metrics.get("haraka_accuracy", 0)), 4),
    }))


if __name__ == "__main__":
    main()
