# ADR-009: Student data retention

## Context

S1.3 introduces a consent ledger (`consents`) and needs a written retention
policy for when a student leaves (withdrawn / transferred / graduated). Spec
S1.3 asked for ADR-002; that number is already used for analytics-in-settings.
This is the retention ADR.

`photo_media_use` will gate gallery/website use of student photos (Website
consumes the latest granted row; no Website change in S1). `ai_training_samples`
pre-builds spec §51.17 and stays off until the Pronunciation phase.

Wallet/ledger and Hifz session history are out of scope here (Commerce / Hifz
frozen).

## Decision

1. **Consent is a ledger.** Changing granted/revoked always inserts a new row.
   Existing `granted` values are never updated in place.

2. **When a student leaves** (`ChangeStudentStatusAction` → withdrawn /
   transferred / graduated):
   - **Immediately:** revoke `photo_media_use` and `marketing_messages` with a
     new consent row (`source=admin`) so public/gallery use stops.
   - **Immediately:** keep the student record, status history, guardians, and
     academic/finance rows (invoices, attendance once S2 exists). These stay
     for statutory reporting.
   - **After 7 years** from the leave/graduation effective date (or the
     Maldives statutory minimum if later): anonymize `national_id`, `passport`,
     `phone`, `email`, `address`, medical fields, and custom-field values;
     replace names with `Former student {id}`. Do not delete `student_status_history`.
   - **AI samples:** `ai_training_samples` granted=false is recorded on leave;
     any later Pronunciation samples keyed to that student must be dropped in
     that phase’s cleanup (not S1).

3. **Guardians** keep their own consent rows. Detaching a guardian does not
   delete consent history.

4. **Hard delete** of a student is not offered in UI. Correction = status
   change + audit row.

## Consequences

- Website/gallery must read the latest `photo_media_use` row (future slice).
- Anonymize job is not shipped in S1; the rule is the contract for S2+ jobs.
- Promotion/graduation in S1.5 writes status via `ChangeStudentStatusAction`,
  which is the hook for a later “revoke marketing/photo on leave” listener.
