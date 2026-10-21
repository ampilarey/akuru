/**
 * Does every page a shop owner or the Institute's office opens work on a
 * phone, in Dhivehi and Arabic? (The office audit, AD1, STATUS §5uc.)
 *
 * The household walk (`household.mjs`) as the seeded vendor and super admin:
 * every link their shell offers, under /dv and /ar at 390 px — it answers,
 * reads right to left, scrolls nothing sideways, raises no error, stacks its
 * tables, and gives no control under 24 px to a thumb. A shop owner's menu is
 * short, so ten pages suffice.
 *
 *   node scripts/smoke/office-phone.mjs
 *
 * Environment: SMOKE_VENDOR, SMOKE_SUPER_ADMIN, and household.mjs's own.
 */
process.env.SMOKE_PARENT = process.env.SMOKE_VENDOR ?? 'vendor@akuru.edu.mv';
process.env.SMOKE_STUDENT = process.env.SMOKE_SUPER_ADMIN ?? 'superadmin@akuru.edu.mv';
process.env.SMOKE_MIN_PAGES ??= '10';
await import('./household.mjs');
