# KindyGo Legacy Data Mapping

> **EDITABLE DOCUMENT** - Review and modify mappings before implementation  
> **Last Updated**: February 2025  
> **Status**: Reviewed

---

## How to Use This Document

### Status Markers

Update these as you review each section:

- `[ ]` = Not reviewed
- `[x]` = Reviewed & approved
- `[~]` = Needs adjustment (add notes)
- `[SKIP]` = Don't migrate this table/field

### Making Changes

1. Edit field mappings in the tables below
2. Add notes in the "Notes" column
3. Mark TODO items as resolved
4. Commit changes to git
5. AI will implement based on your specifications

---

## Table of Contents

1. [Foundation Tables](#1-foundation-tables)
2. [Users](#2-users)
3. [Children & Enrollments](#3-children--enrollments)
4. [Products](#4-products)
5. [Financial Data](#5-financial-data)
   - [5.5 Quotations](#55-quotations)
6. [Relationships](#6-relationships)
7. [Media Files](#7-media-files)
   - [7.1 Child Media](#71-child-media)
   - [7.2 Transaction Media](#72-transaction-media-payment-proof)
   - [7.3 User Media](#73-user-media)
   - [7.4 Family Member Media](#74-family-member-media)
   - [7.5 ChildLog Media](#75-childlog-media)
   - [7.6 Generated and External Files](#76-generated-and-external-files)
8. [Lookup Tables](#8-lookup-tables)

---

## 1. Foundation Tables

### 1.1 Tenant Assignment

**Review Status**: [x]

All legacy data will be assigned to a single tenant:

| Setting | Value | Notes |
|---------|-------|-------|
| Target Tenant ID | `1` | <!-- TODO: Confirm this is admin-tenant --> (resolved) |
| Tenant Name | `admin-tenant` | |

---

### 1.2 `1_campuses` → `campuses`

**Review Status**: [x]

**Source**: `kindygo.1_campuses` (Legacy)
**Target**: `kindygo_app.campuses` (Current)
**ID Preservation**: YES

| Legacy Field | Target Field | Transform | Notes |
|--------------|--------------|-----------|-------|
| `id` | `id` | Direct | Preserve IDs 1–6 so valid preschool foreign keys remain valid. |
| - | `tenant_id` | Set | Target tenant ID. |
| `name` | `name` | Direct | |
| `no_phone` | `phone` | Direct | Empty values become null. |
| `add_1`, `add_2`, `postcode`, `city` | Address fields | Direct | Empty values become null. |
| `state` | `state` | Map | Map legacy state ID to its Malaysian state value. |
| `short_name`, `status`, `ssm_comp_name`, `ssm_no` | `meta_data` | Preserve | Store with `legacy_source_table: 1_campuses` and `legacy_id`. |
| `created_at`, `updated_at` | Timestamps | Direct | Use the migration timestamp when `created_at` is null. |
| `deleted_at` | - | Filter | Skip when not null. |

### 1.3 `1_preschool` → `centres`

**Review Status**: [x]

**Source**: `kindygo.1_preschool` (Legacy)  
**Target**: `kindygo_app.centres` (Current)  
**ID Preservation**: YES

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `name` | varchar | `name` | varchar | Direct | |
| `short_name` | varchar | `code` | varchar | Direct | <!-- TODO: Verify uniqueness (Answer: Yes) --> |
| - | - | `slug` | varchar | Generate | From `short_name` or `name` |
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `campus_id` | int | `campus_id` | bigint | Map | IDs 1–6 map directly. For `0` or null, create one generated campus from this preschool and link it. A missing positive ID is logged and maps to null. |
| `add_1` | varchar | `address_1` | varchar | Direct | |
| `add_2` | varchar | `address_2` | varchar | Direct | |
| `postcode` | varchar | `postal_code` | varchar | Direct | |
| `city` | varchar | `city` | varchar | Direct | |
| `state` | int | `state` | varchar | Map | Map legacy state id to string (Refer 1_state table from legacy DB) |
| `no_phone` | varchar | `phone` | varchar | Direct | |
| - | - | `email` | varchar | Set | null or generate |
| `status` | varchar | `status` | enum | Map | 'active'→'active' |
| `ssm_comp_name` | varchar | `meta_data` | json | Map | Store in meta_data.ssm_comp_name (Refer below) |
| `ssm_no` | varchar | `meta_data` | json | json | Store in meta_data.ssm_no (Refer below) |
| `capacity` | int | `meta_data` | json | json | Store in meta_data.capacity (Refer below) |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### 1.3.1 Preschool `meta_data`

Store other legacy user data in `meta_data` JSON:

```json
{
    "legacy_ssm_comp_name": "Company Name",
    "legacy_ssm_no": "1234",
    "legacy_capacity": 58
}
```

---

## 2. Users

### 2.1 `1_users` → `users`

**Review Status**: [x]

**Source**: `kindygo.1_users` (Legacy)  
**Target**: `kindygo_app.users` (Current)  
**ID Preservation**: YES

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `name` | varchar | `name` | varchar | Direct | |
| `email` | varchar | `email` | varchar | Direct | Unique constraint |
| `email_verified_at` | timestamp | `email_verified_at` | timestamp | Direct | |
| `password` | varchar | `password` | varchar | Direct | Already hashed |
| `remember_token` | varchar | `remember_token` | varchar | Direct | |
| `user_status` | int | - | - | → meta_data | See below |
| `campus` | int | - | - | Skip | All active legacy values are null; campus membership is derived from the preschool mapping. |
| `preschool` | int | `tenant_user.current_centre_id`, `centre_user.centre_id` | bigint | Map | Set the current centre and attach the user to that centre. |
| `other_preschools` | JSON | `centre_user.centre_id` | bigint | Map | Attach each valid referenced centre. |
| `classroom_id` | bigint | - | - | Skip | Future: classrooms |
| `user_token` | longtext | - | - | Skip | |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### 2.1.1 User Status → meta_data

Store legacy user type in `meta_data` JSON:

```json
{
  "legacy_user_status": 1,
  "legacy_user_status_name": "Normal",

}
```

**User Status Mapping**:

| Legacy ID | Legacy Name | Notes |
|-----------|-----------|-------|
| 1 | Normal | Regular parent |
| 2 | Staff | Staff parent (waived monthly) |
| 3 | Family | Family parent (waived monthly, annual) |

---

### 2.2 User Discount Config → `users.meta_data`

**Review Status**: [x]

Store all discount configuration in `meta_data` for audit trail:

| Legacy Field | Store As | Notes |
|--------------|----------|-------|
| `discount_by_month` | `meta_data.discount_config.discount_by_month` | JSON/longtext |
| `discount_by_month_amount` | `meta_data.discount_config.discount_by_month_amount` | |
| `discount_by_month_reason` | `meta_data.discount_config.discount_by_month_reason` | |
| `discount_by_month_year` | `meta_data.discount_config.discount_by_month_year` | JSON/longtext |
| `monthly_discount_amount` | `meta_data.discount_config.monthly_discount_amount` | |
| `monthly_discount_reason` | `meta_data.discount_config.monthly_discount_reason` | |
| `discount_histories` | `meta_data.discount_config.discount_histories` | JSON/longtext |

**Example meta_data structure**:
```json
{
  "legacy_user_status": 2,
  "legacy_user_status_name": "Staff",
  "user_type": "staff",
  "discount_config": {
    "discount_by_month": ["1", "2", "3"],
    "discount_by_month_amount": "100",
    "discount_by_month_reason": "Staff discount",
    "discount_by_month_year": ["2024", "2025"],
    "monthly_discount_amount": "50",
    "monthly_discount_reason": "Sibling discount",
    "discount_histories": [...]
  },
  "legacy_id": 123
}
```

<!-- TODO: Verify discount_by_month format - is it JSON array of months? -->
It's JSON array of months.

---

### 2.3 User Profile Fields → `user_profiles`

**Review Status**: [x]

| Legacy Field | Target Field | Notes |
|--------------|--------------|-------|
| `ic_no` | `nric` | |
| `phone_no` | `phone` | |
| `occupation` | `occupation` | |

---

### 2.4 User Address → `user_addresses`

**Review Status**: [x]

**Primary Address**:

| Legacy Field | Target Field | Notes |
|--------------|--------------|-------|
| `add_1` | `address` | |
| `add_2` | `address_2` | |
| `city` | `city` | |
| `postcode` | `postal_code` | |
| `state` | `state_code` | Refer below |

#### 2.4.1 User Address `state` -> `state_code`

**Legacy table `1_state`:**
| id | Name |
|-------|-------|
| 1 | `Johor` |

The column `state` in legacy table `1_users` store the id for table `1_state`. We need to map these into current system `MalaysianState` enum, and store the enum value in the `state_code` from table `user_addresses`.

**Company Address**

| Legacy Field | Target Field | Notes |
|--------------|--------------|-------|
| `company_name` | → `user_office_infos.office_name` | |
| `company_phone` | → `user_office_infos.office_phone` | |
| `company_add_1` | `user_office_infos.office_address` | |
| `company_add_2` | `user_office_infos.office_address_2` | |
| `company_city` | `user_office_infos.office_city` | |
| `company_postcode` | `user_office_infos.office_postal_code` | |
| `company_state` | `user_office_infos.office_state_code` | Use MalaysianState enum for `office_state_code` |

---

### 2.5 Spouse Data

**Review Status**: [x]

The spouse data will go to table `family_members` and it will link to user through `user_id` column.

| Legacy Field | Target | Notes |
|--------------|--------|-------|
| `spouse_name` | `family_members.name` | Create as FamilyMember and link to User |
| `spouse_ic_no` | `family_members.nric` | |
| `spouse_phone_no` | `family_members.phone` | |
| `spouse_email` | `family_members.email` | |
| `spouse_occupation` | `family_members.occupation` | |
| `spouse_add_1` | `family_members.address` | |
| `spouse_add_2` | `family_members.address_2` | |
| `spouse_city` | `family_members.city` | |
| `spouse_postcode` | `family_members.postal_code` | |
| `spouse_state` | `family_members.state_code` | Map legacy into MalaysianState enum |
| `spouse_company_add_1` | `family_members.office_address` | |
| `spouse_company_add_2` | `family_members.office_address_2` | |
| `spouse_company_city` | `family_members.office_city` | |
| `spouse_company_postcode` | `family_members.office_postal_code` | |
| `spouse_company_state` | `family_members.office_state_code` | Map legacy into MalaysianState enum |

---

### 2.6 `1_model_has_roles` → `model_has_roles`

**Review Status**: [x]

**Source**: `kindygo.1_model_has_roles`  
**Target**: `kindygo_app.model_has_roles`

| Legacy Field | Target Field | Transform | Notes |
|--------------|--------------|-----------|-------|
| `role_id` | `role_id` | Map | Resolve the mapped target role by name and store its target role ID |
| `model_type` | `model_type` | Update | `App\Models\User` |
| `model_id` | `model_id` | Direct | User ID |

**Role Mapping** (only roles, skip permissions):

| Legacy Role ID | Legacy Name | Target Role | Notes |
|----------------|-------------|-------------|-------|
| 1 | Super Admin | `super-admin` | |
| 2 | HQ Admin | `admin` | |
| 3 | HQ Accountant | `accountant` | |
| 4 | School Principal | `principal` | |
| 5 | School Accountant | `accountant` | |
| 6 | School Teacher | `teacher` | |
| 7 | Parent | `parent` | |
| 8 | Safety Officer | `staff` | |
| 9 | Occupational Therapist | `staff` | |
| 10 | Application | Skip | System role |
| 11 | Auditor | `auditor` | |
| 12 | School Owner | `owner` | |

### 2.7 User Guardians → `users.meta_data`

Store legacy user guardians in `meta_data` JSON:

```json
{
  "legacy_guardians": [...],
}
```

---

## 3. Children & Enrollments

### 3.1 `1_child` → `children` (Profile Only)

**Review Status**: [x]

**Source**: `kindygo.1_child` (Legacy)  
**Target**: `kindygo_app.children` (Current)  
**ID Preservation**: YES

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `fullname` | varchar | `first_name`, `last_name` | varchar | Split | See logic below |
| - | - | `patronymic` | varchar | Set | null |
| `mykid_no` | varchar | `mykid_no` | varchar | Direct | |
| `cert_no` | varchar | `cert_number` | varchar | Direct | |
| `pob` | varchar | `place_of_birth` | varchar | Direct | |
| `dob` | datetime | `date_of_birth` | date | Convert | Date only |
| `gender` | int | `gender` | varchar | Map | 1→'male', 2→'female' |
| `post_of_child` | int | `position_of_child` | int | Direct | |
| `race` | int | `race` | varchar | Lookup | From `1_race` table |
| `religion` | int | `religion` | varchar | Lookup | From `1_religion` table |
| `languages` | longtext | `languages` | json | Parse | |
| `allergies` | varchar | `allergies` | json | Wrap | `["value"]` |
| `diseases` | longtext | `diseases` | json | Parse | |
| `family_clinic` | varchar | `family_clinic` | varchar | Direct | |
| `family_clinic_phone` | varchar | `family_clinic_phone` | varchar | Direct | |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | `deleted_at` | timestamp | Direct | Preserve soft deletes |

**Fields NOT migrated to children** (→ child_enrolments):
- `status`
- `product`
- `december_product_id` (Skip)
- `other_products`
- `preschool_id`
- `classroom_id` (Skip)
- `year`
- `is_registered` (Skip)
- `alumni` (Skip)
- `type` (Skip)
- `discount` (Skip)

#### Name Splitting Logic

```php
// Split "fullname" into first_name and last_name
function splitName(string $fullname): array
{
    $parts = explode(' ', trim($fullname));
    
    if (count($parts) === 1) {
        return ['first_name' => $parts[0], 'last_name' => ''];
    }
    
    $lastName = array_pop($parts);
    $firstName = implode(' ', $parts);
    
    return ['first_name' => $firstName, 'last_name' => $lastName];
}
```

---

### 3.2 `1_child` → `child_enrolments` (CRITICAL)

**Review Status**: [x]

**Source Fields for Enrollment**:

| Legacy Field | Creates Enrollment | Condition | Notes |
|--------------|-------------------|-----------|-------|
| `product` | YES - Row 1 | Always | |

#### Enrollment Record Structure

For each product, create:

| Target Field | Source | Transform | Notes |
|--------------|--------|-----------|-------|
| `id` | - | Auto | New ID |
| `tenant_id` | - | Set | Target tenant ID |
| `centre_id` | `preschool_id` | Direct | Maps to centres.id |
| `child_id` | `id` | Direct | Child ID |
| `product_id` | `product` / `other_products[n]` | Direct | Product ID |
| `status` | `status` | Map | See status mapping |
| `billed_every` | - | Set | 'monthly' default |
| `date_start` | At 24th of current month | Generate | If the legacy child status is active related typed |
| `date_end` | - | Set | null |
| `next_bill_date` | - | Set | null |
| `type` | `type` | Map | 'full_time', 'trial' |
| `additional_products` | Map | See additional products mapping | null |
| `created_at` | `created_at` | Direct | |
| `updated_at` | `updated_at` | Direct | |

#### Child Status → Enrollment Status Mapping

| Legacy `status` | Legacy Name | Target `ChildEnrolmentStatus` | Notes |
|-----------------|-------------|-------------------------------|-------|
| 1 | New Children | `ACTIVE` | |
| 2 | Return Children | `ACTIVE` | |
| 3 | Alumni | `COMPLETED` | |
| 4 | Future | `PENDING` | |
| 5 | Future (Return) | `LEGACY_FUTURE_RETURN` | |
| 6 | Suspended | `LEGACY_SUSPENDED` | |
| 7 | Registered | `LEGACY_REGISTERED` | |
| 8 | Unregistered | `LEGACY_UNREGISTERED` | |
| 9 | Trial (1 Month) | `LEGACY_TRAIL_1_MONTH` | |
| 10 | Cancelled | `CANCELLED` | |
| 11 | Trial (5 Days) | `LEGACY_TRAIL_5_DAYS` | |

#### Additional Products Mapping
| Legacy `other_products` | Target `additional_products` | Notes |
|---------------|----------------------------|-------|
| integer (product_id) | JSON | Store product_id in the json, and fill the remaining field with child_enrolments data. [{"notes": null, "date_end": null, "date_start": "2026-02-09 01:02:29", "product_id": 2, "billed_every": "monthly"}]. The legacy data of other_products contain array of integer, map the integer into the specify JSON structure. |


#### Enrollment Type Mapping

| Legacy `type` | Target `ChildEnrolmentType` | Notes |
|---------------|----------------------------|-------|
| null | `FULL_TIME` | Default |
| 'regular' | `FULL_TIME` | |
| 'trial' | `TRIAL` | |

---

### 3.3 Child Pivot Tables

**Review Status**: [x]

#### `tenant_child` (Child belongs to Tenant)

| Target Field | Source | Notes |
|--------------|--------|-------|
| `tenant_id` | Set | Target tenant ID |
| `child_id` | `1_child.id` | |
| `status` | `1_child.status` | Map to ChildStatus enum |

**ChildStatus Mapping** (for tenant_child pivot):

| Legacy | Target `ChildStatus` | Notes |
|--------|---------------------|-------|
| 1 | `NEW` | New |
| 2 | `RETURN` | Return |
| 3 | `ALUMNI` | Alumni |
| 4 | `FUTURE` | Future |
| 5 | `FUTURE_RETURN` | Future Return |
| 6 | `SUSPENDED` | Suspended |
| 7 | `REGISTERED` | Registered |
| 8,10 | `INACTIVE` | Inactive |
| 9 | `TRAIL_1_MONTH` | Trail 1 month |
| 11 | `TRAIL_5_DAYS` | Trail 5 days |

#### `centre_child` (Child at Centre)

| Target Field | Source | Notes |
|--------------|--------|-------|
| `centre_id` | `1_child.preschool_id` | |
| `child_id` | `1_child.id` | |

---

## 4. Products

### 4.1 `1_product` → `products`

**Review Status**: [x]

**Source**: `kindygo.1_product` (Legacy)  
**Target**: `kindygo_app.products` (Current)  
**ID Preservation**: YES

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `name` | varchar | `name` | varchar | Direct | |
| - | - | `code` | varchar | Generate | From name or null |
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `status` | varchar | `status` | varchar | Map | 'active'→'active' |
| `product_type` | int | `type` | varchar | Map | See mapping |
| - | - | `priority` | int | Set | 0 or based on type |
| `price` / `year` | int / varchar | → `product_prices` | | See 4.2 | |
| `price_history` | longtext | → `product_prices` | | See 4.3 | |
| `preschool` | longtext | - `product_centre` | | See 4.4 | JSON of preschool IDs |
| `recurrence` | int | - | | Skip | |
| `recurrence_months` | longtext | - | | Skip | |
| `remarks` | varchar | `description` | longtext | | |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### Product Type Mapping

| Legacy `product_type` | Legacy Name | Target `ProductType` | Notes |
|-----------------------|-------------|---------------------|-------|
| 1 | Programme | `PROGRAMME` | Main educational product |
| 2 | Events | `EVENT` | |
| 3 | Merchandise | `MERCHANDISE` | |
| 4 | Over Time | `OVERTIME` | |
| 5 | Stay In | `STAYIN` | |
| 6 | Service/Fee | `SERVICE` | |
| 7 | Deposit | `DEPOSIT` | |

---

### 4.2 `1_product.price` → `product_prices`

**Review Status**: [x]

| Target Field | Source | Notes |
|--------------|--------|-------|
| `id` | Auto | |
| `product_id` | `1_product.id` | |
| `price` | `1_product.price` | Legacy whole-RM value; multiply by 100 to store cents |
| `start_date` | `1_product.year` | Parse into carbon object and make it at the beginning of the year |
| `end_date` | Calculated | `31 December` of the year when a later price exists; otherwise null |
| `created_at` | `1_product.created_at` | |
| `updated_at` | `1_product.updated_at` | |

### 4.3 `1_product.price_history` -> `product_prices`

**Review Status**: [x]

| Target Field | Source | Notes |
|--------------|--------|-------|
| `id` | Auto | |
| `product_id` | `1_product.id` | |
| `price` | `1_product.price_history[n]['price']` | Legacy whole-RM value; multiply by 100 to store cents |
| `start_date` | `1_product.price_history[n]['year']` | Parse into carbon object and make it at the beginning of the year |
| `end_date` | Calculated | `31 December` of the year when a later price exists; otherwise null |
| `created_at` | `1_product.created_at` | |
| `updated_at` | `1_product.updated_at` | |

### 4.4 Map `1_product` to `product_centre`

**Review Status**: [x]

| Target Field | Source | Notes |
|--------------|--------|-------|
| `id` | Auto | |
| `product_id` | `1_product.id` | |
| `centre_id` | `1_product.preschool[n]` |  |
| `created_at` | `1_product.created_at` | |

---

## 5. Financial Data

### 5.1 `1_invoices` → `invoices`

**Review Status**: [x]

**Source**: `kindygo.1_invoices` (Legacy)  
**Target**: `kindygo_app.invoices` (Current)  
**ID Preservation**: YES

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_no` | varchar | `number` | varchar | Map the string format: replace empty space with `-`  | |
| `parent` | int | `user_id` | bigint | Direct | FK to users |
| `preschool` | int | `centre_id` | bigint | Direct | FK to centres |
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `invoice_date` | datetime | `date` | datetime | Direct | |
| `due_date` | date | `due_at` | datetime | Convert | Add time |
| `payment_status` | int | `status` | varchar | Map | See mapping |
| `price` | int | `total` | int | Direct | Total amount |
| - | - | `total_items` | int | Calculate | Sum of items |
| - | - | `total_discounts` | int | Calculate | Sum of discounts |
| - | - | `total_amount` | int | Calculate | Items - Discounts |
| `child_id` | int | - | - | → invoice_items | |
| `locked` | tinyint | - | - | Skip | |
| `deposit` | int | - | - | Meta | |
| `is_pos_invoice` | tinyint | - | - | Meta | |
| `is_enrollment` | tinyint | - | - | Meta | |
| `billplz_*` | varchar | - | - | Meta | |
| `last_mailgun_message_id` | varchar | - | - | Meta | |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### Invoice Status Mapping

| Legacy `payment_status` | Legacy Name | Target `InvoiceStatus` | Notes |
|------------------------|-------------|------------------------|-------|
| 1 | Pending Payment | `PENDING` | |
| 2 | Overdue | `OVERDUE` | |
| 3 | Partially Paid | `PARTIALLY_PAID` | |
| 4 | Processing | `PENDING` | |
| 5 | On Hold | `CANCELLED` | |
| 6 | Carried Forward | `PENDING` | |
| 7 | Completed | `PAID` | |
| 8 | Refunded | `REFUNDED` | |
| 9 | Cancel | `CANCELLED` | |
| 10 | Completed With Excess Payment | `PAID` | Store excess in meta |
| 11 | Completed With Deposit | `PAID` | Store deposit in meta |
| 12 | Draft | `DRAFT` | |

---

### 5.2 `1_transactions` → `invoice_items`

**Review Status**: [x]

**Source**: `kindygo.1_transactions` (type = 'bill') (Legacy)  
**Target**: `kindygo_app.invoice_items` (Current)
**ID Preservation**: YES
**Legacy table Filter**: type = bill

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_id` | int | `invoice_id` | bigint | Direct | |
| `product_id` | int | `product_id` | bigint | Direct | |
| `child_id` | int | `child_id` | bigint | Direct | |
| - | - | `child_enrolment_id` | bigint | Lookup | Match child+product |
| `label` | varchar | `name` | varchar | Direct | |
| - | - | `description` | text | Set | null |
| `amount` | int | `price` | int | Direct | |
| `quantity` | int | `quantity` | int | Direct | |
| `discount_amount` | int | `discount` | int | Direct | |
| - | - | `total` | int | Calculate | price * qty - discount |
| - | - | `period_start` | datetime | From invoice | invoice.start_date |
| - | - | `period_end` | datetime | From invoice | invoice.end_date |
| - | - | `type` | enum | Set | 'product' or 'invoice_discount' |
| - | - | `paid_amount` | int | Calculate | From invoice.payments |
| - | - | `balance_amount` | int | Calculate | From invoice.payments, use allocatePaymentToInvoices action |
| - | - | `paid` | tinyint | Calculate | 1 if fully paid |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### Discount Handling

**Legacy discount format** (from discussions):
- Negative `amount` values = discount
- `discount_amount` column (if exists)
- Watch `quantity` column

**Strategy**:
1. Check for negative amounts → create discount item
2. Check for discount_amount column → create discount item
3. Historical discounts preserved through invoice_items

---

### 5.3 `1_transactions` → `payments`

**Review Status**: [x]

**Source**: `kindygo.1_transactions` (type = 'payment') (Legacy)  
**Target**: `kindygo_app.payments` (Current)  
**ID Preservation**: YES
**Legacy table Filter**: `type = 'payment'` and `deleted_at IS NULL`

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_id` | int | - | - | → pivot | invoice_payment pivot |
| `parent_id` | int | `user_id` | bigint | Direct | |`
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `paid_amount` | int | `amount` | int | Direct | |
| `payment_method` | int | `gateway` | varchar | Map | See mapping |
| `payment_method` | int | `method` | varchar | Map | See mapping |
| `paid_at` | timestamp | `paid_at` | timestamp | Direct | |
| `billplz_bill_id` | string | `gateway_transaction_id` | string | Direct | To string |
| `billplz_collection_id` | string | `gateway_account_id` | string | To string |
| - | - | `gateway_payment_data` | Set | null | Reserved for provider payloads retrieved after migration. |
| `paid_status` | tinyint | `status` | varchar | Map | 1 = 'paid' & 0 = 'unpaid' |
| `reference_id` | varchar | `reference_no` | varchar | Direct | |
| `label` | varchar | `description` | text | Direct | |
| `remarks` | varchar | `meta` | JSON | Map | See mapping to meta |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### Payment Method Mapping

| Legacy `payment_method` | Legacy Name                                   | Target `gateway` | Target `method`       | Notes |
| ----------------------- | --------------------------------------------- | ---------------- | --------------------- | ----- |
| 1                       | Billplz: Online Banking (FPX)                 | `billplz`        | `online_banking_fpx`  |       |
| 2                       | CDM/Manual Transfer                           | `manual`         | `cdm_manual_transfer` |       |
| 3                       | Cheque                                        | `manual`         | `cheque`              |       |
| 4                       | CHIP: Online Banking (FPX), Visa & Mastercard | `chip`           | `online_banking_fpx`  | Provisional; replace with the provider-confirmed method when gateway data is fetched. |
| 5                       | Zakat                                         | `manual`         | `zakat`               |       |
| 6                       | Baitulmal                                     | `manual`         | `baitulmal`           |       |
| 7                       | JKM                                           | `manual`         | `jkm`                 |       |
| 8                       | ANIS                                          | `manual`         | `anis`                |       |
| 9                       | Booking (CHIP)                                | `manual`         | `chip_booking`        |       |
| 10                      | Salary Deduction                              | `manual`         | `salary_deduction`    |       |


#### Gateway and Meta Mapping

| Legacy Name | Target `meta` | Notes |
|------------------------|-------------|-------|
| `billplz_collection_id` | `gateway_collection_id` | |
| `remarks` | `remark` | |
| `payment_method` | `legacy_payment_method` | Preserve the source integer for audit and future gateway reconciliation. |

#### Payment Allocation Strategy

1. Create a `payments` record for every active legacy `payment` transaction, including unpaid attempts.
2. Map `paid_status` to `status`: `1` to `paid`; `0` to `unpaid`.
3. Create `invoice_payment` allocations only for `paid` payments with a positive `paid_amount`.
4. Recalculate invoice payment status from paid allocations only.

### 5.4 `1_transactions` Deposits → `invoice_items`

**Review Status**: [x]

**Source**: `kindygo.1_transactions` (type = 'deposit') (Legacy)  
**Target**: `kindygo_app.invoice_items` (Current)
**ID Preservation**: YES
**Legacy table Filter**: `type = 'deposit'` and `deleted_at IS NULL`

#### Legacy Booking Behaviour

Despite the legacy `deposit` transaction type, these records are not deposit receipts. They
represent booking products charged by a preschool on an invoice. A parent's payment for that
invoice is stored separately as a normal `payment` transaction. When the booking amount is
applied to a later invoice, the legacy system creates another invoice line with a negative value
to offset the booking amount already paid.

#### Migration Strategy

Migrate every active `deposit` transaction as an invoice item using the same mapping and
calculations as a `bill` transaction in section 5.2.

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_id` | int | `invoice_id` | bigint | Direct | Preserve the invoice relationship |
| `product_id` | int | `product_id` | bigint | Direct | Booking product, when present |
| `child_id` | int | `child_id` | bigint | Direct | |
| - | - | `child_enrolment_id` | bigint | Lookup | Match child and product |
| `label` | varchar | `name` | varchar | Direct | |
| `remarks` | varchar | `description` | text | Direct | |
| `amount` | int | `price` | int | Direct | Preserve the signed value |
| `quantity` | int | `quantity` | int | Direct | |
| `discount_amount` | int | `discount` | int | Direct | |
| - | - | `total` | int | Calculate | price * quantity - discount |
| - | - | `period_start` | datetime | From invoice | invoice.start_date |
| - | - | `period_end` | datetime | From invoice | invoice.end_date |
| - | - | `type` | enum | Set | `product` or `invoice_discount` using section 5.2 rules |
| - | - | `paid_amount` | int | Calculate | From invoice payments |
| - | - | `balance_amount` | int | Calculate | From invoice payments using the payment allocation action |
| - | - | `paid` | tinyint | Calculate | 1 when fully paid |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if not null |

Preserve negative `amount` values as negative invoice line items so historical booking offsets
remain intact. Do not create `payments` or `invoice_payment` records from `deposit` transactions;
only legacy `payment` transactions follow the payment migration in section 5.3.

### 5.5 Quotations

**Review Status**: [x]

| Legacy Source | Target | Transform |
|---------------|--------|-----------|
| `1_quotations.id` | `quotations.id` | Preserve ID; skip and log a target-ID conflict. |
| `quotation_no` | `number` | Preserve; use `LEGACY-QUO-{id}` when blank and add a duplicate suffix if needed. |
| `parent_id`, `preschool_id` | `user_id`, `centre_id` | Required direct references; skip and log when unresolved. |
| `date` | `date`, `valid_until` | Preserve as the issue date and set the expiry to the same timestamp. |
| - | `tenant_id`, `status` | Set target tenant and `expired`, respectively. |
| - | `converted_invoice_id`, terms, notes | Set null; the legacy schema has no reliable values. |
| `1_quotation_transactions` | `quotation_items` | Preserve IDs and map product, child, matching enrolment, label, remarks, amount, quantity, discount, and bill date. |

Quotation items are migrated as unpaid product items. Their `total` and `balance_amount` are calculated from the legacy amount, quantity, and discount. No invoice-link heuristic is applied.

---

## 6. Relationships

### 6.1 Parent-Child Relationships

**Review Status**: [x]

**Source**: `1_child.parent_id` 
**Target**: `child_user` pivot table

#### From `1_child.parent_id`

| Target Field | Source | Notes |
|--------------|--------|-------|
| `child_id` | `1_child.id` | |
| `user_id` | `1_child.parent_id` | FK to users |
| `relationship_type` | 'parent' | Default |

---

## 7. Media Files

### 7.1 Child Media

**Review Status**: [x]

**Source Location**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/children/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `passport_sized_image` | `children/{child_id}/profile/passport_sized_image.{ext}` | `children.photo` | Extension is determined by the uploaded MIME type. |
| `child_birth_certificate` | `children/{child_id}/profile/child_birth_certificate.{ext}` | `children.birth_certificate` | Extension is determined by the uploaded MIME type. |
| `immunization_card` | `children/{child_id}/profile/immunization_card.{ext}` | `children.immunization_card` | Extension is determined by the uploaded MIME type. |

#### Migration Strategy for Child Media

1. **Source Path**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/children/{child_id}/profile/{filename}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `photo` → stores `passport_sized_image`
   - Collection: `birth_certificate` → stores `child_birth_certificate`
   - Collection: `immunization_card` → stores `immunization_card`
3. **Process**:
   - For each child, check if files exist in legacy location
   - Copy file to temp location
   - Attach to child using media library
   - Preserve original filename metadata
4. **Orphan Handling**: If child files are missing, skip silently
5. **File Format**: Accept JPG, JPEG, PNG, and PDF formats as-is


---

### 7.2 Transaction Media (Payment Proof)

**Review Status**: [x]

**Source**: `kindygo.1_transactions.payment_slip` (type = 'payment') (Legacy)

**Target**: Spatie Media Library `media` table, attached to `kindygo_app.payments`

**Legacy table Filter**: `type = 'payment'`, `deleted_at IS NULL`, and `payment_slip` is not empty

**Source Location**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/transactions/bills/payment_slips/`

Legacy payment transactions store the relative payment-proof path in `payment_slip`. Only
genuine `payment` transactions are processed in this section. As defined in section 5.4,
`deposit` transactions are booking invoice items and must not be migrated to `payments` or
have media attached to a `Payment` model.

The `payment_slip` value is only a locator for the legacy file. Migration must resolve that
value to the physical file, copy the file into Spatie Media Library, and create the corresponding
`media` database record. Storing the legacy path in `payments.meta` alone is not sufficient.

| Legacy Field | Legacy Value / File Path | New Target | Transform | Notes |
|--------------|--------------------------|------------|-----------|-------|
| `1_transactions.id` | `123` | `payments.id` | Direct | Payment migration preserves the legacy transaction ID. |
| `1_transactions.type` | `payment` | `payments.meta.legacy.transaction_type` | Preserve | Always `payment` for imported payment proof. |
| `1_transactions.payment_slip` | `/transactions/bills/payment_slips/{transaction_id}.{ext}` | `payments.meta.legacy.payment_slip_path` | Preserve | Exact legacy value used to locate the file and support audit and retries. |
| Legacy file bytes | `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/transactions/bills/payment_slips/{transaction_id}.{ext}` | Spatie `payment_proof` media | Copy and import | Copy the physical file to the private media disk and create a `media` record. |
| Legacy transaction ID | `1_transactions.id` | `media.custom_properties.legacy_transaction_id` | Preserve | Enables traceability back to the legacy record. |
| Legacy source path | `payment_slip` | `media.custom_properties.legacy_source_path` | Preserve | Store the exact legacy relative path. |
| Legacy transaction type | `type` | `media.custom_properties.legacy_transaction_type` | Preserve | Always `payment`; deposit rows are excluded. |

#### Target Media Collection

The new `Payment` model owns the media. Attach every imported proof to its existing
Spatie Media Library collection:

| Setting | Value | Notes |
|---------|-------|-------|
| Model | `App\Models\Payment` | Polymorphic owner stored in the `media` table. |
| Collection | `payment_proof` | One proof per payment. |
| Disk | `private` | Private access through the application only. |
| Collection behaviour | `singleFile()` | Re-running an import replaces the collection's existing proof. |
| Accepted MIME types | `image/jpeg`, `image/png`, `application/pdf` | Legacy uploads were JPG, JPEG, PNG, or PDF. |
| Image conversion | `thumb` | Existing payment-proof thumbnail conversion. |

#### Spatie Media Record Mapping

Each successfully resolved legacy file creates one Spatie `media` row:

| Media Field | Value | Notes |
|-------------|-------|-------|
| `model_type` | `App\Models\Payment` | Polymorphic owner |
| `model_id` | Preserved payment transaction ID | Must reference the migrated payment |
| `collection_name` | `payment_proof` | Existing collection on `Payment` |
| `disk` | `private` | Stores the copied file under `storage/app/private/media/` using Spatie's path generator |
| `file_name` | Legacy file name | Preserve the source extension |
| `mime_type` | Detected from physical file | Must match an accepted MIME type |
| `size` | Physical file size | Recorded by Spatie during import |
| `custom_properties.legacy_source_table` | `1_transactions` | Migration provenance |
| `custom_properties.legacy_transaction_id` | Legacy transaction ID | Migration provenance |
| `custom_properties.legacy_source_path` | Exact `payment_slip` value | Migration provenance and retry locator |
| `custom_properties.legacy_transaction_type` | `payment` | Deposit transactions are excluded |

#### Migration Strategy for Transaction/Payment Media

1. Migrate active legacy `payment` transactions into `payments` under section 5.3, preserving their IDs.
2. Exclude all `deposit` transactions; they migrate to `invoice_items` under section 5.4.
3. Store each payment's legacy `payment_slip` path and transaction type under `payments.meta.legacy`.
4. Process only payments with a non-empty `meta.legacy.payment_slip_path` value.
5. Resolve the exact `payment_slip` value under the configured legacy tenant directory. Reject
   any resolved path outside that directory. If the stored value is stale,
   look for `{payment_id}` with a supported extension in `transactions/bills/payment_slips/`.
6. Validate that the physical file exists, is readable, has an accepted MIME type, and is within
   the configured size limit.
7. Import the physical file with Spatie using `preservingOriginal()`. This copies the file to the
   `private` disk, creates the `media` row, and leaves the legacy source file unchanged.
8. Attach the imported file to the payment's `payment_proof` collection and generate the configured
   `thumb` conversion for supported images.
9. Preserve legacy provenance in the media custom properties:

```php
$payment->addMedia($legacyFilePath)
    ->preservingOriginal()
    ->withCustomProperties([
        'legacy_source_table' => '1_transactions',
        'legacy_transaction_id' => $payment->id,
        'legacy_source_path' => $legacyPath,
        'legacy_transaction_type' => data_get($payment->meta, 'legacy.transaction_type'),
    ])
    ->toMediaCollection('payment_proof', 'private');
```

10. Verify the created media record belongs to the expected payment, uses the `payment_proof`
    collection and `private` disk, and has a readable stored file.
11. Use `hasMedia('payment_proof')` with `--skip-existing` for safe reruns. Because the
   collection is single-file, an intentional re-import replaces its existing attachment.
12. When the legacy path is present but the file cannot be found or validated, log a migration orphan/error
   with the transaction ID and source path. Do not create an empty media record.

If a legacy `deposit` row contains `payment_slip`, do not attach that file to a payment. The
corresponding actual `payment` transaction is the authoritative source for payment-proof media.

#### File Validation

| Rule | Value |
|------|-------|
| Supported formats | JPG, JPEG, PNG, PDF |
| Maximum legacy upload size | 5 MB |
| Current application media limit | 10 MB global limit; the payment collection restricts MIME types |

---

### 7.3 User Media

**Review Status**: [x]

**Source Location**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/users/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `user_mykad_image` | `users/{user_id}/profile/user_mykad_image.{ext}` | `users.mykad` | Extension is determined by the uploaded MIME type. |
| `user_passport_size_photo` | `users/{user_id}/profile/user_passport_size_photo.{ext}` | `users.photo` | Extension is determined by the uploaded MIME type. |
| `user_immunization_card` | `users/{user_id}/profile/user_immunization_card.{ext}` | `users.immunization_card` | Extension is determined by the uploaded MIME type. |

#### Migration Strategy for User Media

1. **Source Path**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/users/{user_id}/profile/{filename}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `mykad` → stores `user_mykad_image`
   - Collection: `photo` → stores `user_passport_size_photo`
   - Collection: `immunization_card` → stores `user_immunization_card`
3. **Process**:
   - For each user, check if files exist in legacy location
   - Copy file to temp location
   - Attach to user using media library
   - Preserve original filename metadata
4. **Orphan Handling**: If user files are missing, skip silently
5. **File Format**: Accept JPG, JPEG, and PNG formats as-is
6. **Spouse Documents**: Process separately through section 7.4.

---

### 7.4 Family Member Media

**Review Status**: [x]

**Source Location**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/users/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `spouse_mykad_image` | `users/{user_id}/profile/spouse_mykad_image.{ext}` | `family_members.mykad` | Extension is determined by the uploaded MIME type. |
| `spouse_passport_size_photo` | `users/{user_id}/profile/spouse_passport_size_photo.{ext}` | `family_members.photo` | Extension is determined by the uploaded MIME type. |

#### Migration Strategy for User Media

1. **Source Path**: `{LEGACY_APP_PATH}/storage/app/{LEGACY_WEBSITE_UUID}/users/{user_id}/profile/{filename}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `mykad` → stores `spouse_mykad_image`
   - Collection: `photo` → stores `spouse_passport_size_photo` 
3. **Process**:
   - For each user, check if files exist in legacy location
   - Copy file to temp location
   - Attach to user using media library
   - Preserve original filename metadata
4. **Orphan Handling**: If user files are missing, skip silently
5. **File Format**: Accept JPG, JPEG, and PNG formats as-is

---

### 7.5 ChildLog Media

**Review Status**: [ ]

**Source**: Legacy `media` table (tenant-prefixed as `1_media`) for `App\Model\ChildLog` (Legacy)

**Source Location**: `{LEGACY_APP_PATH}/storage/app/public/child_log_images/{media_id}/`

The legacy ChildLog model uses Spatie Media Library with the `child_log_pics` collection on the
public `child_log_images` disk. This storage is outside the Hyn tenant UUID directory.

| Legacy Source | Physical Path Pattern | Target | Status |
|---------------|-----------------------|--------|--------|
| `1_media` where `model_type = App\Model\ChildLog` and `collection_name = child_log_pics` | `storage/app/public/child_log_images/{media_id}/{file_name}` | No current ChildLog model or media collection | Defer mapping until the ChildLog feature has a target model. |

Do not infer ChildLog media from the presence of files alone; use the legacy `1_media` record to
identify the owning ChildLog record, collection, original file name, MIME type, and custom
properties.

---

### 7.6 Generated and External Files

**Review Status**: [SKIP]

| Source | Location / Representation | Migration Decision |
|--------|---------------------------|--------------------|
| Invoice email PDFs | `storage/app/invoices/{invoice_id}.pdf` | Regenerate from migrated invoice data; do not import. |
| Invoice spreadsheet exports | Default storage export such as `invoices.xlsx` | Operational export; do not import. |
| Fee-deduction text files | Command working-directory output | Operational log; do not import. |
| Classwork attachments | External YouTube, link, or Google Drive URLs in metadata | Preserve only if the Classwork feature is later mapped; no local file migration. |
| Health attachments | JSON metadata; local file-writing code is inactive | No physical file migration. |

---

## 8. Lookup Tables

### 8.1 Reference Tables (Read-Only)

These tables are used for lookups during migration:

| Legacy Table | Purpose | Used For |
|--------------|---------|----------|
| `1_child_status` | Child status names | Status mapping |
| `1_user_status` | User type names | User type mapping |
| `1_payment_status` | Invoice status names | Invoice status mapping |
| `1_payment_method` | Payment method names | Gateway mapping |
| `1_product_type` | Product type names | Product type mapping |
| `1_race` | Race names | Child race lookup |
| `1_religion` | Religion names | Child religion lookup |
| `1_gender` | Gender names | Child gender lookup |
| `1_state` | State names | Address state lookup |

---

### 8.2 Tables to SKIP

| Legacy Table | Reason |
|--------------|--------|
| `1_child_medical_history` | Not used per user |
| `1_migrations` | Laravel internal |
| `1_jobs` | Laravel internal |
| `1_password_resets` | Will be regenerated |
| `1_personal_access_tokens` | Will be regenerated |
| `1_audits` | Historical only |
| `1_notification` | Will be regenerated |
| `1_setting` | Different structure |
| `1_setting_meta` | Different structure |

---

## 9. Orphan Record Handling

### 9.1 Log Table Structure

Create `migration_orphans` table:

```sql
CREATE TABLE migration_orphans (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_table VARCHAR(255),
    source_id BIGINT,
    reason VARCHAR(255),
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 9.2 Orphan Scenarios

| Scenario | Action |
|----------|--------|
| Child without valid parent_id | Log, create with null user |
| Invoice with invalid user_id | Log, skip |
| Invoice item with invalid product_id | Log, skip |
| Payment with invalid invoice_id | Log, skip |
| Enrollment with invalid product_id | Log, skip |

---

## 10. Sample Data Testing

### 10.1 Test Records

Before full migration, test with these sample sizes:

| Table | Sample Size | Criteria |
|-------|-------------|----------|
| `1_users` | 20 | Mix of roles and statuses |
| `1_child` | 20 | Mix of statuses and products |
| `1_product` | 10 | All product types |
| `1_invoices` | 50 | Mix of statuses |
| `1_transactions` | 30 | Mix of methods |

### 10.2 Validation Queries

```sql
-- After test migration, run these checks:

-- User count matches
SELECT COUNT(*) FROM kindygo.1_users WHERE deleted_at IS NULL AND id IN (...test_ids...);
SELECT COUNT(*) FROM kindygo_app.users WHERE id IN (...test_ids...);

-- Child enrollment count (should be >= child count due to splits)
SELECT COUNT(*) FROM kindygo.1_child WHERE deleted_at IS NULL AND id IN (...test_ids...);
SELECT COUNT(*) FROM kindygo_app.child_enrolments WHERE child_id IN (...test_ids...);

-- Invoice totals match
SELECT SUM(price) FROM kindygo.1_invoices WHERE deleted_at IS NULL AND id IN (...test_ids...);
SELECT SUM(total) FROM kindygo_app.invoices WHERE id IN (...test_ids...);
```

---

## Changelog

| Date | Change | Author |
|------|--------|--------|
| Feb 2025 | Initial data mapping document | AI Assistant |
| | | |
