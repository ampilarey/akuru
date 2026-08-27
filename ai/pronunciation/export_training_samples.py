#!/usr/bin/env python3
"""Copy manifest audio into the dataset/<letter>/<haraka>/ folders
(SPEC 51.10 layout) for inspection or classic folder-based training.

Usage:
  python3 export_training_samples.py --manifest /path/to/manifest.json --storage-root /path/to/storage/app
"""

from __future__ import annotations

import argparse
import json
import os
import shutil


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--storage-root", required=True)
    args = parser.parse_args()

    dataset_root = os.path.join(os.path.dirname(os.path.abspath(__file__)), "dataset")
    with open(args.manifest, encoding="utf-8") as fh:
        rows = json.load(fh)

    copied = 0
    for row in rows:
        source = os.path.join(args.storage_root, row["path"])
        if not os.path.exists(source):
            print(f"missing {source}")
            continue
        target_dir = os.path.join(dataset_root, row["letter"], row["haraka"])
        os.makedirs(target_dir, exist_ok=True)
        extension = os.path.splitext(source)[1] or ".wav"
        shutil.copy2(source, os.path.join(target_dir, f"sample-{row['sample_id']}{extension}"))
        copied += 1

    print(f"copied {copied} samples into {dataset_root}")


if __name__ == "__main__":
    main()
