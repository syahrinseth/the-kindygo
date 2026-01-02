# KindyGo E-Invoice System Documentation

## Overview
This document outlines the complete e-Invoice implementation in the KindyGo system, including user requirements, LHDN classification codes, timezone handling, and system architecture.

## E-Invoice User Requirements

### Prerequisites for E-Invoice Generation

For a user to be eligible for e-Invoice generation, they must have **ALL** of the following:

#### 1. Valid Identification
- **NRIC** (for Malaysian citizens) OR **Passport** (for foreign nationals)
- At least one of these must be provided and not empty
- **Validation**:
  - NRIC: Exactly 12 digits
  - Passport: Up to 20 alphanumeric characters

#### 2. Tax Identification Number (TIN)
- **Required**: Individual TIN for tax purposes
- **Format**: 10-20 alphanumeric characters (A-Z, 0-9)
- **Example**: `C12345678901`
- **Validation Pattern**: `/^[A-Z0-9]{10,20}$/`
- **LHDN Compliance**: Mandatory for proper e-Invoice submission

#### 3. Complete Address Information
- **Address Line 1**: Primary street address (required)
- **City**: City name (required)
- **Postal Code**: 5-digit Malaysian postal code (required)
- **State**: Malaysian state code (required)
- **Address Line 2**: Additional address info (optional)

## LHDN Classification Codes

### Available Classification Codes
The system implements all 45 official LHDN e-Invoice classification codes through the `EInvoiceClassificationCode` enum:

```php
// Key codes for childcare business
EInvoiceClassificationCode::CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES = '002'
EInvoiceClassificationCode::OTHERS = '022'

// Other relevant codes
EInvoiceClassificationCode::EDUCATION_FEES = '010'
EInvoiceClassificationCode::MEDICAL_EXAMINATION_OR_VACCINATION = '020'
```

### Default Classification
- **Primary**: Code `002` - Child care centres and kindergartens fees
- **Fallback**: Code `022` - Others

### Helper Methods
```php
// Get default childcare classification
EInvoiceClassificationCode::getChildcareDefault()

// Check if code is childcare-related
$code->isChildcareRelated()

// Check if code is for self-billing
$code->isSelfBilled()

// Get all codes as select options
EInvoiceClassificationCode::toSelectArray()
```

## Database Structure

### User Profile Table
```sql
ALTER TABLE user_profiles ADD COLUMN tin VARCHAR(20) NULL
COMMENT 'Tax Identification Number for e-invoice';
```

### Invoice Table
E-Invoice related fields:
```sql
einvoice_uuid
einvoice_submission_id
einvoice_status
einvoice_validation_url
einvoice_submitted_at
```

## User Interface Implementation

### UserForm Component (`app/Filament/Forms/UserForm.php`)

#### TIN Input Field
```php
Forms\Components\TextInput::make('profile.tin')
    ->label('TIN (Tax Identification Number)')
    ->placeholder('e.g., C12345678901')
    ->helperText('Individual TIN for tax purposes (optional)')
    ->maxLength(20)
    ->rules(['nullable', 'regex:/^[A-Z0-9]{10,20}$/'])
    ->suffixIcon('heroicon-m-identification')
    ->hint('Format: 10-20 alphanumeric characters')
```

#### Form Sections
1. **Basic Information**: Name, email
2. **Personal Information**: NRIC/Passport, phone, occupation, TIN
3. **Home Address**: Complete address for e-Invoice
4. **Office Information**: Optional office details
5. **Documents & Photos**: MyKad, immunization cards
6. **Role Assignment**: User roles and permissions
7. **Centre Assignments**: Associated centres

### UserResource Component (`app/Filament/Resources/UserResource.php`)

#### Table Columns
```php
// E-Invoice Ready Status
Tables\Columns\IconColumn::make('einvoice_ready')
    ->label('E-Invoice Ready')
    ->boolean()
    ->getStateUsing(function (User $record): bool {
        return $record->eInvoiceReady();
    })

// TIN Column (Privacy Protected)
Tables\Columns\TextColumn::make('profile.tin')
    ->label('TIN')
    ->formatStateUsing(function (?string $state): string {
        return $state ? '***' . substr($state, -4) : 'Not set';
    })
    ->tooltip(function (?string $state): string {
        return $state ? 'TIN: ' . $state : 'TIN not provided';
    })
```

#### Filters
```php
// E-Invoice Ready Filter
Tables\Filters\Filter::make('einvoice_ready')
    ->query(fn (Builder $query): Builder => $query->eInvoiceReady())

// Missing Requirements Filters
Tables\Filters\Filter::make('missing_identification')
    ->query(fn (Builder $query): Builder => $query->missingIdentification())

Tables\Filters\Filter::make('missing_tin')
    ->query(fn (Builder $query): Builder => $query->missingTin())

Tables\Filters\Filter::make('missing_address')
    ->query(fn (Builder $query): Builder => $query->missingCompleteAddress())
```

## User Model Implementation (`app/Models/User.php`)

### Helper Methods

#### Validation Methods
```php
// Check if user has valid identification
public function hasValidIdentification(): bool
{
    return $this->profile &&
           (!empty($this->profile->nric) || !empty($this->profile->passport));
}

// Check if user has valid TIN
public function hasValidTin(): bool
{
    return $this->profile && !empty($this->profile->tin);
}

// Check if user has complete address
public function hasCompleteAddress(): bool
{
    return $this->userAddress && $this->userAddress->isComplete();
}
```

