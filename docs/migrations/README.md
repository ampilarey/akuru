# Migration / verify archives

Operator copies live-data verification artifacts here. Do not invent
counts or JSON from local `akuru_institute` / `akuru_test`.

## Student unification (S1.1b / S2.0 gate)

After a gated staging deploy (`scripts/pull-deploy-test.sh`), copy:

```text
~/test.akuru.edu.mv/storage/app/s11b-student-unification-report.json
```

to:

```text
docs/migrations/s11b-student-unification-report.json
```

Also paste the verbatim `php artisan students:verify-unification`
stdout into `STATUS.md` (same format as the morph-map capture).

Zero unresolved = Deploy 2 / student-keyed-write gate satisfied.
Nonzero = list affected rows and stop (do not start S3).

## Production-data copy (A3 / S1_SPEC line 147)

Staging synthetics cannot validate Deploy 2. Restore a **production**
mysqldump onto a scratch schema and run `students:verify-unification`
there.

Full procedure: [`restore-production-copy.md`](restore-production-copy.md).

**Never** run `--backfill` against production. The artisan command refuses
that flag when `APP_ENV=production`.
