# KindyGo v2 to v3 Migration Mapping

## Overview

This document provides a comprehensive mapping between the legacy KindyGo v2 database schema and the current v3 Laravel application models. Use this as a reference when migrating data from the tenant-prefixed v2 tables to the normalized v3 schema.

## Architecture Differences

### v2 (Legacy)
- **Multi-tenancy**: Tenant-prefixed tables (e.g., `1_users`, `2_users`)
- **Meta Tables**: Separate meta tables for extended data (`*_meta`)
- **Money Format**: Decimal values (e.g., `99.99`)
- **Status Fields**: Numeric codes (1, 2, 3, etc.)
- **Relationships**: Direct foreign keys, some denormalized data

### v3 (Current)
- **Multi-tenancy**: Unified tables with `tenant_id` foreign keys and global scopes
- **Normalized Structure**: Separate tables for profiles, addresses (one-to-one relationships)
- **Money Format**: Integer cents (e.g., `9999` for $99.99)
- **Status Fields**: PHP Enums (e.g., `InvoiceStatus::Sent`)
- **Relationships**: Laravel Eloquent relationships with pivot tables

---

## Entity Mappings

### 1. Users (`{tenantId}_users` → `users`, `user_profiles`, `user_addresses`, `user_office_info`)

#### Core User Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `users.id` | Direct | Primary key |
| `email` | `users.email` | Direct | Unique per tenant |
| `password` | `users.password` | Direct | Already hashed |
| `status` | - | Skip | v3 uses `deleted_at` for soft deletes |
| `created_at` | `users.created_at` | Direct | |
| `updated_at` | `users.updated_at` | Direct | |

#### User Profile Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `first_name` | `user_profiles.first_name` | Direct | |
| `last_name` | `user_profiles.last_name` | Direct | |
| `phone` | `user_profiles.phone` | Direct | |
| `mobile` | `user_profiles.mobile` | Direct | |
| `gender` | `user_profiles.gender` | Map: M→male, F→female | |
| `avatar` | `media.model_id` && `media.model_type` | Direct | Has one relationship to `Media` Model |
| - | `user_profiles.user_id` | Foreign key | Reference to `users.id` |

#### User Address Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `add` | `user_addresses.address` | Direct | |
| `add_2` | `user_addresses.address_2` | Direct | |
| `city` | `user_addresses.city` | Direct | |
| `state` | `user_addresses.state_code` | Direct | |
| `postcode` | `user_addresses.postal_code` | Direct | |
| - | `user_addresses.country` | Direct | Default: 'MY' |
| - | `user_addresses.user_id` | Foreign key | Reference to `users.id` |

#### User Office Info
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `company_name` | `user_office_infos.company_name` | Direct | |
| `company_phone` | `user_office_infos.office_phone` | Direct | |
| - | `user_office_info.user_id` | Foreign key | Reference to `users.id` |
| `company_add_1` | `user_office_infos.office_address` | Direct | |
| `company_add_2` | `user_office_infos.office_address_2` | Direct | |
| `company_city` | `user_office_infos.office_city`| Direct | |
| `company_postcode` | `user_office_infos.office_state_code`| Direct | |
| `company_state` | `user_office_infos.office_state`| Direct | |

#### User Metadata (`{tenantId}_users_meta`)
Map key-value pairs to appropriate v3 fields based on `meta_key`:
- Extract custom fields and store as JSON in `user_profiles.custom_data` if needed

#### Relationships
- **Tenant Association**: Create record in `tenant_user` pivot table with `tenant_id` and `user_id`
- **Roles**: Map v2 user roles to v3 Spatie permissions (create role assignments)

---

### 2. Children (`{tenantId}_child` → `children`)