#### E-Invoice Readiness
```php
// Overall readiness check
public function eInvoiceReady(): bool
{
    return $this->hasCompleteAddress() &&
           $this->hasValidIdentification() &&
           $this->hasValidTin();
}

// Get missing requirements list
public function getEInvoiceMissingRequirements(): array
{
    $missing = [];

    if (!$this->hasValidIdentification()) {
        $missing[] = 'NRIC or Passport';
    }

    if (!$this->hasValidTin()) {
        $missing[] = 'TIN (Tax ID)';
    }

    if (!$this->hasCompleteAddress()) {
        $missing[] = 'Complete address';
    }

    return $missing;
}
```

### Query Scopes
```php
// Users ready for e-Invoice
public function scopeEInvoiceReady(Builder $query): Builder

// Users missing identification
public function scopeMissingIdentification(Builder $query): Builder

// Users missing TIN
public function scopeMissingTin(Builder $query): Builder

// Users missing complete address
public function scopeMissingCompleteAddress(Builder $query): Builder
```

## Invoice Model Implementation (`app/Models/Invoice.php`)

### E-Invoice Fields
```php
protected $fillable = [
    // ... other fields
    'einvoice_uuid',
    'einvoice_submission_id',
    'einvoice_status',
    'einvoice_validation_url',
    'einvoice_submitted_at',
];

protected $casts = [
    'date' => 'datetime',
    'due_at' => 'datetime',
    'einvoice_submitted_at' => 'datetime',
    'status' => InvoiceStatus::class,
    // ... other casts
];
```

### E-Invoice Integration
```php
// Submit invoice to LHDN
public function submitToEInvoice(): array

// Convert to UBL 2.1 format
public function toEInvoiceFormat(): array

// Check submission status
public function isEInvoiceSubmitted(): bool

// Get validation URL
public function getEInvoiceValidationUrl(): ?string

// Cancel e-Invoice
public function cancelEInvoice(string $reason): array
```

## System Commands

### E-Invoice Status Command
```bash
# Check specific tenant
php artisan einvoice:status --tenant=1

# Check by tenant slug
php artisan einvoice:status --tenant-slug=preschool-abc

# Check all tenants
php artisan einvoice:status --all-tenants

# Detailed configuration check
php artisan einvoice:status --check-config
```

#### Command Features
- Multi-tenant support
- Configuration validation
- Database connectivity checks
- Service status verification
- Invoice statistics reporting
- Detailed error reporting

## Privacy and Security

### TIN Data Protection
- **Display**: Masked format in list views (`***1234`)
- **Search**: Full TIN searchable by authorized users
- **Tooltip**: Full TIN visible on hover for admins
- **Storage**: Plain text in database (consider encryption for production)

### Access Control
- **Role-based**: Different access levels for different user roles
- **Permission-based**: Granular permissions for e-Invoice operations
- **Tenant isolation**: Multi-tenant data separation

## Validation Rules

### TIN Validation
```php
'rules' => ['nullable', 'regex:/^[A-Z0-9]{10,20}$/']
'max_length' => 20
'format' => 'Uppercase letters and numbers only'
```

### NRIC Validation
```php
'rules' => ['required_without:profile.passport', 'digits:12']
'format' => 'Exactly 12 digits'
```

### Postal Code Validation
```php
'rules' => ['required', 'regex:/^\d{5}$/']
'format' => 'Exactly 5 digits'
```

## LHDN Compliance Features

### UBL 2.1 Format
- **Standard compliance**: Follows LHDN UBL 2.1 specification
- **Automatic classification**: Default to childcare classification code
- **Proper namespacing**: Correct XML namespaces for LHDN
- **Validation**: Built-in format validation before submission

### Required Elements
```xml
<Invoice>
    <ID>Invoice Number</ID>
    <IssueDate>YYYY-MM-DD</IssueDate>
    <InvoiceTypeCode>01</InvoiceTypeCode>
    <DocumentCurrencyCode>MYR</DocumentCurrencyCode>
    <InvoiceClassificationCode listID="LHDN">002</InvoiceClassificationCode>
    <!-- Supplier and Customer Party Data -->
    <!-- Invoice Lines -->
    <!-- Legal Monetary Total -->
</Invoice>
```

## Error Handling and Monitoring

### User-Friendly Messages
- Clear indication of missing requirements
- Step-by-step completion guidance
- Real-time validation feedback

### Admin Monitoring
- **Dashboard indicators**: Visual status for each user
- **Bulk operations**: Mass status checks and updates
- **Reporting**: Detailed compliance reports
- **Alerts**: Notifications for submission failures

## Integration Architecture

### Service Layer
- **EInvoiceSDKService**: Main service for LHDN integration
- **Multi-tenant aware**: Per-tenant credentials and settings
- **Error handling**: Comprehensive exception handling
- **Logging**: Detailed audit trails

### API Integration
- **LHDN SDK**: Official Malaysia e-Invoice SDK
- **Authentication**: TIN-based authentication per tenant
- **Rate limiting**: Proper request throttling
- **Retry logic**: Automatic retry for transient failures

## Future Enhancements

### Planned Improvements
1. **TIN Validation API**: Real-time validation against LHDN database
2. **Bulk Import**: Mass user TIN import functionality
3. **MyKad Integration**: Automatic data extraction from MyKad
4. **Advanced Reporting**: Comprehensive compliance analytics
5. **Automated Notifications**: Email alerts for missing requirements
6. **Mobile Support**: Mobile-optimized user registration forms

### Technical Debt
1. **TIN Encryption**: Implement field-level encryption for TIN storage
2. **Audit Trails**: Enhanced logging for all e-Invoice operations
3. **Performance Optimization**: Database indexing for large datasets
4. **API Versioning**: Support for future LHDN API versions
