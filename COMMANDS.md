# Commands

Common commands for local development and AI-assisted verification.

## Start The App

```bash
docker compose up
```

Open [http://localhost:8000](http://localhost:8000).

## Run Test Suite

```bash
docker compose exec app php tests/test.php
```

## Run A Single Test File

```bash
docker compose exec app php tests/time_based_access_test.php
docker compose exec app php tests/document_search_test.php
docker compose exec app php tests/readable_share_id_test.php
docker compose exec app php tests/migration_test.php
```

## Migration Commands

```bash
docker compose exec app php migrate.php up
docker compose exec app php migrate.php down
docker compose exec app php migrate.php down 2
docker compose exec app php migrate.php status
```

## Re-Seed Local Database

Use this when you want a deterministic fresh state without rebuilding everything.

```bash
docker compose exec app php seed.php
```

## Quick Manual Smoke Checks

1. Start app with `docker compose up`.
2. Visit `/admin.php` and create a document.
3. Create a share link for that document.
4. Open the recipient link in `/view.php?...`.
5. If you changed scheduling logic, verify:
   - before available time: "not yet available" behavior
   - at/after available time: document is visible.

## Optional Shell Convenience Aliases

```bash
alias dphp='docker compose exec app php'
alias dtest='docker compose exec app php tests/test.php'
```

