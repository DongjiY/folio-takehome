# AI Context For Folio Take-Home

This file gives AI assistants the minimum context needed to work safely and quickly in this repo.

## Project Snapshot

- Stack: PHP 8 + SQLite + Docker Compose.
- App entrypoints:
  - Staff/admin flow: `public/admin.php`
  - Share creation: `public/share.php`
  - Recipient view: `public/view.php`
- Shared helpers and DB access: `lib/bootstrap.php`
- Schema baseline: `schema.sql`
- Reversible migrations: `migrate.php` + `migrations/*.up.sql` + `migrations/*.down.sql`
- Test harness:
  - Aggregate runner: `tests/test.php`
  - Shared test setup: `tests/bootstrap.php`
  - Feature tests: `tests/*_test.php`

## Runtime Model

- Run app with `docker compose up`.
- Startup runs `seed.php`, which:
  - recreates `db.sqlite`
  - loads `schema.sql`
  - applies pending up migrations via `migrate_up($pdo)`
- This means each fresh startup begins from a deterministic local state.

## Repo-Specific Expectations

- Do schema changes by adding migration files, not by directly editing `schema.sql`.
- Keep migration pairs reversible (`NNN_name.up.sql` + `NNN_name.down.sql`).
- Add or update tests for each feature change.
- Prefer small, incremental commits that preserve a clear story of decisions.
- Preserve compatibility with a fresh-clone reviewer flow (`docker compose up` should still work).

## Fast Orientation By Task

- Audit logging behavior: start in `lib/bootstrap.php`; call sites in `public/admin.php` and share creation flow.
- Time-gated sharing behavior: `public/share.php` and `public/view.php`.
- Search/share UX changes: `public/admin.php` + helper functions in `lib/bootstrap.php`.
- Migration behavior: `migrate.php`, `seed.php`, and `migrations/`.
- Test updates: mirror existing split pattern in `tests/`.

## Definition Of Done (For AI-Assisted Changes)

1. Code compiles/runs in container.
2. Relevant tests pass.
3. Manual smoke checks pass for touched user flow.
4. Migration direction (`up` and, if applicable, `down`) is validated.
5. Notes include tradeoffs, risks, and what was intentionally not changed.

