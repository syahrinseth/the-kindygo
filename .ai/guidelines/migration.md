# Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

Use this as a checklist and playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

## Goals / success criteria

- Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
- Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
- Minimize downtime and provide a clear rollback strategy.
- Keep the codebase ready for an easier upgrade to Filament v4.
- Delete all non-prefix tables
- Tenant data has prefix 1_*.
- Implement table rename to proper plural to meet Laravel's magic requirements.
- Update and rename Foreign Keys to meet new naming changes.

## Contract (inputs / outputs / error modes)

- Inputs:
  - Current codebase & migrations (legacy project).
  - Database dumps (central and per-tenant if multi-db tenancy).
  - Knowledge of tenancy package used (e.g., stancl/tenancy, tenancy/tenancy, custom).
- Outputs:
  - New migrations that move/rename/transform tables/columns safely.
  - Updated Eloquent models, factories, Filament resources.
  - Small test-suite proving migration success for both central and tenant data.
- Error modes / failure cases:
  - Missing or incomplete backups (Mitigation: stop and require full backups).
  - Data loss from destructive changes (Mitigation: use copy-and-swap migrations, staging verification, and chunked backfills).

## Assumptions

- You can run migrations in a staging environment first.
- You have SQL backups for central and tenant DBs and can restore them.
- The tenancy topology (single DB with tenant_id vs multi-DB per tenant) is known or detectable.

## High-level plan

1. Inventory & backups
2. Map schema differences and design non-destructive migrations
3. Implement reversible migration files + backfill jobs
4. Add an artisan runner (or use tenancy package helpers) to run tenant migrations
5. Update models, Filament resources, Livewire components
6. Write tests (Pest) and run verification
7. Staged rollout (staging → canary tenants → production)

---

## 1) Inventory & backups

- Export schema and a small data sample for every table (central + tenants).
- Identify tenancy pattern:
  - Single DB with tenant_id columns? — then migrations apply globally but must be tenant-aware.
  - Multi-DB per tenant? — then migrations must be executed per-connection (per-tenant).
- Take full backups for central and each tenant DB. Verify restores in staging.
- Create a mapping doc: old_table => new_table (or keep), old_column => new_column, cast/type changes.

## 2) Schema mapping & non-destructive prototyping

- Prefer add-column/copy-backfill/drop-old (copy-and-swap) rather than in-place destructive changes.
- For each change record:
  - Reason for change, backward-compatibility plan (dual-read/dual-write window), and backfill approach.
- Handle special cases explicitly: pivot tables, polymorphic relations, JSON columns, enum replacements.

## 3) Reversible migrations and backfills

- Migration A (add): add the new column/table (nullable) and keep old field.
- Backfill job: a chunked queued job or artisan command that copies existing values into new columns.
- Dual-write deployment: update app code to write to both old and new columns until cutover.
- Migration B (swap): after verification, make new column NOT NULL and drop the old column in a reversible migration.

Notes:

- Use transactions where possible but avoid long-running locks on large tables — prefer chunked background jobs.
- For huge tables, backfill with indexed filters or add temporary indexing to speed up copy jobs.

## 4) Tenant-aware execution

- If your tenancy package provides a helper to run migrations across tenants, use it (safer and battle-tested).
- Otherwise implement an artisan command like `tenants:migrate` that iterates tenant connections and runs migrations and backfill jobs per-tenant. Log progress and failures.

Example (pseudocode):

- For each tenant:
  - Switch DB connection to tenant
  - Run `php artisan migrate --path=database/migrations/tenants` (or run specific migrations)
  - Dispatch backfill jobs scoped to the tenant

## 5) Code updates for Filament v3

- Models:
  - Update casts via `casts()` method (Laravel 12 style) and add new accessors/mutators for compatibility.
  - Implement Filament-related contracts (e.g., FilamentUser) if required.
- Filament resources:
  - Resources live in `app/Filament/Resources/YourModelResource`.
  - Update forms/tables to use the new fields and relationships.
- Livewire / Frontend:
  - If using Livewire v3, adapt `wire:model.live` as needed and confirm component namespaces.

## 6) Tests & verification

