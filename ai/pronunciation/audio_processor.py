"""Audio preprocessing for the Arabic pronunciation model (SPEC 51.11).

Loads mono 16 kHz audio, normalizes, pads/truncates to exactly 1 second,
and returns fixed-shape MFCC input for the CNN. Browser webm audio needs
ffmpeg available on PATH so librosa/audioread can decode it.
"""

from __future__ import annotations

SAMPLE_RATE = 16000
CLIP_SECONDS = 1.0
N_MFCC = 40


def load_features(audio_path: str):
    """Return an MFCC array shaped (N_MFCC, frames, 1) for one clip."""
    import librosa
    import numpy as np

    try:
        signal, _ = librosa.load(audio_path, sr=SAMPLE_RATE, mono=True)
    except Exception as exc:  # decoding failure (missing ffmpeg for webm, corrupt file)
        raise RuntimeError(f"Could not decode audio: {exc}") from exc

    peak = np.max(np.abs(signal)) if signal.size else 0.0
    if peak > 0:
        signal = signal / peak

    target = int(SAMPLE_RATE * CLIP_SECONDS)
    if signal.size < target:
        signal = np.pad(signal, (0, target - signal.size))
    else:
        signal = signal[:target]

    mfcc = librosa.feature.mfcc(y=signal, sr=SAMPLE_RATE, n_mfcc=N_MFCC)
    return mfcc[..., np.newaxis]
