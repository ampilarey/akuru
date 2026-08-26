# AGENTS.md

Project-level rules for AI coding sessions live in `.cursorrules` / `CLAUDE.md` (the
12 architecture rules and the slice discipline). Read those first. This file adds
environment/runtime notes.

## Cursor Cloud specific instructions

Stack: Laravel 12 + Inertia/React (Vite), PHP 8.4, MySQL 8, Composer, npm.
Dependencies are refreshed automatically on VM startup (`composer install` + `npm install`).

### Database (required for the app and the test suite)

- MySQL is installed but the service is not auto-started. Start it each session:
  `sudo service mysql start`
- `root@127.0.0.1` has an **empty password** (this is what `phpunit.xml` hardcodes:
  `mysql`, `127.0.0.1:3306`, db `akuru_test`, user `root`, empty password).
- Two databases exist: `akuru_test` (used by Pest/PHPUnit) and `akuru_institute`
  (local dev, referenced by `.env`).
- `.env.example` ships `DB_CONNECTION=sqlite`, but the app is actually run and tested
  against **MySQL**. The local `.env` is configured for MySQL (`DB_DATABASE=akuru_institute`).
  After creating/refreshing `.env`, run `php artisan migrate --seed`.

### Run (development)

- `composer dev` runs everything concurrently (server :8000, `queue:listen`,
  `pail` logs, and Vite). Defined in `composer.json`.
- To run pieces individually: `php artisan serve`, `npm run dev` (Vite :5173),
  `php artisan queue:listen --tries=1`.

### Lint / test / build

- Lint (CI-blocking): `./vendor/bin/pint --test`
- Tests (CI-blocking, includes the `tests/Architecture` suite): `./vendor/bin/pest`
  — requires MySQL running with the `akuru_test` database.
- PHPStan is informational only (non-blocking): `./vendor/bin/phpstan analyse --memory-limit=512M`
- Production asset build: `npm run build` (outputs to `public/build/`, which is tracked in git).

### Seeded logins

`php artisan migrate --seed` creates users, all with password `password`, e.g.
`admin@akuru.edu.mv` (admin), `teacher@akuru.edu.mv`, `student@akuru.edu.mv`.
Password login looks up a **verified `user_contacts` email**, not `users.email`.
`UserSeeder` writes those contacts. See `docs/AUTHENTICATION_GUIDE.md`.

### Deployment

Staging (`test.akuru.edu.mv`) deploy details and the GitHub Actions auto-deploy
workflow are documented in `docs/STAGING.md`. Never use `php artisan route:cache`
— it breaks the mcamara localized routes; use `route:clear` instead.
