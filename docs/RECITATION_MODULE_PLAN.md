# Quran Recitation Practice Module — Implementation Plan ("Tarteel-like")

Status: **PLAN ONLY** — no application code exists for this yet. This document is the
implementation contract for a developer building the module phase by phase. It follows
the CLAUDE.md rules: AI only behind `Domains/Pronunciation`-style contracts and a
feature flag (rule 8), audio never leaves our servers (SPEC §51.9), one Quran dataset
(rule 11), additive migrations (rule 9), morph-map registration for every new
polymorphic column (ADR-005), and Pest tests shipping with every slice (rule 2).

---

## 1. What this repository already has (verified findings)

The plan builds on the actual codebase, not a green field. Verified on 2026-08-29:

**Stack.** Laravel 12 modular monolith (PHP 8.4), domains under `app/Domains/*`,
MySQL 8, `QUEUE_CONNECTION=database` with a worker on the server (deploy scripts call
`php artisan queue:restart`). New UI is Inertia + React; the public site is Blade.
The Vite build (`public/build`) is **committed** — deploys only `git pull` (see
STATUS §5t), so every JS change must ship a rebuilt bundle. CI is one GitHub Actions
"quality" job (pint → pest → phpstan). TEST deploys go to cPanel-style hosting via a
self-pull script — **PHP-only hosting; it cannot run a Python service**, so the
inference service needs its own VPS (see §3).

**Auth and people.** Session auth on `App\Domains\Identity\Models\User` with
spatie-style roles (`super_admin|admin|headmaster|supervisor`, teachers via
`isTeacher()`). One student record in `Domains/People` (`Student`), linked to
guardians through the `guardian_student` pivot (`ParentGuardian`). Consents already
exist: `Domains/People/Enums/ConsentType` includes `AiTrainingSamples`,
`PhotoMediaUse`, `DataProcessing` — stored per person in the People consents tables.

**Quran text (rule 11 — reuse, never duplicate).** `quran_mushafs`, `quran_pages`,
`quran_ayahs` (`text_uthmani`, `text_simple`, unique per mushaf+surah+ayah),
`quran_words` (word-level text, `word_text` + `word_text_simple`, unique
mushaf+surah+ayah+word_number) and `quran_word_positions` (x/y boxes per mushaf page —
enough to highlight words on a rendered mushaf page). Read access goes through
`App\Support\Contracts\QuranTextProviderInterface` (implemented by
`Hifz\Actions\ReadQuranTextAction`; the Hifz domain is being retired, so expect this
implementation to move — depend on the contract only).

**Recitation flow (already shipped, human-only).** The Courses engine's Quran
component (`Domains/Courses/Components/Quran`) already has:
- `QuranHifzAssignment` (student, teacher, course/offering, `academic_year_id`,
  surah + ayah range, due date, status) — teachers assign passages today at
  `/teach/assignments`.
- `QuranRecitationSubmission` (assignment, student, surah + ayah range,
  `audio_media_file_id`, mode, duration, status, reviewer fields) — students already
  submit **recorded audio**, stored through the Media domain.
- `QuranMistakeMark` (submission, surah, ayah, `word_position`, mistake type,
  severity, teacher, comment) — teachers already mark word-level mistakes by hand.
- Review queue + actions (`SubmitRecitationAction`, `ReviewRecitationAction`,
  `ListRecitationReviewQueueAction`).

**AI scaffold (already shipped, flag off).** `Domains/Pronunciation` has the pattern
this module must copy: `PronunciationPredictionInterface` (the ONE entry point),
`NullPronunciationPredictor` bound when `AI_PRONUNCIATION_ENABLED` is off,
`LocalPythonPronunciationPredictor` that shells out to a local `predict.py` and
degrades to the human queue on any failure, `AiModelVersion` bookkeeping, and
`TrainingSample` + `ExportApprovedSamplesAction` gated on the `ai_training_samples`
consent.

**i18n.** Trilingual EN/DV/AR lang files; Thaana uses the Faruma font (referenced in
the built CSS); the T1 admin editor lets staff override any Dhivehi string at runtime.
RTL-safe layout is an existing convention (`rtl:space-x-reverse` etc.).

