/**
 * Does every page a teacher or the headmaster opens work on a phone, in
 * Dhivehi and Arabic? (The school audit, TD1, STATUS §5ub.)
 *
 * The household walk (`household.mjs`) as the seeded teacher and headmaster:
 * every link their shell offers, under /dv and /ar at 390 px — it answers,
 * reads right to left, scrolls nothing sideways, raises no error, stacks its
 * tables (a grid that scrolls in its own box is named `data-phone-scroll`),
 * and gives no control under 24 px to a thumb.
 *
 *   node scripts/smoke/staff-phone.mjs
 *
 * Environment: SMOKE_TEACHER, SMOKE_HEADMASTER, and household.mjs's own.
 */
process.env.SMOKE_PARENT = process.env.SMOKE_TEACHER ?? 'teacher@akuru.edu.mv';
process.env.SMOKE_STUDENT = process.env.SMOKE_HEADMASTER ?? 'headmaster@akuru.edu.mv';
await import('./household.mjs');
