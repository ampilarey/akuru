# Arabic Pronunciation AI (SPEC §51.9–§51.11)

Local/offline isolated letter + haraka checker. **Never** a cloud speech
API — everything here runs on the server (or a training machine) and is
called by Laravel only through `Domains/Pronunciation` (§51.14).

## Layout (§51.10)

```
ai/pronunciation/
  dataset/<letter>/<haraka>/*.wav      # raw collected clips (by key_name)
  approved_training_samples/           # manifests exported by Laravel
  models/
    arabic_letter_haraka_model.h5
    letter_labels.json
    haraka_labels.json
  audio_processor.py                   # 16 kHz mono → MFCC (1 s window)
  model.py                             # multi-output CNN definition
  train.py                             # batch training from a manifest
  predict.py                           # CLI: audio path → JSON only
  export_training_samples.py           # copy manifest audio into dataset/
  requirements.txt
```

## Setup

```bash
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt
# browser webm audio needs ffmpeg on PATH for librosa/soundfile decoding
```

## Laravel wiring (§51.15)

```
AI_PRONUNCIATION_ENABLED=false   # the feature flag — platform runs fully with it off
AI_PYTHON_BIN=python3
AI_PRONUNCIATION_PREDICT_SCRIPT=/path/to/ai/pronunciation/predict.py
AI_PRONUNCIATION_MODEL_PATH=/path/to/ai/pronunciation/models/arabic_letter_haraka_model.h5
AI_CONFIDENCE_THRESHOLD=0.70
```

## Human-in-the-loop (§51.16)

Teachers verify recordings in the app; admin approves samples; Laravel's
"Export approved samples" writes a JSON manifest under
`storage/app/ai/pronunciation/approved_training_samples/`. Run
`train.py --manifest <path>` to train, then register the produced model
file as a NEW version in the admin screen and activate it there (old
versions are kept; activation/rollback is audited).