**Consequence:** this module is mostly *an automation layer on top of an existing
manual pipeline*, not a new product. That drives the phasing in §6.

---

## 2. Product goals

1. **Assigned recitation with word-level grading.** A student recites an assigned
   surah/ayah range into a phone or laptop mic; the system transcribes, aligns
   against the true text, and marks each expected word `correct | wrong | skipped`,
   plus `extra` for inserted words.
2. **Hifz mode.** Text hidden while reciting; mistakes revealed only afterwards.
3. **Follow-along mode.** Detect which ayah is being recited and highlight the
   position live or near-live.
4. **Teacher dashboard.** Assign passages (exists), see attempts, error words, and
   progress over time. The system is a first-pass checker; **the teacher is the
   authority** — every automatic grade is a *suggestion* until a teacher confirms it.
5. **Dhivehi UI.** Thaana/RTL chrome around Arabic-script Quran text (see §8).

**Out of scope for automation (honesty clause):** tajweed scoring — madd lengths,
ghunnah, makhraj. The model transcribes words; it cannot reliably judge these. The
only automated tajweed artifact is a **"teacher should listen to this word" flag**
(low-confidence or duration-anomaly words). Anything finer is a human's job, forever
in this plan.

---

## 3. Architecture

```
Browser (student)                    Laravel app (existing)              Inference VPS (new)
┌──────────────────┐   HTTPS   ┌──────────────────────────┐   HTTPS   ┌─────────────────────┐
│ MediaRecorder     │ ───────▶ │ SubmitRecitationAction    │ ───────▶ │ FastAPI service      │
│ (webm/opus or m4a)│  upload  │ + RecitationGradeJob      │  (queue  │ faster-whisper +     │
│ playback <audio>  │          │ (database queue)          │   job    │ tarteel-ai model     │
└──────────────────┘          │ Alignment + grading (PHP) │  calls)  │ returns transcript + │
                               │ per-word results in MySQL │ ◀─────── │ word timestamps JSON │
                               └──────────────────────────┘          └─────────────────────┘
```

### 3.1 Browser recording

- **API:** `MediaRecorder` over `getUserMedia({audio: true})`. It is supported by all
  current Chrome/Edge/Firefox/Safari (Safari ≥ 14.1). No native app needed; this also
  works inside the Phase 5 Capacitor wrapper.
- **Format:** record what the browser gives us — `audio/webm;codecs=opus` on
  Chrome/Firefox, `audio/mp4` (AAC) on Safari. **Do not transcode in the browser.**
  The inference service uses ffmpeg to decode anything to 16 kHz mono WAV (Whisper's
  input) server-side. Store the original container.
- **Limits:** cap one attempt at **5 minutes / ~10 MB** client-side (an assigned range
  should be recited in chunks that size; longer ranges are split into multiple
  attempts per ayah group). Show a live level meter so children can see the mic works.
- **Chunking:** for Phases 1–3 upload **one blob per attempt** when the student stops.
  For follow-along (Phase 4) use `MediaRecorder.start(timeslice)` with ~3-second
  chunks POSTed sequentially (details in §6, Phase 4). Do not build WebSockets on the
  cPanel host; chunked HTTP + polling is the ceiling there.
- **Upload path:** the existing `SubmitRecitationAction` already accepts an
  `audio_media_file_id` via the Media domain — reuse it. Add server-side validation:
  max size, max duration, mime allowlist (`webm`, `mp4`, `m4a`, `ogg`, `wav`).

### 3.2 Inference service (new, self-hosted)

- **What:** a small Python 3.11+ **FastAPI** app on a separate VPS, exposing exactly
  one authenticated endpoint:
  `POST /transcribe` → `{ text, words: [{word, start, end, probability}], language,
  duration, model_version }`.
- **Model:** `tarteel-ai/whisper-base-ar-quran` (verified on Hugging Face,
  Apache-2.0, Whisper *base* fine-tuned on Quranic recitation; reported WER ≈ 5.7% on
  its eval set — expect worse on children, see Phase 0). Fallback for weak hardware:
  `tarteel-ai/whisper-tiny-ar-quran` (also verified, Apache-2.0).
