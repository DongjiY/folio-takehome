# AI Guardrails And Conventions

These rules are for AI-assisted development in this repository.

## Safety And Scope

- Keep changes inside this repository.
- Do not run destructive commands unless explicitly requested (for example: `rm -rf`, `git reset --hard`).
- Do not revert unrelated local edits you did not create.
- Prefer narrow, explainable patches over broad refactors during take-home scope.

## Schema And Data Rules

- Never edit `schema.sql` for feature schema changes.
- Add reversible migration pairs in `migrations/`:
  - `NNN_description.up.sql`
  - `NNN_description.down.sql`
- Validate migration behavior with:
  - `php migrate.php up`
  - `php migrate.php down`
  - `php migrate.php status`

## Execution Rules

- Prefer Dockerized commands over host assumptions.
- Use `docker compose exec app php ...` for tests and scripts.
- Assume `docker compose up` must keep working from a clean clone.

## Testing And Verification

- Every feature change should include at least one targeted test.
- Prefer the existing split test structure (`tests/bootstrap.php` + feature files).
- Verify both:
  - Aggregate suite: `tests/test.php`
  - Directly touched feature file(s), when practical.

## Code Quality Rules

- Match existing project style and patterns before introducing new abstractions.
- Reuse helpers in `lib/bootstrap.php` where possible.
- Keep behavior changes explicit and traceable with minimal hidden side effects.
- If behavior is ambiguous, document assumptions in PR notes rather than silently guessing.

## AI Collaboration Rules

- Start by mapping current code paths before editing.
- State tradeoffs when choosing between multiple reasonable implementations.
- Prefer incremental commits that reflect decision points.
- Call out risks and known gaps explicitly (tests not added, edge cases deferred, etc.).

