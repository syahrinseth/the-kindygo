# Migration Implementation Progress

> **Auto-updated** as each phase completes  
> **Started**: March 2026

---

## Phases Overview

| Phase | Description | Status | Records |
|-------|-------------|--------|---------|
| 0a | DB migrations for missing columns | DONE | 5 columns added |
| 0b | ChildStatus enum fix (FUTURE_RETURN) | DONE | 1 enum case added |
| 0c | Migration infrastructure tables (logs, orphans) | DONE | 2 tables created |
| 0d | FamilyMember model + migration + factory | DONE | 1 model + table + factory |
| 0e | Helper services (StatusMapper, NameParser, OrphanLogger, MigrationLogger) | DONE | 4 services |
| 1 | Foundation: Centres & Roles migration commands | DONE | 22 centres, 9 roles |
| 2a | Master Data: Users command | DONE | 2,468 users + profiles |
| 2c | Master Data: Products command | DONE | 437 products |
| 2b | Master Data: Children command | DONE | 2,425 children |
| 3a | Financial Data: Invoices command | DONE | 43,118 invoices |
| 3b | Financial Data: Payments command | DONE | 38,569 payments |
| 4 | Media migration command | CREATED | Dry-run verified |
| 5 | Validation command | DONE | 17/17 FK checks PASS |
| 6 | Orchestrator command | DONE | All phases chained |
| 7 | Pest tests for all phases | DONE | 161 tests, 533 assertions |

---

## Phase 0: Infrastructure

### 0a — Missing Database Columns (DONE)

Migration: `2026_03_02_042514_add_legacy_migration_columns_to_existing_tables.php`

| Table | Column | Type | Purpose |
|-------|--------|------|---------|
| `centres` | `meta_data` | json, nullable | Legacy SSM data (ssm_comp_name, ssm_no, capacity) |
| `users` | `meta_data` | json, nullable | Legacy user status, discount config, guardians |
| `payments` | `meta` | json, nullable | Legacy gateway_collection_id, remarks |
| `products` | `description` | longtext, nullable | Legacy product remarks |
| `user_office_infos` | `office_name` | varchar(255), nullable | Legacy company_name |

### 0b — ChildStatus Enum (DONE)

Added `FUTURE_RETURN` case to `app/Enums/ChildStatus.php` for legacy status 5.

### 0c — Migration Infrastructure Tables (DONE)

Migration: `2026_03_02_042657_create_migration_infrastructure_tables.php`

- `migration_logs` — tracks each phase run (timestamps, counts, errors)
- `migration_orphans` — logs records that couldn't be migrated (missing FK references)

### 0d — FamilyMember Model (DONE)

Migration: `2026_03_02_042658_create_family_members_table.php`

New model for spouse data from legacy `1_users` table. Fields: name, nric, phone, email, occupation, address fields, office fields. Links to User via `user_id`. Supports Spatie Media Library (mykad, photo collections).

- Model: `app/Models/FamilyMember.php`
- Factory: `database/factories/FamilyMemberFactory.php`

### 0e — Helper Services (DONE)

| Service | Location | Purpose |
|---------|----------|---------|
| `StatusMapper` | `app/Services/Migration/StatusMapper.php` | Maps legacy int statuses to enums |
| `NameParser` | `app/Services/Migration/NameParser.php` | Splits fullname into first/last |
| `OrphanLogger` | `app/Services/Migration/OrphanLogger.php` | Logs orphaned records |
| `MigrationLogger` | `app/Services/Migration/MigrationLogger.php` | Tracks migration progress |

---

## Phase 1: Foundation Tables (DONE)

### Commands
- `migrate:legacy-centres` — Migrates `1_campuses` → `campuses` (6 records) and `1_preschool` → `centres` (21 active, 22 total)
- `migrate:legacy-roles` — Created 4 new roles (Auditor, Owner, Principal, Teacher), 5 already existed

### Results

| Entity | Count |
|--------|-------|
| Campuses | 6 |
| Centres | 21 active + 1 soft-deleted (ID 14) |
| Roles created | 4 new |
| Roles existing | 5 |

---

## Phase 2: Master Data

### Phase 2a — Users (DONE)

Command: `migrate:legacy-users`

| Entity | Count | Notes |
|--------|-------|-------|
| Users | 2,468 | All non-deleted legacy users |
| User Profiles | 1,663 | NRIC, phone, occupation |
| User Addresses | 1,433 | State mapped via lookup table |
| User Office Infos | 993 | Including office_name |
| Family Members | 1,283 | Spouse data extracted |
| Tenant-User pivot | 2,468 | All linked to tenant_id=1 |
| Centre-User pivot | 2,527 | From preschool + other_preschools |
| Model Has Roles | 2,532 | 1 skipped (role 10 = Application) |