#### Core Child Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `children.id` | Direct | Primary key |
| `fullname` | `children.first_name` | Direct | |
| - | `children.last_name` | Direct | |
| `mykid_no` | `children.mykid_no` | Direct | |
| `cert_no` | `children.cert_no` | Direct | |
| `pob` | `children.place_of_birth` | Direct | |
| `dob` | `children.date_of_birth` | Direct | Check for invalid dates |
| `languages` | `children.languages` | Direct | The language use `Language` enum |
| `post_of_child` | `children.position` | Direct | Current child position |
| `product` | `child_enrollments.child_id` | Foreign key | Reference to `ChildEnrollment` with `HasMany` relationship  |
| `gender` | `children.gender` | Map: M→male, F→female, O→other | |
| `race` | `children.race` | Direct | |
| `religion` | `children.religion` | Map: to Religion enum | Refer to `Religion` enum |
| `passport_sized_image` | `media.model_id` && `media.model_type` | Map into `Media` Collection | Refer to `Media` model as `avatar` collection |
| `immunization_card` | `media.model_id` && `media.model_type` | Map into `Media` Collection | Refer to `Media` model as `immunization_card` collection |
| `child_birth_certificate` | `media.model_id` && `media.model_type` | Map into `Media` Collection | Refer to `Media` model as `child_birth_certificate` collection |
| `medical_conditions` | `children.medical_conditions` | Direct | Text field |
| `allergies` | `children.allergies` | Direct | Text field |
| `special_needs` | `children.special_needs` | Direct | Text field |
| `status` | `children.status` | Map: 1→active, 0→inactive | Use ChildStatus enum |
| `created_at` | `children.created_at` | Direct | |
| `updated_at` | `children.updated_at` | Direct | |
| - | `children.tenant_id` | Set from migration context | Foreign key to `tenants.id` |
| `family_clinic` | `children.family_clinic` | Direct | Text field |
| `family_clinic_phone` | `children.family_clinic_phone` | Direct | Text field |


#### Child Metadata (`{tenantId}_child_meta`)
Map metadata key-value pairs to JSON fields or custom columns as needed.

#### Relationships
- **Parents/Guardians**: Create records in `child_user` pivot table
  - Look up parent user by `{tenantId}_child.parent_id` or similar fields
  - `child_id` → `children.id`
  - `user_id` → `users.id`
  - Set `relationship_type` (parent, guardian, emergency_contact)

- **Centre Association**: Create records in `centre_children` pivot table
  - `child_id` → `children.id`
  - `centre_id` → Map from v2 `preschool_id`
  - `enrolled_at` → Migration timestamp

---

### 3. Products/Programs (`{tenantId}_product` → `products`)

#### Core Product Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `products.id` | Direct | Primary key |
| `name` | `products.name` | Direct | |
| `description` | `products.description` | Direct | |
| `price` | `products.price` | Multiply by 100 | Convert decimal to cents |
| `type` | `products.type` | Map to ProductType enum | See enum mapping below |
| `billing_cycle` | `products.billing_cycle` | Map to enum | monthly, quarterly, yearly |
| `status` | `products.status` | Map: 1→active, 0→inactive | ProductStatus enum |
| `priority` | `products.priority` | Direct | ProductPriority enum |
| `created_at` | `products.created_at` | Direct | |
| `updated_at` | `products.updated_at` | Direct | |
| - | `products.tenant_id` | Set from migration context | Foreign key to `tenants.id` |

#### Product Type Mapping
| v2 Type | v3 ProductType Enum |
|---------|---------------------|
| 1 | `program` |
| 2 | `addon` |
| 3 | `fee` |
| 4 | `other` |

#### Product Metadata
Extract from `{tenantId}_product_meta` and map to `products` JSON columns or related tables.

---

### 4. Child Enrollments (`{tenantId}_child_product` → `child_enrollments`)

#### Core Enrollment Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `child_enrollments.id` | Direct | Primary key |
| `child_id` | `child_enrollments.child_id` | Direct | Foreign key |
| `product_id` | `child_enrollments.product_id` | Direct | Foreign key |
| `start_date` | `child_enrollments.start_date` | Direct | |
| `end_date` | `child_enrollments.end_date` | Direct | Nullable |
| `status` | `child_enrollments.status` | Map to ChildEnrollmentStatus | See mapping below |
| `price` | `child_enrollments.price` | Multiply by 100 | Convert to cents |
| `billing_cycle` | `child_enrollments.billed_every` | Map to ChildEnrollmentBilledEvery | monthly, quarterly, yearly |
| `discount` | `child_enrollments.discount` | Multiply by 100 | Convert to cents |
| `created_at` | `child_enrollments.created_at` | Direct | |
| `updated_at` | `child_enrollments.updated_at` | Direct | |
| - | `child_enrollments.tenant_id` | Set from migration context | Foreign key |
| - | `child_enrollments.centre_id` | Map from child's centre | Foreign key |
| - | `child_enrollments.type` | Determine from product | ChildEnrollmentType enum |

