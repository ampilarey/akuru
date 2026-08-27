<?php

/*
 * Arabic Module B (SPEC §51.15): the pronunciation AI is LOCAL/offline
 * (§51.9 — never a cloud speech API) and feature-flagged. The platform
 * must work fully with the flag off (rule 8): attempts are stored and
 * teachers review them by ear — AI is an accelerator, never a dependency.
 */
return [
    'pronunciation_enabled' => env('AI_PRONUNCIATION_ENABLED', false),
    'python_bin' => env('AI_PYTHON_BIN', 'python3'),
    'predict_script' => env('AI_PRONUNCIATION_PREDICT_SCRIPT', base_path('ai/pronunciation/predict.py')),
    'model_path' => env('AI_PRONUNCIATION_MODEL_PATH', base_path('ai/pronunciation/models/arabic_letter_haraka_model.h5')),
    'confidence_threshold' => env('AI_CONFIDENCE_THRESHOLD', 0.70),
];
