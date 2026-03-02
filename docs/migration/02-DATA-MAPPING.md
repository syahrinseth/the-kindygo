# KindyGo Legacy Data Mapping

> **EDITABLE DOCUMENT** - Review and modify mappings before implementation  
> **Last Updated**: February 2025  
> **Status**: PENDING REVIEW

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
6. [Relationships](#6-relationships)
7. [Media Files](#7-media-files)
   - [7.1 Child Media](#71-child-media)
   - [7.2 Transaction Media](#72-transaction-media-payment-proof)
   - [7.3 Invoice Media](#73-invoice-media)
   - [7.4 User Media](#74-user-media)
   - [7.5 Family Member Media](#75-family-member-media)
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

### 1.2 `1_preschool` → `centres`

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
| `campus_id` | int | `campus_id` | bigint | Direct | <!-- TODO: Do we need campus? (Answer: Yes) --> |
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

#### 1.2.1 Preschool `meta_data`

Store other legacy user data in `meta_data` JSON:

```json
{
    "legacy_ssm_comp_name": "Company Name",
    "legacy_ssm_no": "1234",
    "legacy_capacity": 58
}
```

**Custom Notes**:
```
I have add a notes beside the TODO, other than that table 1_preschool is good.

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
| `campus` | int | - | - | Skip | Not used |
| `preschool` | int | - | - | Map | use user has many centres relationship |
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
| `add_1` | `address_1` | |
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
| `company_add_1` | `user_office_infos.office_address_1` | |
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
| `spouse_add_1` | `family_members.address_1` | |
| `spouse_add_2` | `family_members.address_2` | |
| `spouse_city` | `family_members.city` | |
| `spouse_postcode` | `family_members.postal_code` | |
| `spouse_state` | `family_members.state` | Map legacy into MalaysianState enum |
| `spouse_company_add_1` | `family_members.office_address_1` | |
| `spouse_company_add_2` | `family_members.office_address_2` | |
| `spouse_company_city` | `family_members.office_city` | |
| `spouse_company_postcode` | `family_members.office_postcode` | |
| `spouse_company_state` | `family_members.office_state` | Map legacy into MalaysianState enum |

---

### 2.6 `1_model_has_roles` → `model_has_roles`

**Review Status**: [x]

**Source**: `kindygo.1_model_has_roles`  
**Target**: `kindygo_app.model_has_roles`

| Legacy Field | Target Field | Transform | Notes |
|--------------|--------------|-----------|-------|
| `role_id` | `role_id` | Direct | Legacy role IDs preserved |
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
| `price_history` | longtext | → `product_prices` | | See 4.2 | |
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
| `price` | `1_product.price` | In cents |
| `start_date` | `1_product.year` | Parse into carbon object and make it at the beginning of the year |
| `effective_to` | null | |
| `created_at` | `1_product.created_at` | |

### 4.3 `1_product.price_history` -> `product_prices`

**Review Status**: [x]

| Target Field | Source | Notes |
|--------------|--------|-------|
| `id` | Auto | |
| `product_id` | `1_product.id` | |
| `price` | `1_product.price_history[n]['price']` | In cents |
| `start_date` | `1_product.price_history[n]['year']` | Parse into carbon object and make it at the beginning of the year |
| `effective_to` | null | |
| `created_at` | `1_product.created_at` | |

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
**Legacy table Filter**: type = payment

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_id` | int | - | - | → pivot | invoice_payment pivot |
| `parent_id` | int | `user_id` | bigint | Direct | |`
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `paid_amount` | int | `amount` | int | Direct | |
| `payment_method` | int | `gateway` | varchar | Map | See mapping |
| `paid_at` | timestamp | `paid_at` | timestamp | Direct | |
| `billplz_bill_id` | int | `gateway_payment_id` | varchar | Direct | To string |
| `billplz_collection_id` | varchar | `meta` | JSON | Map | See mapping to meta |
| - | - | `gateway_payment_data` | Set | null |
| `paid_status` | tinyint | `status` | varchar | Map | 1 = 'paid' & 0 = 'unpaid' |
| `reference_id` | varchar | `reference_no` | varchar | Direct | |
| `label` | varchar | `description` | text | Direct | |
| `remarks` | varchar | `meta` | JSON | Map | See mapping to meta |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

#### Payment Method Mapping

| Legacy `payment_method` | Legacy Name | Target `gateway` | Notes |
|------------------------|-------------|------------------|-------|
| 1 | Billplz: Online Banking (FPX) | `billplz` | |
| 2 | CDM/Manual Transfer | `manual` | |
| 3 | Cheque | `cheque` | |
| 4 | CHIP: Online Banking (FPX), Visa & Mastercard | `chip` | |
| 5 | Zakat | `zakat` | |
| 6 | Baitulmal | `baitulmal` | |
| 7 | JKM | `jkm` | |
| 8 | ANIS | `anis` | |
| 9 | Booking (CHIP) | `chip_booking` | |

#### Meta Mapping

| Legacy Name | Target `meta` | Notes |
|------------------------|-------------|-------|
| `billplz_collection_id` | `gateway_collection_id` | |
| `remarks` | `remark` | |

### 5.4 `1_transactions` -> `payments`
Migrate legacy deposit typed to payments table.

**Review Status**: [x]

**Source**: `kindygo.1_transactions` (type = 'deposit') (Legacy)  
**Target**: `kindygo_app.payments` (Current)  
**ID Preservation**: YES
**Legacy table Filter**: type = deposit

| Legacy Field | Type | Target Field | Type | Transform | Notes |
|--------------|------|--------------|------|-----------|-------|
| `id` | int | `id` | bigint | Direct | Preserve ID |
| `invoice_id` | int | - | - | → pivot | invoice_payment pivot |
| `parent_id` | int | `user_id` | bigint | Direct | |`
| - | - | `tenant_id` | bigint | Set | Target tenant ID |
| `paid_amount` | int | `amount` | int | Direct | |
| `payment_method` | int | `gateway` | varchar | Map | See mapping |
| `paid_at` | timestamp | `paid_at` | timestamp | Direct | |
| `billplz_bill_id` | int | `gateway_payment_id` | varchar | Direct | To string |
| `billplz_collection_id` | varchar | `meta` | JSON | Map | See mapping to meta |
| - | - | `gateway_payment_data` | Set | null |
| `paid_status` | tinyint | `status` | varchar | Map | 1 = 'paid' & 0 = 'unpaid' |
| `reference_id` | varchar | `reference_no` | varchar | Direct | |
| `label` | varchar | `description` | text | Direct | |
| `remarks` | varchar | `meta` | JSON | Map | See mapping to meta |
| `created_at` | timestamp | `created_at` | timestamp | Direct | |
| `updated_at` | timestamp | `updated_at` | timestamp | Direct | |
| `deleted_at` | timestamp | - | - | Filter | Skip if NOT NULL |

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

**Source Location**: `/storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/children/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `passport_sized_image` | `children/{child_id}/profile/passport_sized_image.jpg` | `children.photo` | JPG format |
| `child_birth_certificate` | `children/{child_id}/profile/child_birth_certificate.png` | `children.birth_certificate` | PNG format |
| `immunization_card` | `children/{child_id}/profile/immunization_card.png` | `children.immunization_card` | PNG format |

#### Migration Strategy for Child Media

1. **Source Path**: `storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/children/{child_id}/profile/{filename}`
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
5. **File Format**: Accept jpg, jpeg, png formats as-is


---

### 7.2 Transaction Media (Payment Proof)

**Review Status**: [ ]

**Source Location**: Legacy payment records from `1_transactions` table

**File Path Patterns** (to be discovered):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| Payment proof files | `payments/{payment_id}/payment_proof.{ext}` | `payments.payment_proof` | JPG, PNG, PDF formats |
| Deposit proof files | `deposits/{deposit_id}/proof.{ext}` | `payments.payment_proof` | For deposit transactions |

#### Migration Strategy for Transaction/Payment Media

1. **Source Path**: `storage/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/payments/{payment_id}/payment_proof.{ext}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `payment_proof` → stores payment receipt/proof
3. **Process**:
   - For each payment/deposit record, check if payment_proof file exists in legacy location
   - Copy file to temp location
   - Attach to payment using media library
   - Preserve original filename metadata
4. **Orphan Handling**: If payment proof files are missing, skip silently
5. **File Format**: Accept jpg, jpeg, png, pdf formats as-is
6. **Size Limit**: 5MB per file

---

### 7.3 Invoice Media

**Review Status**: [ ]

**Source Location**: `/storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/invoices/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| Invoice documents | `invoices/{invoice_id}/*` | `invoices.documents` | PDF, images; multiple files |

#### Migration Strategy for Invoice Media

1. **Source Path**: `storage/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/invoices/{invoice_id}/*`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `documents` → stores invoice-related files (PDFs, images, etc.)
3. **Process**:
   - For each invoice, check if directory exists in legacy invoices folder
   - Detect all files in that directory
   - Copy all files to temp location
   - Attach each file to invoice using media library
   - Preserve original filenames
4. **Multiple Files**: Support multiple documents per invoice
5. **Orphan Handling**: If invoice documents directory is missing, skip silently
6. **File Format**: Accept pdf, jpg, jpeg, png, and other formats as-is

---

### 7.4 User Media

**Review Status**: [x]

**Source Location**: `/storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/users/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `user_mykad_image` | `users/{user_id}/profile/user_mykad_image.jpg` | `users.mykad` | Image format |
| `user_passport_size_photo` | `users/{user_id}/profile/user_passport_size_photo.jpg` | `users.photo` | Image format |
| `user_immunization_card` | `users/{user_id}/profile/user_immunization_card.jpg` | `users.immunization_card` | Image format |

#### Migration Strategy for User Media

1. **Source Path**: `storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/users/{user_id}/profile/{filename}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `mykad` → stores `user_mykad_image`
   - Collection: `photo` → stores `user_passport_size_photo`
   - Collection: `immunization_card` → stores `user_immunization_card`
3. **Skip Fields**:
4. **Process**:
   - For each user, check if files exist in legacy location
   - Copy file to temp location
   - Attach to user using media library
   - Preserve original filename metadata
5. **Orphan Handling**: If user files are missing, skip silently
6. **File Format**: Accept jpg, jpeg, png formats as-is
7. **Spouse Documents**: Skip spouse (`spouse_mykad_image`, `spouse_passport_size_photo`) - Move to family_members media

---

### 7.5 Family Member Media

**Review Status**: [x]

**Source Location**: `/storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/users/`

**File Path Patterns** (verified):

| Legacy Field | File Path Pattern | Target Collection | Notes |
|--------------|------------------|-------------------|-------|
| `spouse_mykad_image` | `users/{user_id}/profile/spouse_mykad_image.jpg` | `family_members.mykad` | Image format |
| `spouse_passport_size_photo` | `users/{user_id}/profile/spouse_passport_size_photo.jpg` | `family_members.photo` | Image format |

#### Migration Strategy for User Media

1. **Source Path**: `storage/app/kindygo-legacy/c136c9fde0ee46499ef6da5e15455449/users/{user_id}/profile/{filename}`
2. **Target Storage**: Use Filament Media Collections
   - Collection: `mykad` → stores `spouse_mykad_image`
   - Collection: `photo` → stores `spouse_passport_size_photo` 
3. **Skip Fields**:
4. **Process**:
   - For each user, check if files exist in legacy location
   - Copy file to temp location
   - Attach to user using media library
   - Preserve original filename metadata
5. **Orphan Handling**: If user files are missing, skip silently
6. **File Format**: Accept jpg, jpeg, png formats as-is

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
| `1_campuses` | Not used in new system |
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
