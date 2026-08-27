#!/usr/bin/env python3
"""CLI predictor (SPEC 51.11): audio path in, JSON out — nothing else.

Usage: python3 predict.py /path/to/audio.wav

Prints exactly one JSON object. Any failure (missing model, missing
dependencies, bad audio) is reported as {"success": false, "error": ...}
so the Laravel side degrades to the human queue instead of crashing.
"""

from __future__ import annotations

import json
import os
import sys

MODEL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "models")
MODEL_PATH = os.environ.get(
    "AI_PRONUNCIATION_MODEL_PATH",
    os.path.join(MODEL_DIR, "arabic_letter_haraka_model.h5"),
)
LETTER_LABELS = os.path.join(MODEL_DIR, "letter_labels.json")
HARAKA_LABELS = os.path.join(MODEL_DIR, "haraka_labels.json")
MODEL_VERSION = os.environ.get("AI_PRONUNCIATION_MODEL_VERSION", "arabic_pronunciation_v1")


def fail(message: str) -> None:
    print(json.dumps({"success": False, "error": message}))
    sys.exit(0)


def main() -> None:
    if len(sys.argv) < 2:
        fail("Usage: predict.py <audio_path>")
    audio_path = sys.argv[1]
    if not os.path.exists(audio_path):
        fail(f"Audio file not found: {audio_path}")
    if not os.path.exists(MODEL_PATH):
        fail("Model file not found — train and register a model first.")

    try:
        import numpy as np
        from tensorflow import keras

        from audio_processor import load_features
    except Exception as exc:
        fail(f"Missing dependency: {exc}")
        return

    try:
        with open(LETTER_LABELS, encoding="utf-8") as fh:
            letter_labels = json.load(fh)
        with open(HARAKA_LABELS, encoding="utf-8") as fh:
            haraka_labels = json.load(fh)

        features = load_features(audio_path)
        model = keras.models.load_model(MODEL_PATH)
        letter_probs, haraka_probs = model.predict(np.expand_dims(features, 0), verbose=0)

        letter_index = int(np.argmax(letter_probs[0]))
        haraka_index = int(np.argmax(haraka_probs[0]))
        print(json.dumps({
            "success": True,
            "predicted_letter": letter_labels[letter_index],
            "predicted_haraka": haraka_labels[haraka_index],
            "letter_confidence": round(float(letter_probs[0][letter_index]), 4),
            "haraka_confidence": round(float(haraka_probs[0][haraka_index]), 4),
            "model_version": MODEL_VERSION,
        }))
    except Exception as exc:  # noqa: BLE001 — everything becomes JSON
        fail(f"Prediction error: {exc}")


if __name__ == "__main__":
    main()