- **Runtime:** `faster-whisper` (PyPI, v1.2.x) on **CTranslate2** (v4.8.x) with
  `compute_type="int8"` for CPU. The fine-tuned model is converted once with
  CTranslate2's Transformers converter (`ct2-transformers-converter --model
  tarteel-ai/whisper-base-ar-quran ...`) and the converted weights are stored on the
  VPS — after that the service needs no internet access.
- **Word timestamps:** `word_timestamps=True` in faster-whisper gives per-word start/end
  and probability — this powers the confidence flag and follow-along.
- **Constrained decoding:** because the expected text is known, pass it as an
  `initial_prompt` (biases decoding toward the expected ayahs). Grading still never
  trusts the transcript alone — alignment does the work (§3.3).
- **Security:** the VPS listens on HTTPS only; the Laravel app authenticates with a
  static bearer token in the `Authorization` header (config/env both sides); the
  endpoint is IP-allowlisted to the web server. Audio goes over the wire between our
  two servers only — this satisfies §51.9 (never a third-party API).
- **Sizing (start point, Phase 0 verifies):** 4 vCPU / 8 GB RAM VPS. whisper-base is
  ~74 M parameters; int8 CPU transcription typically runs around 0.3–1× real-time on
  modern cores — a 20-second ayah should take roughly 5–20 s. **Numbers are guesses
  until Phase 0 measures them; nothing real-time is promised before then.**
- **Process model:** uvicorn with 1 worker + an internal semaphore of 1–2 concurrent
  transcriptions (CPU-bound); the Laravel queue provides the buffering, so bursts
  wait in our queue rather than OOM-ing the VPS.
- **Laravel side:** a `RecitationTranscriberInterface` in the new component (same
  pattern as `PronunciationPredictionInterface`), with:
  - `NullRecitationTranscriber` — bound when the flag is off; everything stays
    human-only exactly as today.
  - `HttpRecitationTranscriber` — calls the VPS, 120 s timeout, any failure returns
    an error result and the attempt falls back to the manual review queue (never
    crashes, never blocks the student).

### 3.3 Alignment and grading (PHP, inside the Laravel app)

Grading is **not** "compare transcript to text". It is alignment of two word
sequences: the *expected* words (from `quran_words` for the assigned range) and the
*hypothesis* words (from the transcript), after both pass the same normalizer.

- **Normalization (apply to both sides before comparing):**
  - strip all tashkeel/diacritics (U+064B–U+065F, U+0670) and tatweel (U+0640);
  - strip Quran-specific annotation marks (U+06D6–U+06ED: sajdah, waqf signs, etc.);
  - unify alif forms: أ إ آ ٱ → ا;
  - unify hamza seats: ؤ → و, ئ → ي, standalone ء kept;
  - taa marbuta ة → ه;
  - alif maqsurah ى → ي;
  - remove non-Arabic characters and collapse whitespace.
  This is a pure PHP class (`ArabicNormalizer`) with a Pest test-table of examples —
  it is the single most test-worthy unit in the module. `quran_words.word_text_simple`
  already stores a simplified form; the normalizer output should be validated against
  it during import, and the normalized expected words can be cached in a column.
- **Alignment:** Needleman–Wunsch / Levenshtein alignment over the two word arrays
  (dynamic programming, ~50×60 matrix per ayah — trivial in PHP). Substitution cost
  uses **character-level similarity** of the normalized words (Levenshtein ratio), so
  a near-miss (one letter off) is distinguishable from a different word.
- **Per-word verdicts:**
  - aligned + similarity ≥ `correct_threshold` → **correct**
  - aligned + similarity ≥ `partial_threshold` → **borderline** → shown as correct to
    the student (forgiving), flagged "listen to this word" for the teacher
  - aligned + similarity below `partial_threshold` → **wrong (suggested)**
  - expected word with no aligned hypothesis word → **skipped (suggested)**
  - hypothesis word with no expected match → **extra** (recorded, never penalized on
    its own — children repeat words; repetition of the previous word is auto-ignored)
- **Confidence gate:** any word whose Whisper probability < `confidence_floor` is
  never marked wrong — it becomes "teacher should listen" instead. Low-confidence
  audio (overall avg probability < floor, or transcript < 50% of expected length)
  fails soft: "We couldn't hear this well — try again?" with unlimited retries and
  **no recorded failure**.
- **Tunable thresholds per age group (children constraint):** a
  `recitation_grading_profiles` table (name, min_age, max_age, correct_threshold,
  partial_threshold, confidence_floor, max_flagged_ratio) seeded with `child`
  (forgiving) / `teen` / `adult` rows; the grader picks a profile from the student's
  date of birth, teachers can pin a profile per student. Thresholds are data, not
  code — retuning after Phase 0 measurements needs no deploy.
- **Never auto-fail:** the automatic result sets `suggested_*` fields only. Attempt
  status goes to `auto_graded`, surfaced to the student as *provisional* ("checked by
  computer — your teacher confirms"). Teacher confirmation (existing
  `ReviewRecitationAction` flow) produces the final grade; a teacher can flip any
  word with one tap, and those flips are stored (they are future tuning data).

### 3.4 Queue vs synchronous

- **Phases 1–3: queued.** `SubmitRecitationAction` stores the attempt and dispatches
  `GradeRecitationJob` on the existing database queue (dedicated `recitation` queue
  name so slow transcriptions never delay SMS/notifications; run a second worker for
  it). The student's page polls the attempt status every 3 s (or the existing
  Inertia partial-reload pattern) — results typically appear in well under a minute.
  Retries: 2, exponential backoff; terminal failure → `needs_manual_review`, teacher
  queue, student sees "sent to your teacher".
- **Phase 4 only: near-real-time chunks**, and only if Phase 0's latency numbers pass
  the bar (§6 Phase 0). Follow-along runs transcription per 3–5 s chunk with
  `initial_prompt` biasing plus alignment into the expected range to locate the ayah.

---

## 4. Data model (all additive; morph-map aliases registered same slice)

Reused as-is: `QuranHifzAssignment`, `QuranRecitationSubmission` (+ its media file),
`QuranMistakeMark` (stays the *teacher's* word-level mark), `quran_ayahs`,
`quran_words`, consents.

New tables (in the Quran component's migrations):

| Table | Purpose | Key columns |
|---|---|---|
| `recitation_grading_profiles` | tunable thresholds per age group | name, min_age, max_age, correct_threshold, partial_threshold, confidence_floor, is_default |
| `recitation_auto_results` | one row per auto-graded attempt | quran_recitation_submission_id (unique), model_version, transcript_raw, transcript_normalized, avg_confidence, wer, status (`suggested`/`confirmed`/`rejected`), grading_profile_id, latency_ms, timestamps |
| `recitation_word_results` | one row per expected word per attempt | recitation_auto_result_id, surah_number, ayah_number, word_number, expected_text, heard_text nullable, similarity, confidence, verdict (`correct/borderline/wrong/skipped`), needs_listen bool, start_ms/end_ms nullable, teacher_verdict nullable |
| `recitation_extra_words` | inserted words (not penalized) | recitation_auto_result_id, after_word ref, heard_text, confidence, start_ms/end_ms |
| `recitation_progress_aggregates` | per student × surah rollup, recomputed on confirm | student_id, academic_year_id, surah_id, ayah_from, ayah_to, attempts_count, best_accuracy, last_accuracy, mastered_at nullable, updated_at |

Notes:
- `recitation_word_results` is the biggest table (≈ range-length rows per attempt).
  With word verdicts stored per attempt, "error words over time" is one indexed query
  (`student_id` via join, surah/ayah/word, verdict).
- Rule 10: attempts inherit `academic_year_id` from the submission (already there);
  aggregates carry it explicitly.
- Audio retention fields go on the **existing** submission row:
  `audio_retention` (`delete_after_grading` default / `keep_for_review` /
  `keep_for_training`), `audio_deleted_at`. See §7.
- Quran text source: the tables exist but which mushaf edition fills them is an
  **owner decision** (§9). Recommended: Tanzil.net Uthmani text (tanzil.net/download,
  free, the de-facto standard digital text; verified reachable) imported once into a
  `quran_mushafs` row, words split on whitespace into `quran_words`. If word-by-word
  data with page layout is wanted later, Tarteel's QUL (qul.tarteel.ai, verified) has
  downloadable scripts/layouts — evaluate licensing per dataset at that point.

---

## 5. How the pieces talk to the existing app

- New code lives in `app/Domains/Courses/Components/Quran/…` (it is Quran-subject
  behavior — the engine core stays subject-ignorant, rule 6) plus one new
  `RecitationTranscriberInterface` + implementations. Components never import other
  components or engine models directly (rule 3) — everything reaches the engine
  through the existing submission/assignment models that already live in this
  component, and Quran text through `QuranTextProviderInterface`.
- Feature flag: `RECITATION_AI_ENABLED` (config `recitation.ai_enabled`, default
  **off**) → container binds Null vs Http transcriber, exactly like
  `AI_PRONUNCIATION_ENABLED`. With the flag off, everything built in Phase 1 keeps
  working human-only.
- UI: Inertia pages under the existing `/learn` (student) and `/teach` (teacher)
  areas; **every JS change commits a fresh `npm run build`** (STATUS §5t rule).

---

## 6. Phased roadmap

Effort estimates assume one developer familiar with the codebase; "week" = focused
week. Every phase ends green (pint + pest + arch), walked in a browser, STATUS.md
updated (Definition of Done).

### Phase 0 — Benchmark / proof of concept (go/no-go gate) — ~1 week
Nothing ships to users; this de-risks everything after it.
1. Provision the target CPU VPS (owner decision §9; benchmark on the real spec).
2. Convert `tarteel-ai/whisper-base-ar-quran` (and `-tiny-` for comparison) with
   CTranslate2; stand up the FastAPI service skeleton.
3. Assemble **20 sample recitations**: ~10 adult (use everyayah.com reciters plus
   staff recordings), ~10 **children from our own community** (staff/teachers'
   children with parent consent, recorded on real phones — this is the population
   that matters and no public dataset covers Maldivian children).
4. Measure per ayah: transcription latency (p50/p95), word error rate after
   normalization vs the true text, per-word probability distributions.
5. **Go/no-go bars:**
   - *Batch grading (Phases 2–3) GO if:* p95 latency ≤ 3× audio duration AND
     normalized WER ≤ 15% adults / ≤ 30% children AND ≥ 90% of *correctly recited*
     words align as correct/borderline with draft thresholds (i.e. false-accusation
     rate ≤ 10%, the child-fairness number).
   - *Follow-along (Phase 4) GO only if:* a 3–5 s chunk transcribes in ≤ 2 s p95 on
     the same hardware (else Phase 4 waits for a GPU or stays off).
   - *No-go:* if children's false-accusation rate can't get under ~10% by loosening
     thresholds, ship Phase 1 only (record + teacher grades) and revisit models.
6. Deliverable: `docs/RECITATION_BENCHMARK.md` with the numbers, chosen model size,
   chosen starting thresholds per age profile. Captured in STATUS.md (merge-gate
   discipline: a gate whose evidence isn't recorded has not run).

### Phase 1 — Record & playback + teacher manual grading — ~1–2 weeks
Usable alone; also the fallback if AI is ever down or no-go.
- Student page for an assignment: big record button (MediaRecorder), level meter,
  re-record before submit, playback, submit → existing `SubmitRecitationAction`.
- Teacher review page upgrade: play audio, tap words in the rendered ayah text to
  mark mistakes (writes `QuranMistakeMark` rows — model exists), confirm grade.
- Retention default wired in: audio auto-deleted after teacher confirmation unless
  consent + teacher chose to keep (§7).
- *Acceptance:* a student records on a phone (Safari + Chrome tested), the teacher
  hears it, taps two wrong words, confirms; audio file is gone after confirmation
  (default path); all in Dhivehi UI with Arabic text rendering correctly RTL.

### Phase 2 — Automatic word-level grading of assigned ayahs — ~2–3 weeks
The core value. Ships behind `RECITATION_AI_ENABLED`.
- Inference service productionized (systemd unit, token auth, health endpoint,
  deploy doc `docs/RECITATION_INFERENCE_DEPLOY.md`).
- `ArabicNormalizer` + `AlignRecitationAction` (pure PHP, heavy Pest tables: hamza
  forms, alif variants, taa marbuta, skipped word, extra word, repeated word,
  borderline similarity).
- `GradeRecitationJob` + `recitation_auto_results`/`recitation_word_results` +
  grading profiles; student result view (green/amber/red words, provisional label);
  teacher review shows suggestions pre-loaded — teacher confirms or flips words,
  confirm writes final verdicts and (optionally) `QuranMistakeMark` rows so the old
  reports keep working.
- *Acceptance:* student submits Al-Fatiha with one deliberately wrong + one skipped
  word → auto result within 60 s marks exactly those words (given Phase-0-calibrated
  audio); teacher flips one verdict and confirms; flag off → Phase 1 behavior
  untouched; inference VPS down → attempt lands in manual queue with no student error.

### Phase 3 — Hifz (memorization) mode — ~1 week
- Same recording flow with text hidden (mode column already exists on submissions);
  optional first-word peek button (counted, shown to teacher); mistakes revealed
  only on the result screen; hifz attempts feed the same grading path with the
  student's profile thresholds.
- *Acceptance:* text never visible while recording in hifz mode; result reveals the
  passage with mistake words highlighted; peeks recorded.

### Phase 4 — Follow-along mode — ~2–3 weeks, **conditional on Phase 0 GO**
- Chunked MediaRecorder upload (3–5 s), per-chunk transcription with expected-range
  prompt, alignment locates current ayah/word, response highlights position; client
  merges chunk results; a "lost tracking" state falls back gracefully to manual
  page-turning. No sockets — sequential chunk POSTs; if the cPanel host can't take
  the request rate, chunks go directly to the inference VPS (signed short-lived
  token) which pushes positions back to Laravel.
- *Acceptance:* reciting Al-Fatiha steadily, the highlight is on the correct ayah
  within ~5 s of crossing into it, on a mid-range Android phone over 4G.

### Phase 5 — Progress analytics — ~1–2 weeks
- `recitation_progress_aggregates` recompute-on-confirm; teacher dashboard: per
  student accuracy trend, most-missed words (cross-attempt word_results query),
  passage mastery board (reuse `ListQuranMilestoneBoardAction` patterns); student
  view: streaks, mastered ranges; CSV export (house rule: every listing gets one).
- *Acceptance:* teacher sees a student's last 10 attempts as a trend, the top-5
  error words with counts, and exports CSV; numbers reconcile with raw attempts.

---

## 7. Privacy, consent, retention (children first)

- **Default: process then delete.** `audio_retention = delete_after_grading` on every
  attempt unless elevated. Deletion = the Media-domain file is removed after (a) auto
  grading completes AND (b) teacher confirms, or 7 days after submission, whichever
  first (teacher may not get to it; grades keep the word rows, audio goes). A daily
  scheduled command enforces this and stamps `audio_deleted_at`; a Pest test proves
  expired audio is gone.
- **Keeping audio for teacher review** (beyond the 7-day window) requires an active
  **`RecitationAudioReview` consent** (new `ConsentType` case) from the
  parent/guardian for minors (self for adults), stored in the existing People
  consents tables like `prayer_reminders` is today, with granted_by, timestamp, and
  revocation. Revocation triggers deletion of retained audio within 24 h.
- **Keeping audio for future fine-tuning** requires the **existing
  `AiTrainingSamples` consent** (already in the enum, already wired to
  `TrainingSample` + `ExportApprovedSamplesAction` in the Pronunciation domain) —
  reuse that exact pipeline; recitation clips become training samples only through
  explicit approval, never by default.
- Transcripts and per-word results (text, no audio) are grade records and are kept —
  the consent language must say so plainly.
- Consent wording (Dhivehi + English) is an **owner decision** (§9); the plan only
  fixes *where* it lives and *what it gates*.
- All audio stays on our two servers (web + inference VPS); the inference VPS keeps
  audio only in a tmp dir wiped after each request and holds no database.

---

## 8. Dhivehi / RTL / fonts

- UI chrome in Dhivehi (Thaana, RTL) via the existing `learn` lang group + T1
  override editor; every new string added to en/dv/ar in the same slice.
- Quran text rendered from `quran_ayahs.text_uthmani` in an Arabic Quran font —
  ship a self-hosted **KFGQPC Uthmanic Script HAFS** (or Amiri Quran as fallback)
  woff2 in the repo (fonts are self-hosted like Faruma already is; verify license
  file included). Never render Quran text in the Thaana font stack.
- Mixed direction: both Thaana and Arabic are RTL, so pages are `dir="rtl"`
  throughout; the only LTR islands are numbers/latin (auto-handled by the bidi
  algorithm). Word-highlight spans must wrap on word boundaries (`display:inline`,
  no letter-spacing — Arabic joining breaks otherwise). Verdict colors need a
  non-color cue too (underline style) for color-blind children.
- Tashkeel stays visible in the displayed text (students read with harakat) even
  though grading strips it.

---

## 9. Risks and open questions (owner decides)

| # | Question | Recommendation |
|---|---|---|
| 1 | **Inference hosting spec & budget** — which VPS (4 vCPU/8 GB start?), where (latency to MV users is irrelevant for queued grading; any region works), monthly cost ceiling? GPU later? | Start 4 vCPU/8 GB CPU; decide GPU only if Phase 4 is wanted and Phase 0 says CPU can't do it |
| 2 | **Mushaf text edition** to import into `quran_mushafs` — Tanzil Uthmani (recommended), or QPC/Madinah layout via QUL for page-true rendering? | Tanzil Uthmani first; page layout later if mushaf-page UI is wanted |
| 3 | **Consent wording** (DV/EN) for `RecitationAudioReview` and reuse of `AiTrainingSamples` — needs guardian-facing language a parent actually understands | Draft with a scholar + a parent; keep under 100 words each |
| 4 | **Age-profile boundaries** (child/teen/adult cut-offs) and whether teachers may loosen but not tighten | Teachers may loosen only |
| 5 | **Class structure**: assignments today are per student (`QuranHifzAssignment`); is per-class bulk assignment needed in Phase 1 or later? | Later; bulk = loop in an action |
| 6 | **Retention window** — is 7 days right for unreviewed audio? | 7 days unless teachers say review latency is worse |
| 7 | **Risk: children's WER** may stay high even after threshold tuning → module ships as Phase 1 + "teacher listens" with AI suggestions only for teens/adults | Accepted fallback; Phase 0 decides |
| 8 | **Risk: queue worker capacity** on shared hosting — one slow transcription queue must not starve SMS | Separate `recitation` queue + second worker; verify in Phase 2 |
| 9 | **Risk: model bias to Hafs/professional recitation** — Tarteel models are trained on adult recitation in Hafs; other qira'at are out of scope | State Hafs-only in the UI |
| 10 | **Risk: Whisper hallucination on silence/noise** — mitigated by VAD (faster-whisper `vad_filter=True`), length ratio check, and the soft-fail retry path | Built into §3.3 |

---

## 10. Verified external resources (checked 2026-08-29)

- `tarteel-ai/whisper-base-ar-quran` — Hugging Face, Apache-2.0, ASR (verified via HF API)
- `tarteel-ai/whisper-tiny-ar-quran` — Hugging Face, Apache-2.0 (verified)
- `tarteel-ai/everyayah` — Hugging Face dataset of recitation audio (verified; adult reciters)
- `faster-whisper` 1.2.x — PyPI (verified); `ctranslate2` 4.8.x — PyPI (verified)
- `rapidfuzz` 3.x — PyPI (verified; optional fast similarity in the Python service —
  the PHP grader uses `levenshtein()`/`similar_text()` which are built in)
- tanzil.net/download (Quran text), everyayah.com (audio), qul.tarteel.ai (QUL) — all reachable