### Phase 2c — Products (DONE — Runs BEFORE Phase 2b)

Command: `migrate:legacy-products`

Execution order changed: Products must run before Children because `child_enrolments.product_id` has FK constraint on `products.id`.

| Entity | Count | Notes |
|--------|-------|-------|
| Products | 437 | Auto-generated codes from names |
| Product Prices | 846 | Parsed from price_history JSON, prices in cents |
| Product-Centre pivot | 1,287 | From preschool JSON array |

### Phase 2b — Children (DONE)

Command: `migrate:legacy-children`

| Entity | Count | Notes |
|--------|-------|-------|
| Children | 2,425 | 79 soft-deleted preserved |
| Child Enrolments | 1,825 | From primary product; additional_products from other_products |
| Child-User pivot | 2,362 | parent_id → child_user with relationship_type=parent |
| Tenant-Child pivot | 2,425 | Status mapped via StatusMapper |
| Centre-Child pivot | 2,398 | From preschool_id |

---

## Phase 3: Financial Data

### Phase 3a — Invoices (DONE)

Command: `migrate:legacy-invoices`

| Entity | Count | Notes |
|--------|-------|-------|
| Invoices | 43,118 | Status mapped, numbers sanitised (spaces → hyphens) |
| Invoice Items | 86,466 | From 88,127 legacy bills; 1,661 orphaned |
| Orphaned items | 1,661 | Missing invoices/products/centres |

Additional migrations created:
- `2026_03_02_050046_make_invoices_columns_nullable_for_legacy_migration.php` — Made `due_at`, `centre_id`, `user_id` nullable

Fixes applied:
- `InvoiceStatus` enum — added missing `REFUNDED` case in `label()` match
- Invoice totals recalculated via bulk SQL UPDATE

### Phase 3b — Payments (DONE)

Command: `migrate:legacy-payments` (3 steps)

| Step | Entity | Count | Notes |
|------|--------|-------|-------|
| 1 | Payments | 38,569 | Of 38,735 legacy; 166 orphaned (missing users) |
| 2 | Invoice-Payment pivot | 38,548 | 187 skipped: 166 orphan users + 21 orphan invoices |
| 3 | Invoices → PAID | 32,501 | Bulk SQL update |
| 3 | Invoices → PARTIALLY_PAID | 3,099 | Bulk SQL update |

Additional migrations created:
- `2026_03_02_053138_make_payments_reference_no_nullable_for_legacy_migration.php`

Date sanitisation added: `sanitiseDatetime()` method handles malformed dates (2-digit years, epoch dates).

---

## Phase 4: Media Migration (CREATED — DRY-RUN ONLY)

Command: `migrate:legacy-media` (4 steps)

| Step | Entity | Files Expected | Files Missing | Errors |
|------|--------|---------------|---------------|--------|
| 1 | Child media (photo, birth_cert, immunisation) | 3,170 | 1,435 | 0 |
| 2 | User media (mykad, photo, immunisation) | 1,682 | 1,974 | 0 |
| 3 | Family member media (spouse mykad, photo) | 504 | 1,254 | 0 |
| 4 | Payment proof | 5,263 | 1 | 0 |

Source: `/storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/`

**Not yet executed for real** — will copy ~10,619 files (~7.5 GB).

---

## Phase 5: Validation (DONE)

Command: `migrate:legacy-validate`

### Results Summary

| Check | Result |
|-------|--------|
| FK Integrity | 17/17 PASS |
| Record Counts | 5 PASS, 3 WARN (expected variances from orphans) |
| Financial | 1 PASS, 4 WARN (1,255 Billplz auto-payments; 59 zero/neg; 21 pivot mismatches; RM82,705 total diff) |
| Enum Consistency | 3/3 PASS |
| Media | WARN (not yet run for real) |

### Invoice Status Distribution

| Status | Count |
|--------|-------|
| paid | 32,501 |
| pending | 5,344 |
| partially_paid | 3,099 |
| cancelled | 1,993 |
| draft | 112 |
| overdue | 65 |
| refunded | 4 |

---

## Phase 6: Orchestrator (DONE)

Command: `migrate:legacy` (alias: `migrate:legacy-all`)