- Write Pest tests covering:
  - Migration happy path: migration + backfill produces expected schema & sample rows migrated.
  - Filament resource smoke test: Livewire test for list/create/edit flows against migrated model.
  - Tenant runner: run a test tenant migration on a fixture DB.
- Quality gates:
  - Build: app boots with new migrations applied in staging.
  - Lint/Format: run `vendor/bin/pint` and fix issues.
  - Tests: pass targeted Pest tests.

Commands to try locally (examples):

```zsh
vendor/bin/pint
php artisan test --filter=YourMigrationTest
php artisan migrate --path=database/migrations/2025_xxx_add_new_columns.php --seed
```

## 7) Staged rollout & monitoring

- Staging: run full migration on staging with production-like data and run tests and manual QA.
- Canary tenants: pick a small subset of tenants for initial production run.
- Production: perform migration during a maintenance window if final cutover is disruptive.
- Monitoring: after migration run checksums, row counts, sample record comparisons, and error log monitoring.

## Rollback plan

- Keep old columns in place until you verify migration results. That allows quick rollback: revert code to use old columns and drop any partial new indexes.
- For destructive operations, ensure a tested restore-from-backup runbook is available.

## Filament v4 considerations (prepare now)

- Keep business logic out of Filament resources; keep it in models/services so UI changes are isolated.
- Avoid tight coupling to Filament helpers/APIs that may change between v3 → v4.
- Add small compatibility shims where necessary rather than broad, risky refactors.

## Practical tips & gotchas

- Large tables: chunk backfills and consider temporary indexes.
- Foreign keys: plan FK changes carefully (drop/recreate order) and verify constraints after migration.
- Enums: prefer PHP Enums in `app/Enums` and keep numeric values stable.

## This discussion (summary)

I scanned the repository migrations and we agreed on a safe, copy-and-swap approach for schema changes. Key points from our conversation that are included here:

- Always inspect the `migrations` table in the cloned production DB before running new migrations: `php artisan migrate:status` and/or query the `migrations` table directly.
- New migration filenames should sort after existing ones (use current timestamps) to avoid ordering collisions with repo migrations.
- Use idempotent checks in migrations (Schema::hasTable / hasColumn) so they can run safely against a DB with partially-applied changes.
- For renames or destructive changes, use a two-step approach: add nullable new column, backfill with chunked jobs, dual-write window, then make new column NOT NULL and drop old column.

## Plan to detect and rename columns (concrete steps)

