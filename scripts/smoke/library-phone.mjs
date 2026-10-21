/**
 * Does every page a Library writer or an adult reader opens work on a phone,
 * in Dhivehi and Arabic? (The writer audit, LW1, STATUS §5ud.)
 *
 * The household walk (`household.mjs`) as the walks' writer (made a writer
 * by `library.mjs`, which runs before this in `all.mjs`) and the seeded adult
 * learner: every link their shell offers, under /dv and /ar at 390 px.
 *
 *   node scripts/smoke/library-phone.mjs
 *
 * Environment: SMOKE_APPLICANT, SMOKE_LEARNER, and household.mjs's own.
 */
process.env.SMOKE_PARENT = process.env.SMOKE_APPLICANT ?? 'smoke-writer@akuru.edu.mv';
process.env.SMOKE_STUDENT = process.env.SMOKE_LEARNER ?? 'smoke-learner@akuru.edu.mv';
process.env.SMOKE_MIN_PAGES ??= '10';
await import('./household.mjs');
