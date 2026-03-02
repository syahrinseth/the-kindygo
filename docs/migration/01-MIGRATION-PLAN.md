# KindyGo Legacy Data Migration Plan

> **Version**: 1.0  
> **Last Updated**: February 2025  
> **Status**: APPROVED - Ready for Implementation

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Source & Target Systems](#source--target-systems)
3. [Migration Phases](#migration-phases)
4. [Risk Assessment & Mitigation](#risk-assessment--mitigation)
5. [Technical Requirements](#technical-requirements)
6. [Implementation Guidelines](#implementation-guidelines)
7. [Rollback Strategy](#rollback-strategy)

---

## Executive Summary

This document outlines the comprehensive plan for migrating data from the legacy KindyGo v2 system (`kindygo` database) to the new KindyGo application (`kindygo_app` database). The migration involves 73 legacy tables with the `1_*` prefix containing user, child, financial, and operational data.

### Key Objectives
- Preserve all historical data for audit and compliance
- Maintain referential integrity across all migrated entities
- Minimize downtime during production cutover
- Enable rollback capability at each phase

### Timeline Overview
| Phase | Duration | Focus |
|-------|----------|-------|
| Phase 0 | Days 1-3 | Preparation & Assessment |
| Phase 1 | Days 4-5 | Foundation Tables |
| Phase 2 | Days 6-16 | Master Data (Users, Children, Products) |
| Phase 3 | Days 17-19 | Financial Data |
| Phase 4 | Days 20-21 | Relationships & Media |
| Phase 5 | Days 22-25 | Validation & UAT |
| Phase 6 | Day 26 | Production Cutover |

---

## Source & Target Systems

### Source: Legacy KindyGo v2

```
Database: kindygo
Connection: config/database.php → 'legacy'
Tables: 73 tables with prefix 1_*
```

**Key Legacy Tables:**
| Table | Records | Purpose |
|-------|---------|---------|
| `1_users` | ~2000+ | Parents, staff, admin users |
| `1_child` | ~1500+ | Child profiles + enrollment state |
| `1_preschool` | ~10 | Preschool/Centre locations |
| `1_product` | ~50+ | Products and programmes |
| `1_invoices` | ~50000+ | Invoice headers |
| `1_transactions` | ~100000+ | Invoice line items (with type = 'bill') |
| `1_transactions` | ~40000+ | Payment records (with type = 'payment') |

### Target: Current KindyGo Application

```
Database: kindygo_app
Connection: config/database.php → 'mysql'
Architecture: Multi-tenant with Filament 4
```

**Key Target Tables:**
| Table | Purpose |
|-------|---------|
| `users` | User accounts with meta_data JSON |
| `children` | Child profiles (profile data only) |
| `child_enrolments` | Enrollment state per product |
| `centres` | Centre locations (replaces preschool) |
| `products` | Products with prices |
| `invoices` | Invoice headers |
| `invoice_items` | Invoice line items |
| `payments` | Payment records |

---

## Migration Phases

### Phase 0: Preparation & Assessment (Days 1-3)

**Objectives:**
- Validate legacy data integrity
- Create migration infrastructure
- Backup all data

**Tasks:**

- [ ] **0.1** Create full backup of `kindygo` database
- [ ] **0.2** Create full backup of `kindygo_app` database
- [ ] **0.3** Verify legacy database connection works
- [ ] **0.4** Run data quality assessment queries
- [ ] **0.5** Create migration logging table
- [ ] **0.6** Create orphan records log table
- [ ] **0.7** Document all soft-deleted record counts
- [ ] **0.8** Verify media files exist at `/storage/app/kindygo-legacy/`

**Deliverables:**
- Data quality report
- Backup confirmation
- Migration infrastructure ready

---

### Phase 1: Foundation Tables (Days 4-5)

**Objectives:**
- Migrate lookup tables and reference data
- Establish the tenant for all legacy data
- Migrate centres (preschools)

**Tables:**

| Priority | Legacy Table | Target Table | ID Preserve |
|----------|--------------|--------------|-------------|
| 1.1 | - | `tenants` | Create new |
| 1.2 | `1_preschool` | `centres` | YES |
| 1.3 | `1_roles` | `roles` | YES |
| 1.4 | `1_product_type` | Store in `products.type` | N/A |
| 1.5 | `1_child_status` | Map to `ChildEnrolmentStatus` enum | N/A |
| 1.6 | `1_user_status` | Store in `users.meta_data` | N/A |
| 1.7 | `1_payment_method` | Store in `payments.gateway` | N/A |
| 1.8 | `1_payment_status` | Map to `InvoiceStatus` enum | N/A |

**Tasks:**

- [ ] **1.1** Create or identify target tenant (admin-tenant or id=1)
- [ ] **1.2** Migrate `1_preschool` → `centres` (skip soft-deleted)
- [ ] **1.3** Migrate `1_roles` → `roles` (preserve IDs)
- [ ] **1.4** Create status mapping documentation
- [ ] **1.5** Validate all centres created correctly

**Validation:**
```sql
-- Verify centre count matches (excluding deleted)
SELECT COUNT(*) FROM kindygo.1_preschool WHERE deleted_at IS NULL;
SELECT COUNT(*) FROM kindygo_app.centres WHERE tenant_id = 1;
```

---

### Phase 2: Master Data (Days 6-16)

**Objectives:**
- Migrate all users with profiles
- Migrate children with profile/enrollment split
- Migrate products with pricing

#### Phase 2.1: Users (Days 6-10)

| Priority | Legacy Table | Target Table |
|----------|--------------|--------------|
| 2.1.1 | `1_users` | `users` |
| 2.1.2 | `1_users` | `user_profiles` |
| 2.1.3 | `1_users` | `user_addresses` |
| 2.1.4 | `1_users_meta` | `users.meta_data` |
| 2.1.5 | `1_model_has_roles` | `model_has_roles` |

**Tasks:**

- [ ] **2.1.1** Migrate user accounts (skip soft-deleted)
- [ ] **2.1.2** Store `user_status` in `meta_data` JSON
- [ ] **2.1.3** Store discount config in `meta_data` JSON
- [ ] **2.1.4** Migrate spouse data to profiles/addresses
- [ ] **2.1.5** Map legacy roles to new roles
- [ ] **2.1.6** Attach users to target tenant

**Special Handling:**
```php
// User meta_data structure
[
    'legacy_user_status' => 1, // 1=Normal, 2=Staff, 3=Family
    'legacy_user_status_name' => 'Normal',
    'discount_config' => [
        'discount_by_month' => [...],
        'discount_by_month_amount' => '100',
        'discount_by_month_reason' => 'Staff discount',
        'monthly_discount_amount' => '50',
        'monthly_discount_reason' => 'Sibling discount',
        'discount_histories' => [...],
    ],
    'legacy_id' => 123,
]
```

#### Phase 2.2: Children & Enrollments (Days 11-14)

| Priority | Legacy Table | Target Table |
|----------|--------------|--------------|
| 2.2.1 | `1_child` | `children` |
| 2.2.2 | `1_child` | `tenant_child` (pivot) |
| 2.2.3 | `1_child` | `centre_child` (pivot) |
| 2.2.4 | `1_child.product` | `child_enrolments` (row 1) |
| 2.2.5 | `1_child.december_product_id` | `child_enrolments` (row 2) |
| 2.2.6 | `1_child.other_products` | `child_enrolments` (rows 3+) |

**CRITICAL: Enrollment Split Logic**

```
Legacy 1_child record:
├── Profile Data → children table
│   ├── fullname → first_name, last_name
│   ├── mykid_no → mykid_no
│   ├── dob → date_of_birth
│   └── ... other profile fields
│
└── Enrollment Data → child_enrolments table (MULTIPLE ROWS)
    ├── product → child_enrolments row 1
    ├── december_product_id → child_enrolments row 2 (if not null)
    └── other_products (JSON) → child_enrolments rows 3, 4, ... (parse array)
```

**Status Mapping:**

| Legacy `1_child_status.id` | Legacy Name | Target `ChildEnrolmentStatus` |
|---------------------------|-------------|------------------------------|
| 1 | New Children | `PENDING` |
| 2 | Return Children | `ACTIVE` |
| 3 | Alumni | `COMPLETED` |
| 4 | Future | `PENDING` |
| 5 | Future (Return) | `PENDING` |
| 6 | Suspended | `INACTIVE` |
| 7 | Registered | `ACTIVE` |
| 8 | Unregistered | `DRAFT` |
| 9 | Trial (1 Month) | `ACTIVE` |
| 10 | Cancelled | `CANCELLED` |
| 11 | Trial (5 Days) | `ACTIVE` |

**Tasks:**

- [ ] **2.2.1** Create name splitting logic (fullname → first/last)
- [ ] **2.2.2** Migrate child profiles (skip soft-deleted)
- [ ] **2.2.3** Create tenant_child pivot records
- [ ] **2.2.4** Create centre_child pivot records
- [ ] **2.2.5** Create enrollment from `product` field
- [ ] **2.2.6** Create enrollment from `december_product_id` (if not null)
- [ ] **2.2.7** Parse and create enrollments from `other_products` JSON
- [ ] **2.2.8** Map child status to enrollment status

#### Phase 2.3: Products (Days 15-16)

| Priority | Legacy Table | Target Table |
|----------|--------------|--------------|
| 2.3.1 | `1_product` | `products` |
| 2.3.2 | `1_product` | `product_prices` |
| 2.3.3 | `1_product_meta` | `products` (JSON fields) |

**Tasks:**

- [ ] **2.3.1** Migrate products (skip soft-deleted)
- [ ] **2.3.2** Map `product_type` to `ProductType` enum
- [ ] **2.3.3** Create product_prices from `price` field
- [ ] **2.3.4** Store `price_history` in meta/JSON

---

### Phase 3: Financial Data (Days 17-19)

**Objectives:**
- Migrate all invoices with items
- Migrate payments
- Preserve discount history through invoice_items

#### Phase 3.1: Invoices (Days 17-18)

| Priority | Legacy Table | Target Table |
|----------|--------------|--------------|
| 3.1.1 | `1_invoices` | `invoices` |
| 3.1.2 | `1_transactions` (type = 'bill') | `invoice_items` |

**Invoice Status Mapping:**

| Legacy `1_payment_status.id` | Legacy Name | Target `InvoiceStatus` |
|------------------------------|-------------|------------------------|
| 1 | Pending Payment | `PENDING` |
| 2 | Overdue | `OVERDUE` |
| 3 | Partially Paid | `PARTIALLY_PAID` |
| 4 | Processing | `PENDING` |
| 5 | On Hold | `PENDING` |
| 6 | Carried Forward | `PENDING` |
| 7 | Completed | `PAID` |
| 8 | Refunded | `CANCELLED` |
| 9 | Cancel | `CANCELLED` |
| 10 | Completed With Excess Payment | `PAID` |
| 11 | Completed With Deposit | `PAID` |
| 12 | Draft | `DRAFT` |

**Tasks:**

- [ ] **3.1.1** Migrate invoice headers (skip soft-deleted)
- [ ] **3.1.2** Map payment_status to InvoiceStatus enum
- [ ] **3.1.3** Migrate invoice_items (preserve discounts)
- [ ] **3.1.4** Calculate and verify totals
- [ ] **3.1.5** Handle negative amounts (discounts)

#### Phase 3.2: Payments (Day 19)

| Priority | Legacy Table | Target Table |
|----------|--------------|--------------|
| 3.2.1 | `1_transactions` (type = 'payment') | `payments` |
| 3.2.2 | `1_transactions` (type = 'payment') | `payments` (additional data) |

**Payment Method Mapping:**

| Legacy `1_payment_method.id` | Legacy Name | Target `gateway` |
|------------------------------|-------------|------------------|
| 1 | Billplz: Online Banking (FPX) | `billplz` |
| 2 | CDM/Manual Transfer | `manual` |
| 3 | Cheque | `cheque` |
| 4 | CHIP: Online Banking (FPX), Visa & Mastercard | `chip` |
| 5 | Zakat | `zakat` |
| 6 | Baitulmal | `baitulmal` |
| 7 | JKM | `jkm` |
| 8 | ANIS | `anis` |
| 9 | Booking (CHIP) | `chip_booking` |

**Tasks:**

- [ ] **3.2.1** Migrate payments (skip soft-deleted)
- [ ] **3.2.2** Map payment_method to gateway
- [ ] **3.2.3** Create invoice_payment pivot records

---

### Phase 4: Relationships & Media (Days 20-21)

**Objectives:**
- Migrate parent-child relationships
- Migrate media files to Spatie library
- Handle orphaned records

#### Phase 4.1: Relationships (Day 20)

| Priority | Legacy Source | Target Table |
|----------|---------------|--------------|
| 4.1.1 | `1_child.parent_id` | `child_user` pivot |
| 4.1.2 | `1_users.guardians` (JSON) | `child_user` pivot |
| 4.1.3 | `1_classroom_user` | Future: classroom assignments |

**Tasks:**

- [ ] **4.1.1** Create parent-child relationships from `parent_id`
- [ ] **4.1.2** Parse guardians JSON and create relationships
- [ ] **4.1.3** Log orphaned children (no parent found)

#### Phase 4.2: Media Files (Day 21)

| Priority | Legacy Source | Target Collection |
|----------|---------------|-------------------|
| 4.2.1 | `1_child.passport_sized_image` | `children.photo` |
| 4.2.2 | `1_child.child_birth_certificate` | `children.birth_certificate` |
| 4.2.3 | `1_child.immunization_card` | `children.immunization_card` |
| 4.2.4 | `1_users.user_mykad_image` | `users.mykad` |
| 4.2.5 | `1_users.user_passport_size_photo` | `users.photo` |

**Tasks:**

- [ ] **4.2.1** Verify files exist in `/storage/app/kindygo-legacy/`
- [ ] **4.2.2** Copy files to Spatie media library
- [ ] **4.2.3** Create media records for children
- [ ] **4.2.4** Create media records for users
- [ ] **4.2.5** Log missing files

---

### Phase 5: Validation & UAT (Days 22-25)

**Objectives:**
- Verify data integrity
- Run reconciliation reports
- User acceptance testing

**Validation Queries:**

```sql
-- Record count validation
SELECT 'users' as entity, 
       (SELECT COUNT(*) FROM kindygo.1_users WHERE deleted_at IS NULL) as legacy,
       (SELECT COUNT(*) FROM kindygo_app.users) as migrated;

SELECT 'children' as entity,
       (SELECT COUNT(*) FROM kindygo.1_child WHERE deleted_at IS NULL) as legacy,
       (SELECT COUNT(*) FROM kindygo_app.children WHERE deleted_at IS NULL) as migrated;

-- Financial reconciliation
SELECT 'invoice_total' as metric,
       (SELECT SUM(price) FROM kindygo.1_invoices WHERE deleted_at IS NULL) as legacy,
       (SELECT SUM(total) FROM kindygo_app.invoices) as migrated;
```

**Tasks:**

- [ ] **5.1** Run all validation queries
- [ ] **5.2** Review orphaned records log
- [ ] **5.3** Verify sample records (10-20 per table)
- [ ] **5.4** Test user login with legacy credentials
- [ ] **5.5** Test child enrollment display
- [ ] **5.6** Test invoice viewing
- [ ] **5.7** UAT sign-off from stakeholders

---

### Phase 6: Production Cutover (Day 26)

**Pre-Cutover Checklist:**

- [ ] All validation tests passed
- [ ] UAT sign-off obtained
- [ ] Backup of production databases
- [ ] Rollback plan tested
- [ ] Team notified of cutover window

**Cutover Steps:**

1. **Freeze legacy system** (read-only or maintenance mode)
2. **Final backup** of both databases
3. **Run delta migration** (any records created since Phase 5)
4. **Run final validation**
5. **Switch application config** to use new data
6. **Smoke test** critical flows
7. **Announce completion**

**Rollback Triggers:**
- Data integrity validation fails
- Critical functionality broken
- Stakeholder requests rollback

---

## Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Data loss during migration | Low | Critical | Multiple backups, validation at each phase |
| Orphaned records | Medium | Medium | Log and review, manual remediation |
| Status mapping errors | Medium | Medium | Document all mappings, sample testing |
| Media file missing | Medium | Low | Log missing, continue migration |
| Performance issues | Low | Medium | Batch processing, off-hours migration |
| ID conflicts | Low | High | Preserve legacy IDs where possible |

---

## Technical Requirements

### Infrastructure
- PHP 8.2+
- MySQL 8.0+
- Laravel 12
- Composer dependencies installed

### Commands to Implement

```
app/Console/Commands/
├── MigrateLegacyCommand.php           (orchestrator)
├── MigrateLegacyCentresCommand.php
├── MigrateLegacyUsersCommand.php
├── MigrateLegacyChildrenCommand.php
├── MigrateLegacyProductsCommand.php
├── MigrateLegacyInvoicesCommand.php
├── MigrateLegacyPaymentsCommand.php
├── MigrateLegacyMediaCommand.php
└── ValidateLegacyMigrationCommand.php
```

### Service Classes

```
app/Services/Migration/
├── MigrationService.php               (shared utilities)
├── StatusMapper.php                   (enum mappings)
├── NameParser.php                     (fullname splitting)
├── OrphanLogger.php                   (orphan record handling)
└── ValidationService.php              (data verification)
```

---

## Implementation Guidelines

### General Rules

1. **Skip soft-deleted records**: `WHERE deleted_at IS NULL`
2. **Preserve IDs where specified**: Use `insertOrIgnore` with explicit IDs
3. **Log all orphans**: Create `migration_orphans` table for review
4. **Batch processing**: Use chunking (500-1000 records)
5. **Idempotent**: Commands should be re-runnable safely

### Code Pattern

```php
// Example migration command pattern
public function handle()
{
    $this->info('Starting migration...');
    
    $query = DB::connection('legacy')
        ->table('1_users')
        ->whereNull('deleted_at');
    
    $bar = $this->output->createProgressBar($query->count());
    
    $query->chunk(500, function ($records) use ($bar) {
        foreach ($records as $record) {
            try {
                $this->migrateUser($record);
            } catch (\Exception $e) {
                $this->logOrphan('users', $record->id, $e->getMessage());
            }
            $bar->advance();
        }
    });
    
    $bar->finish();
    $this->info("\nMigration complete!");
}
```

---

## Rollback Strategy

### Phase-Level Rollback

Each phase can be rolled back independently:

```bash
# Rollback Phase 3 (Financial Data)
php artisan migrate:legacy:rollback --phase=3
```

### Full Rollback

```bash
# Restore from backup
mysql kindygo_app < backup_kindygo_app_YYYYMMDD.sql
```

### Point-in-Time Recovery

- All migration commands log start/end timestamps
- Backup snapshots taken before each phase
- `migration_log` table tracks all operations

---

## Appendix

### A. Legacy Table Reference

See `02-DATA-MAPPING.md` for complete field-by-field mapping.

### B. Enum Definitions

See `app/Enums/` for all status enum definitions.

### C. Contact

- **Technical Lead**: [Developer Name]
- **Project Manager**: [PM Name]
- **Stakeholder**: [Stakeholder Name]