1. Inventory current state on the cloned DB
   - Run `php artisan migrate:status` to get applied migrations.
   - Query the DB to list existing tables and columns (for MySQL):

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'your_db_name'
ORDER BY TABLE_NAME;
```

2. Build expected schema from repo migrations
   - Parse `database/migrations/*.php` to find `Schema::create`, `Schema::table` calls and `->renameColumn()` or `->dropColumn()` usages. This can be automated with a small PHP script that tokenizes files and extracts migration operations.

3. Create a mapping file (old_name -> new_name)
   - For each table found in the cloned DB that differs from the expected schema, add an entry to a mapping JSON or YAML file, for example:

```json
{
  "users": {
  "old_email": "email_address",
  "phone": "phone_number"
  },
  "invoices": {
  "child_id": "child_enrollment_id"
  }
}
```

4. Generate safe migration stubs
   - For each mapping entry generate a migration stub using the copy-and-swap pattern. Example filename format: `2025_10_31_00000X_copy_users_email_to_email_address.php` where the timestamp is >= latest repo migration.

5. Add backfill commands
   - Create an artisan command `php artisan migrations:backfill --job=CopyUsersEmail` or small queued jobs to perform chunked copies using Eloquent `chunkById()`.

6. Dual-write window & cutover
   - Deploy application changes that write to both old and new columns (or use accessors/mutators to handle read/write compatibility).
   - Monitor for data integrity. When satisfied, run Migration B to enforce NOT NULL and drop the old column.

## Safe migration template (copy-and-swap)

Use this template for renaming columns. Make `up()` idempotent and `down()` reversible.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (! Schema::hasColumn('users', 'email_address')) {
      Schema::table('users', function (Blueprint $table) {
        $table->string('email_address')->nullable()->after('email');
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('users', 'email_address')) {
      Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('email_address');
      });
    }
  }
};
```

And a simple backfill artisan command skeleton (chunked copy):

```php
public function handle(): void
{
  User::chunkById(1000, function ($users) {
    foreach ($users as $user) {
      if (is_null($user->email_address) && ! is_null($user->email)) {
        $user->email_address = $user->email;
        $user->save();
      }
    }
  });
}
```

## Repository-scan & next-safe-steps (what I can run for you)

I can perform a repository scan and produce a concrete mapping of columns/tables that need renaming or other transforms by:

- Reading `database/migrations/*.php` and extracting schema operations.
- Reading the cloned DB `information_schema` (or you running the SQL above and pasting results) to detect differences.

Choose one:

- Option A: You run `php artisan migrate:status` and paste the output here, and I will generate a conflict-risk list and migration stubs.
- Option B: I create a small PHP script in the repo (`tools/scan_migrations.php`) that you run locally (so it reads your DB) and it will output a mapping file I can use to generate safe migration files.

If you want me to generate migration stubs now, tell me which tables/columns to rename or say "scan repo" and I will create the scanner script and the first batch of safe stubs (do you prefer Option A or B?).

## Next actions I can take for you

1. (A) Inspect this repository to detect the tenancy package, current migrations, and propose concrete migration skeletons.
2. (B) Create example reversible migration files + a `tenants:migrate` artisan command scaffold for your tenancy topology.

Tell me which you want (A or B) and I will proceed: for A I'll scan the repo and produce a concrete plan; for B I'll create migration & command stubs and a couple of Pest tests.

## Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

### Goals / success criteria

- Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
- Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
- Minimize downtime and provide rollback steps.
- Keep the codebase ready for an easier upgrade to Filament v4.

### Contract (inputs / outputs / error modes)

- Inputs:

  # Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

  This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

  Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

  ## Goals / success criteria

  - Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
  - Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
  - Minimize downtime and provide rollback steps.
  - Keep the codebase ready for an easier upgrade to Filament v4.

  ## Contract (inputs / outputs / error modes)

  - Inputs:
    - Current codebase & migrations (legacy project).
    - Database dumps (central and per-tenant if multi-db tenancy).
    - Knowledge of tenancy package used (e.g., stancl/tenancy, hyn, custom).
    - Remove non-prefix tables
    - All usefull data is located in table with 1_* prefix
  - Outputs:
    - New migrations that move/rename/transform tables/columns safely.
    - Updated Eloquent models, factories, and Filament resources.
  - Error modes / failure cases:
    - Missing backups or partial tenant dumps. (Mitigation: refuse action until full backups present.)
    - Data loss from destructive migrations. (Mitigation: run migrations in staging, ensure backups, use copy-and-swap strategy.)

  # Migration guideline: Old Laravel + tenancy DB → Filament v3 (prepare for v4)

  This document explains a practical, low-risk approach to migrate a legacy Laravel application that uses an older tenancy/database layout into a structure that works well with Filament v3, while keeping an eye toward a future Filament v4 upgrade.

  Use this as a checklist and a playbook. It covers inventory, schema mapping, safe migrations (including tenant-specific considerations), code updates (models, factories, Filament resources), testing, verification, and post-migration monitoring. It assumes you have a working local/CI environment and safe backups.

  ## Goals / success criteria

  - Produce a database schema compatible with Filament v3 conventions (resources, users, policies) without losing data.
  - Provide reversible, well-tested migrations and scripts to migrate both central and tenant data.
  - Minimize downtime and provide rollback steps.
  - Keep the codebase ready for an easier upgrade to Filament v4.

  ## Contract (inputs / outputs / error modes)

  - Inputs:
    - Current codebase & migrations (legacy project).
    - Database dumps (central and per-tenant if multi-db tenancy).
    - Knowledge of tenancy package used (e.g., stancl/tenancy, hyn, custom).
  - Outputs:
    - New migrations that move/rename/transform tables/columns safely.
    - Updated Eloquent models, factories, and Filament resources.
  - Error modes / failure cases:
    - Missing backups or partial tenant dumps. (Mitigation: refuse action until full backups present.)
    - Data loss from destructive migrations. (Mitigation: run migrations in staging, ensure backups, use copy-and-swap strategy.)

  ## Assumptions (make these explicit before starting)

  - You can take a maintenance window if some operations require downtime (or have a migration strategy that supports online migration).
  - You have SQL backups for central and all tenant DBs and can restore to a staging environment.
  - The tenancy package and version (or custom solution) is known — migration approach depends on whether you use separate DB per tenant or single DB with tenant_id columns.
  - The project uses Filament v3 now (or is being migrated to v3). If currently on Filament v2 or earlier, increase the scope to include Filament compatibility changes.

  ## High-level plan (ordered)

  1. Inventory & backups
  2. Schema mapping & non-destructive prototyping
  3. Create reversible migrations + migration runner for tenants
  4. Code updates (models, resources, policies, Filament configs)
  5. Tests & verification
  6. Staged rollout (staging → canary → production)
  7. Post-migration follow-up and v4 prep

  ---

  ## 1) Inventory & backups

  - Inventory DB structure: list all tables, columns, indexes, FK constraints, triggers, views, and stored procedures. Export schema and a data sample for each table.
  - Identify tenancy model:
    - Multi-database (one DB per tenant)? Or single database with tenant_id on tables?
    - Which package or custom code implements tenancy? (e.g., stancl/tenancy, tenancy/tenancy, hyn).
  - Take full backups: central DB and all tenant DBs. Keep copies off-host and verify restores in staging.
  - Create a short mapping document: old_table → new_table (if rename), columns that will be dropped/renamed/type-changed.

  Tip: Use tools like mysqldump/pg_dump for dumps, and create checksums on rows before/after migration to validate data integrity.

  ## 2) Schema mapping & prototyping (non-destructive)

  - Avoid in-place destructive schema changes. Prefer add-column/copy data then drop-old approach.
  - Create a directory for migration helpers (if not present): `database/migration-scripts` or a small artisan command to run tenant migrations.
  - Typical mapping tasks:
    - Rename tables to Filament-conventional names (or keep existing and adapt resources). Filament doesn't require specific table names, but models & resource code should match current tables.
    - Move polymorphic relations, pivot tables, and JSON columns carefully: ensure JSON casting in models.
    - Convert enum-like tinyints/strings into PHP Enum classes in `app/Enums` and update migrations to preserve values.
  - Prototype in a staging environment: run new migrations against a copy of production data and test the app for critical flows.

  ## 3) Create reversible migrations and tenant migration runner

  - Migration style guidance:
    - Always write migrations that can be rolled back or, if destructive, accompany them with a copy strategy: create new columns/tables, copy data, switch code to the new columns, and only then drop old columns after a safe delay.
    - Use explicit SQL for heavy data transforms to improve performance and clarity.
  - Example safe pattern for renaming a column:
    1. Migration A: add new column `new_col` (nullable), leaving `old_col` intact.
    2. A queued job or artisan command to backfill `new_col` from `old_col` for existing rows (use chunking).
    3. Deploy code that writes both `old_col` and `new_col` (dual-write) for a short window.
    4. Migration B (after verification): set `new_col` not-null and drop `old_col`.
  - Tenant migrations:
    - If single DB with tenant_id: normal migrations apply; but data transforms must be tenant-aware.
    - If multi-DB per tenant: you need to run migration per tenant DB. Implement an artisan command that iterates tenant list and runs migrations/seeders on each connection.
    - If your tenancy package provides a helper to run migrations across tenants, prefer it. If custom, create safe automation with logging and error handling.

  ## 4) Code updates: models, Filament resources & Livewire

  - Models:
    - Add or update casts via the `casts()` method as per Laravel 12 conventions.
    - Implement any new interfaces Filament requires (e.g., FilamentUser or other contracts) and ensure policies align.
    - Add helper accessors/mutators to smooth the transition between old/new column names during dual-write phase.
  - Filament specifics (v3):
    - Resources live under `app/Filament/Resources/YourModelResource` — check naming & field components.
    - Ensure `Forms\\Components` and `Tables\\Columns` usage matches v3 API.
    - If your users or admin model shape changed, update resource forms/tables accordingly.
  - Livewire & frontend:
    - If you use Livewire v3, update wiring (e.g., `wire:model.live` where needed) and ensure component namespaces are correct.

  ## 5) Tests & verification

  - Add migration tests (Pest) that run on a copy of production schema (or a smaller sample) to validate transforms.
  - Add integration tests that:
    - Create a tenant, seed with sample data, run tenant migrations, and assert expected tables/columns/data exist.
    - Use `Livewire::test()` for Filament pages to assert basic CRUD operations.
  - Verification checklist (quality gates):
    - Build: PASS (app boots, composer autoload generated)
    - Lint/Format: run `vendor/bin/pint` (format) — ensure PASS
    - Tests: run the subset of tests touching migration & Filament functionality. Ensure PASS.

  Commands to run locally (examples):

  ```bash
  # run formatting
  vendor/bin/pint

  # run a subset of pest tests
  php artisan test --filter=Migration
  ```

  Note: run the minimal set of tests relevant to changes during iteration. When ready, run the broader suite.

  ## 6) Staged rollout & monitoring

  - Staging: run full migration on staging with production-sized dataset and run tests and manual QA. Validate data using checksums.
  - Canary/Canary tenants: pick a small set of tenants and run migration there first.
  - Production: run migrations during a maintenance window or use zero-downtime approach with dual-read/write and a final cutover.
  - Monitoring: set up quick queries that check row counts and a few sample rows before/after migration; alert on discrepancy.

  ## 7) Rollback plan

  - If migration uses copy-and-swap: rollback is to keep serving old columns until you are sure; you can remove new columns if required and resume the old code paths.
  - For destructive one-shot changes: ensure you have tested DB backups and quick restore playbook; test the restore in staging beforehand.

  ## Filament v4 considerations (keep in mind now)

  - Keep Filament resource logic isolated and small. Avoid embedding heavy migration transforms inside resource classes.
  - Filament v4 may change resource APIs or folder layout; decouple business logic into model methods and services so UI layer can be upgraded with minimal DB changes.
  - Keep Livewire/Alpine interactions minimal and use server-side validation and policies.

  ## Example migration checklist (concrete)

  1. Backup central DB and all tenant DBs (verify restore).
  2. Freeze non-essential writes or put site into read-only mode for a short window if necessary.
  3. Run prototype migration in staging and run tests.
  4. Deploy code that supports dual-write for renamed columns (if required).
  5. Run data-copy migration jobs to fill new schema.
  6. Validate data with checksums and spot checks.
  7. Switch code to read from new columns.
  8. After 1–2 days of verification, drop old columns in a final migration.

  ## Practical tips & gotchas

  - Large tables: perform backfills in chunks to avoid long locks or high memory usage. Use DB-native tools (pt-online-schema-change) if needed.
  - Foreign keys: drop/recreate foreign keys in careful order; consider using disable/enable constraints around large transforms where supported.
  - Enums & constants: create PHP Enum classes early and keep integer values stable to avoid subtle bugs.
  - Tenants with custom data variations: test tenant-specific edge cases (custom columns, missing fields).

  ## Verification & quality gate summary

  - Build: app boots with new migrations applied in staging.
  - Lint/Format: `vendor/bin/pint` -> PASS
  - Tests: relevant Pest tests -> PASS
  - Manual QA: Filament admin resource pages load and basic CRUD flows work.

  ## Next steps / recommended actions

  1. Confirm the tenancy package & DB topology used in the project. Note that plan depends heavily on this.
  2. Create `database/migrations/XXXX_add_new_columns_for_migration.php` migration skeletons using the copy-and-swap pattern for each major table.
  3. Create an artisan command `php artisan tenants:migrate` (if multi-db) or adapt your tenancy package runner to handle tenant migrations safely.
  4. Add a small set of Pest tests that run migration steps on a sample tenant and assert the new schema/data.

  If you'd like, I can:

  - (A) Inspect the repo and detect the tenancy package and current migration state and then generate concrete migration skeletons and tenant migration command.
  - (B) Create example reversible migration files and a sample `tenants:migrate` artisan command tailored to your tenancy package.

  Tell me which next step you want (A or B) and I'll continue.