#### Enrollment Status Mapping
| v2 Status | v3 ChildEnrollmentStatus Enum |
|-----------|-------------------------------|
| 1 | `active` |
| 2 | `suspended` |
| 3 | `cancelled` |
| 0 | `inactive` |

---

### 5. Invoices (`{tenantId}_invoices` → `invoices`, `invoice_items`)

#### Core Invoice Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `invoices.id` | Direct | Primary key |
| `invoice_number` | `invoices.invoice_number` | Direct | Unique identifier |
| `child_id` | `invoices.child_id` | Direct | Foreign key |
| `user_id` | `invoices.user_id` | Direct | Foreign key (billed to) |
| `issue_date` | `invoices.issue_date` | Direct | |
| `due_date` | `invoices.due_date` | Direct | |
| `total` | `invoices.total` | Multiply by 100 | Convert to cents |
| `discount` | `invoices.discount` | Multiply by 100 | Convert to cents |
| `tax` | `invoices.tax` | Multiply by 100 | Convert to cents |
| `subtotal` | `invoices.subtotal` | Multiply by 100 | Convert to cents |
| `status` | `invoices.status` | Map to InvoiceStatus enum | See mapping below |
| `notes` | `invoices.notes` | Direct | |
| `created_at` | `invoices.created_at` | Direct | |
| `updated_at` | `invoices.updated_at` | Direct | |
| - | `invoices.tenant_id` | Set from migration context | Foreign key |
| - | `invoices.centre_id` | Map from child's centre | Foreign key |

#### Invoice Status Mapping
| v2 Status | v3 InvoiceStatus Enum |
|-----------|----------------------|
| 1 | `draft` |
| 2 | `sent` |
| 3 | `paid` |
| 4 | `overdue` |
| 5 | `cancelled` |
| 6 | `partially_paid` |

#### Invoice Items
For each invoice in v2, create corresponding `invoice_items` records:

| v2 Source | v3 Table.Field | Transformation | Notes |
|-----------|----------------|----------------|-------|
| Invoice line items | `invoice_items.invoice_id` | Direct | Foreign key |
| - | `invoice_items.description` | From product/enrollment | |
| - | `invoice_items.quantity` | Usually 1 | |
| - | `invoice_items.unit_price` | Multiply by 100 | From enrollment price |
| - | `invoice_items.total` | Multiply by 100 | Calculated |
| - | `invoice_items.type` | Determine from source | InvoiceItemType enum |
| - | `invoice_items.child_enrollment_id` | Link to enrollment | If applicable |

---

### 6. Payments (`{tenantId}_transactions` → `payments`)

#### Core Payment Data
| v2 Field | v3 Table.Field | Transformation | Notes |
|----------|----------------|----------------|-------|
| `id` | `payments.id` | Direct | Primary key |
| `reference_number` | `payments.reference_number` | Direct | |
| `amount` | `payments.amount` | Multiply by 100 | Convert to cents |
| `payment_date` | `payments.paid_at` | Direct | |
| `payment_method` | `payments.gateway` | Map to Gateway enum | See mapping below |
| `status` | `payments.status` | Map to PaymentStatus enum | See mapping below |
| `notes` | `payments.notes` | Direct | |
| `transaction_id` | `payments.transaction_id` | Direct | Gateway transaction ID |
| `created_at` | `payments.created_at` | Direct | |
| `updated_at` | `payments.updated_at` | Direct | |
| - | `payments.tenant_id` | Set from migration context | Foreign key |
| - | `payments.user_id` | Map from invoice | Foreign key |

#### Payment Status Mapping
| v2 Status | v3 PaymentStatus Enum |
|-----------|----------------------|
| 1 | `pending` |
| 2 | `completed` |
| 3 | `failed` |
| 4 | `refunded` |
| 5 | `cancelled` |