Features:
- `--from-phase` / `--to-phase` for selective execution
- `--skip-media` / `--skip-validation` flags
- `--dry-run`, `--chunk`, `--tenant-id` pass-through
- Failure handling with user confirmation to continue
- Phase timing and summary table

---

## Phase 7: Tests (DONE)

Pest tests in `tests/Feature/Migration/` for each phase.

| Test File | Phase | Tests | Assertions |
|-----------|-------|-------|------------|
| `MigrateLegacyCentresTest.php` | 1 (Centres & Roles) | 14 | - |
| `MigrateLegacyUsersTest.php` | 2a (Users) | 21 | - |
| `MigrateLegacyProductsTest.php` | 2c (Products) | 14 | - |
| `MigrateLegacyChildrenTest.php` | 2b (Children) | 31 | - |
| `MigrateLegacyInvoicesTest.php` | 3a (Invoices) | 23 | - |
| `MigrateLegacyPaymentsTest.php` | 3b (Payments) | 26 | - |
| `MigrateLegacyValidateTest.php` | 5 (Validation) | 20 | - |
| `MigrateLegacyAllTest.php` | 6 (Orchestrator) | 12 | - |
| **Total** | **All** | **161** | **533** |

Test helper trait: `tests/Traits/LegacyMigrationTestHelper.php`

Key testing techniques:
- SQLite in-memory database with shared PDO between `default` and `legacy` connections
- Legacy tables created dynamically with `1_` prefix to match production schema
- Table drop-and-recreate approach for FK violation tests (SQLite enforces FK constraints inside transactions)
- Full migration pipeline helper (`runFullMigration()`) for integration tests

---

## Key Decisions

1. **Target tenant**: All data → `tenant_id = 1`
2. **ID preservation**: YES for users, children, centres, products, invoices, invoice_items, payments
3. **Soft deletes**: Skip records where `deleted_at IS NOT NULL` (except children — preserve soft deletes)
4. **December product**: SKIP entirely
5. **Enrolment split**: 1 child → 1 enrolment (primary product) + additional_products JSON (other_products)
6. **Chunking**: 500 records per batch
7. **Idempotent**: All commands re-runnable (use updateOrInsert / upsert)
8. **Legacy DB**: Connection name `legacy` in `config/database.php`
9. **Execution order**: Products (2c) before Children (2b) due to FK constraint
10. **Performance**: `DB::table()->upsert()` for batch ops (not `updateOrInsert()`)
11. **Memory**: `array_flip()` + `isset()` for O(1) lookups on large arrays
12. **Date sanitisation**: Reject years < 2000 or > 2030, fix 2-digit years by adding 2000

---

## Models Modified

| Model | Changes |
|-------|---------|
| `User` | Added `meta_data` to fillable+casts, added `familyMembers()` HasMany |
| `Centre` | Added `meta_data` to fillable+casts |
| `Payment` | Added `meta` to fillable+casts |
| `Product` | Added `description` to fillable |
| `UserOfficeInfo` | Added `office_name` to fillable |

## Enums Modified

| Enum | Changes |
|------|---------|
| `ChildStatus` | Added `FUTURE_RETURN` case |
| `ProductType` | Added missing match cases (EVENT, MERCHANDISE, OVERTIME, STAYIN, DEPOSIT) |
| `InvoiceStatus` | Fixed missing `REFUNDED` in `label()` match |

---

## Changelog

| Date | Phase | Notes |
|------|-------|-------|
| 2026-03-02 | 0a-0e | Infrastructure: migrations, enum fix, FamilyMember model, 4 helper services |
| 2026-03-02 | 1 | Foundation: 6 campuses, 21 centres, 9 roles migrated |
| 2026-03-02 | 2a | Users: 2,468 users with profiles, addresses, offices, family members, pivots |
| 2026-03-02 | 2c | Products: 437 products with 846 prices and 1,287 centre links |
| 2026-03-02 | 2b | Children: 2,425 children with enrolments and all pivots |
| 2026-03-02 | 3a | Invoices: 43,118 invoices with 86,466 items (1,661 orphaned) |
| 2026-03-02 | 3b | Payments: 38,569 payments, 38,548 pivots, invoice status updates |
| 2026-03-02 | 4 | Media command created and dry-run verified (not executed for real) |
| 2026-03-02 | 5 | Validation: 17/17 FK PASS, all checks completed |
| 2026-03-02 | 6 | Orchestrator command created |
| 2026-03-02 | 7 | Tests: 161 Pest tests (533 assertions) across 8 test files, all passing |
