# KindyGo Legacy Data Migration

> **Status**: DOCUMENTATION COMPLETE - Ready for Review  
> **Last Updated**: February 2025  
> **Scope**: 73 Legacy Tables (1_* prefix) from `kindygo` database

---

## Document Index

| File | Purpose | When to Use |
|------|---------|-------------|
| [01-MIGRATION-PLAN.md](./01-MIGRATION-PLAN.md) | Comprehensive migration strategy, phases, timeline | Planning & team alignment |
| [02-DATA-MAPPING.md](./02-DATA-MAPPING.md) | **EDITABLE** Field-by-field mapping for each table | Implementation reference |
| [03-EXECUTIVE-SUMMARY.md](./03-EXECUTIVE-SUMMARY.md) | High-level overview, success criteria | Stakeholder communication |

---

## Quick Reference

### Source & Target

```
SOURCE: kindygo (Legacy KindyGo v2)
        └─ Tables with prefix: 1_*
        └─ Connection: config/database.php → 'legacy'

TARGET: kindygo_app (Current KindyGo)
        └─ Multi-tenant architecture
        └─ Connection: config/database.php → 'mysql'
```

### Key Decisions

| Decision | Value |
|----------|-------|
| Target Tenant | admin-tenant (or tenant id=1) |
| ID Preservation | Keep legacy IDs for users, centres, lookups |
| User Type | Store in `users.meta_data` as JSON |
| Child Enrollment | Split to MULTIPLE `child_enrolments` per child |
| Discount | Store config in `users.meta_data` (rebuild later) |
| Media Files | Migrate supported legacy uploads to Spatie Media Library |
| Soft Deletes | Skip records with `deleted_at IS NOT NULL` |

### Legacy Source Locations

Record the source locations before running a migration. The legacy database is accessed through
the `legacy` connection configured with the existing `LEGACY_DB_*` environment values; it is not
a filesystem path.

| Source | Value / Pattern | Notes |
|--------|-----------------|-------|
| Legacy application root | `/Users/muhammadnorsyahrinseth/Herd/kindygo` | Local development location; use the absolute path for the environment running the migration. |
| Legacy website UUID | `c136c9fde0ee46499ef6da5e15455449` | Hyn tenancy storage prefix. |
| Tenant media root | `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/` | Contains `children`, `users`, and `transactions`. |
| ChildLog media root | `{LEGACY_APP_PATH}/storage/app/public/child_log_images/` | Legacy Spatie Media Library disk; not tenant-prefixed. |
| Legacy database | `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD` | Used by `config/database.php` connection `legacy`. |

Configure `LEGACY_APP_PATH` with the absolute legacy application path and
`LEGACY_WEBSITE_UUID` with its Hyn tenancy storage prefix. The legacy application can remain in
its existing location; media migration reads directly from that configured source.

Reconcile database file references with the configured source files before running the
production media migration.

### Phase Overview

```
Phase 0: Preparation & Assessment (Days 1-3)
Phase 1: Foundation Tables (Days 4-5)
Phase 2: Master Data - Users, Children, Products (Days 6-16)
Phase 3: Financial Data (Days 17-19)
Phase 4: Relationships (Days 20-21)
Phase 5: Validation & UAT (Days 22-25)
Phase 6: Production Cutover
```

---

## How to Edit This Documentation

### Editing Data Mappings

The **`02-DATA-MAPPING.md`** file is designed for you to edit. Each table has a section with:

1. **Status markers** you can update:
   - `[ ]` = Not reviewed
   - `[x]` = Reviewed & approved
   - `[~]` = Needs adjustment
   - `[SKIP]` = Don't migrate

2. **Field mapping tables** you can modify:
   ```markdown
   | Legacy Field | Target Field | Transform | Notes |
   |--------------|--------------|-----------|-------|
   | old_field    | new_field    | Direct    | Your notes here |
   ```

3. **TODO markers** for things to clarify:
   ```markdown
   <!-- TODO: Verify this mapping with business logic -->
   ```

### After Editing

Once you've made adjustments to the data mapping document:
1. Commit changes to git
2. Ask me to review the updated mappings
3. I'll implement based on your documented specifications

---

## Implementation Structure (To Be Created)

```
app/
├── Console/Commands/
│   ├── MigrateLegacyCommand.php          (orchestrator)
│   ├── MigrateLegacyUsersCommand.php
│   ├── MigrateLegacyChildrenCommand.php
│   ├── MigrateLegacyProductsCommand.php
│   ├── MigrateLegacyFinancialsCommand.php
│   └── ValidateLegacyMigrationCommand.php
│
└── Services/Migration/
    ├── MigrationService.php
    ├── UserMigrator.php
    ├── ChildrenMigrator.php
    ├── ProductMigrator.php
    ├── FinancialMigrator.php
    └── ValidationService.php
```

---

## Contact & Ownership

- **Plan Owner**: [Your Name]
- **Technical Lead**: [Developer Name]
- **Review Date**: [Date]

---

## Changelog

| Date | Change | Author |
|------|--------|--------|
| Feb 2025 | Initial README created | AI Assistant |
| Feb 2025 | Created 01-MIGRATION-PLAN.md (comprehensive 6-phase plan) | AI Assistant |
| Feb 2025 | Created 02-DATA-MAPPING.md (editable field mappings) | AI Assistant |
| Feb 2025 | Created 03-EXECUTIVE-SUMMARY.md (stakeholder overview) | AI Assistant |
| | | |