#### Payment Gateway Mapping
| v2 Method | v3 Gateway Enum |
|-----------|----------------|
| cash | `cash` |
| bank_transfer | `bank_transfer` |
| credit_card | `credit_card` |
| stripe | `stripe` |
| paypal | `paypal` |
| other | `other` |

#### Invoice-Payment Relationships
Create records in `invoice_payment` pivot table:
- `invoice_id` → From `{tenantId}_invoice_payment` mapping
- `payment_id` → `payments.id`
- `amount` → Multiply by 100 (portion of payment applied to this invoice)

---

### 7. Supporting Entities

#### Tenants (`tenants`)
Create tenant records if they don't exist:
- Map from v2 tenant ID (prefix in table names)
- Set `name`, `domain`, `status`
- Generate unique `slug`

#### Centres/Campuses (`centres`)
Map from `{tenantId}_preschool` or `{tenantId}_campus`:
- `name` → `centres.name`
- `address` fields → `centres` address columns
- `tenant_id` → Foreign key
- `status` → active/inactive

#### Classrooms (`classrooms`)
Map from `{tenantId}_classroom`:
- `name` → `classrooms.name`
- `capacity` → `classrooms.capacity`
- `centre_id` → Map from preschool
- `tenant_id` → Foreign key

---

## Data Transformation Rules

### Monetary Values
```php
// v2: decimal(10,2)
$v2_amount = 99.99;

// v3: integer (cents)
$v3_amount = (int)($v2_amount * 100); // 9999
```

### Dates
```php
// Handle invalid dates from v2
if ($v2_date === '0000-00-00' || $v2_date === null) {
    $v3_date = null;
} else {
    $v3_date = Carbon::parse($v2_date);
}
```

### Status Codes to Enums
```php
// Example: Invoice status
$statusMap = [
    1 => InvoiceStatus::Draft,
    2 => InvoiceStatus::Sent,
    3 => InvoiceStatus::Paid,
    4 => InvoiceStatus::Overdue,
    5 => InvoiceStatus::Cancelled,
];

$v3_status = $statusMap[$v2_status] ?? InvoiceStatus::Draft;
```

### Gender Codes
```php
$genderMap = [
    'M' => 'male',
    'F' => 'female',
    'O' => 'other',
];

$v3_gender = $genderMap[$v2_gender] ?? null;
```

---

## Migration Sequence

Execute migrations in this order to maintain referential integrity:

1. **Tenants** - Create tenant records
2. **Campuses/Centres** - Migrate centre data
3. **Centres** - Migrate centre/preschool data
4. **Users** - Migrate users, profiles, addresses, office info
5. **Tenant-User Relationships** - Create pivot table records
6. **Children** - Migrate child records
7. **Child-User Relationships** - Create parent/guardian associations
8. **Products** - Migrate programs and products
9. **Child Enrollments** - Migrate enrollment records
10. **Invoices & Invoice Items** - Migrate billing data
11. **Payments** - Migrate transaction records
12. **Invoice-Payment Relationships** - Link payments to invoices

---

## Validation Queries

### Check User Migration
```sql
-- v2 count
SELECT COUNT(*) FROM 1_users WHERE status = 1;

-- v3 count
SELECT COUNT(*) FROM users WHERE tenant_id = 1;
```

### Check Invoice Totals
```sql
-- v2 total revenue
SELECT SUM(total) FROM 1_invoices WHERE status = 3;

-- v3 total revenue (remember: divide by 100)
SELECT SUM(total) / 100 FROM invoices WHERE tenant_id = 1 AND status = 'paid';
```

### Check Child Enrollments
```sql
-- v2 active enrollments
SELECT COUNT(*) FROM 1_child_product WHERE status = 1;

-- v3 active enrollments
SELECT COUNT(*) FROM child_enrollments 
WHERE tenant_id = 1 AND status = 'active';
```

### Verify Relationships
```sql
-- Check orphaned children (no parents)
SELECT c.id, c.first_name, c.last_name 
FROM children c
LEFT JOIN child_user cu ON c.id = cu.child_id
WHERE cu.id IS NULL AND c.tenant_id = 1;

-- Check payments without invoices
SELECT p.id, p.reference_number 
FROM payments p
LEFT JOIN invoice_payment ip ON p.id = ip.payment_id
WHERE ip.id IS NULL AND p.tenant_id = 1;
```

---

## Special Considerations

### Duplicate Emails
v2 may have duplicate emails across tenants. v3 requires unique emails globally:
```php
// Check for duplicates
$email = $legacyUser->email;
if (User::where('email', $email)->exists()) {
    // Append tenant ID: user@example.com → user+tenant1@example.com
    $email = str_replace('@', "+tenant{$tenantId}@", $email);
}
```

### Soft Deletes
v2 uses `status = 0` for deleted records. In v3:
```php
if ($v2Record->status == 0) {
    $v3Record->delete(); // Sets deleted_at timestamp
}
```

### Missing Parent Records
Some child records may reference non-existent parents:
```php
// Before creating child_user relationship
if (!User::where('id', $parentId)->exists()) {
    // Log warning or create placeholder user
    Log::warning("Child {$childId} references missing parent {$parentId}");
}
```

### JSON Data
v2 meta tables store key-value pairs. For v3:
```php
// Collect all meta for a record
$meta = DB::connection('legacy')
    ->table("{$tenantId}_users_meta")
    ->where('user_id', $userId)
    ->pluck('meta_value', 'meta_key')
    ->toArray();

// Store as JSON in v3
$userProfile->custom_data = json_encode($meta);
```

---

## Error Handling

### Transaction Wrapping
```php
DB::beginTransaction();
try {
    // Migrate user
    $user = User::create([...]);
    
    // Migrate profile
    UserProfile::create([...]);
    
    // Migrate address
    UserAddress::create([...]);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    Log::error("Migration failed for user {$legacyUserId}: " . $e->getMessage());
}
```

### Data Quality Checks
```php
// Validate before migration
if (empty($legacyUser->email) || !filter_var($legacyUser->email, FILTER_VALIDATE_EMAIL)) {
    Log::warning("Invalid email for user {$legacyUser->id}");
    // Skip or use placeholder email
}

// Validate amounts
if ($legacyInvoice->total < 0) {
    Log::error("Negative invoice total for invoice {$legacyInvoice->id}");
    // Handle appropriately
}
```

---

## Performance Optimization

### Chunking Large Tables
```php
DB::connection('legacy')
    ->table("{$tenantId}_users")
    ->orderBy('id')
    ->chunk(500, function ($users) use ($tenantId) {
        foreach ($users as $user) {
            // Process each user
        }
    });
```

### Disable Timestamps Temporarily
```php
// During bulk inserts
Model::unguarded(function () {
    Model::insert($bulkData);
});
```

### Index Creation
After migration, ensure v3 has proper indexes:
```sql
CREATE INDEX idx_children_tenant ON children(tenant_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_payments_user ON payments(user_id, tenant_id);
```

---

## Post-Migration Checklist

- [ ] Verify record counts match between v2 and v3
- [ ] Check referential integrity (no orphaned records)
- [ ] Validate calculated totals (invoices, payments)
- [ ] Test user authentication with migrated accounts
- [ ] Verify file attachments/avatars are accessible
- [ ] Confirm date ranges are correct (no future dates where inappropriate)
- [ ] Test application functionality with migrated data
- [ ] Run automated tests against migrated data
- [ ] Backup migrated v3 database
- [ ] Update application configs if needed
- [ ] Monitor error logs for migration-related issues

---

## Command Usage

```bash
# Dry run for specific tenant
php artisan migrate:legacy-data --tenant-id=1 --dry-run

# Migrate specific table
php artisan migrate:legacy-data --tenant-id=1 --table=users

# Full migration with custom chunk size
php artisan migrate:legacy-data --tenant-id=1 --chunk-size=1000

# Migrate all data for tenant
php artisan migrate:legacy-data --tenant-id=1
```

---

## References

- v2 Schema: `database/schema/mysql-v2-schema.sql`
- v3 Models: `app/Models/`
- Migration Command: `app/Console/Commands/MigrateLegacyData.php`
- Enums: `app/Enums/`

---

**Last Updated**: December 15, 2025
**Migration Command Version**: 1.0
**Target Laravel Version**: 11.x
