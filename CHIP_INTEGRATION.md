# CHIP Payment Gateway Integration

This document outlines the implementation of CHIP payment gateway integration in the KindyGo application.

## What has been implemented:

### 1. Package Installation & Configuration
- ✅ Installed `syahrinseth/chip-laravel` package
- ✅ Published configuration file to `config/chiplaravel.php`
- ✅ Added environment variables to `.env`

### 2. Database Migration
- ✅ Added `gateway_payment_id` column to `payments` table
- ✅ Updated Payment model to include the new field
- ✅ Made `paid_at` column nullable for pending payments

### 3. Form Updates
- ✅ Updated `MakePaymentAction` form schema to conditionally show fields based on gateway selection:
  - Reference number: Only shown for bank transfers
  - Payment proof upload: Only shown for bank transfers
  - Payment date: Only shown for bank transfers
  - Amount and description: Always shown

### 4. Payment Processing
- ✅ Split payment processing into two methods:
  - `handleBankTransferPayment()`: Existing logic for bank transfers
  - `handleChipPayment()`: New logic for CHIP payments

### 5. CHIP Payment Flow
- ✅ Creates pending payment record
- ✅ Links payment to invoice
- ✅ Creates CHIP product and purchase
- ✅ Stores CHIP purchase ID in `gateway_payment_id`
- ✅ Redirects user to CHIP checkout page via `checkout_url`

### 6. Callback Handling
- ✅ Created `ChipPaymentController` with routes for:
  - Success callback (`/chip/success/{payment}`)
  - Failure callback (`/chip/failure/{payment}`)
  - Cancel callback (`/chip/cancel/{payment}`)
  - Webhook endpoint (`/chip/webhook`)
- ✅ Proper tenant-aware redirects to Filament resources

### 7. Payment Status Updates
- ✅ Added `CANCELLED` status to `PaymentStatus` enum
- ✅ Payment status is updated based on callback results
- ✅ Invoice status is updated to PAID when fully paid

### 8. Enhanced Payment History Display
- ✅ Added comprehensive payment history in invoice view showing:
  - Reference number and gateway type
  - Payment status with color-coded badges
  - Amount paid and payment date
  - Paid by (user who made the payment)
  - Payment description
  - Payment proof for bank transfers

## Environment Configuration

Add these variables to your `.env` file:

```env
CHIP_LARAVEL_API_BRAND_ID=your_chip_brand_id
CHIP_LARAVEL_API_KEY=your_chip_api_key
CHIP_LARAVEL_API_ENDPOINT=https://staging-api.chip-in.asia/api/
```

For production, change the endpoint to:
```env
CHIP_LARAVEL_API_ENDPOINT=https://gate.chip-in.asia/api/v1/
```

## How it works:

1. **User selects CHIP as payment gateway**: Form dynamically hides bank transfer-specific fields
2. **User fills amount and description**: These are the only required fields for CHIP payments
3. **Form submission**: Creates pending payment record and redirects to CHIP checkout
4. **CHIP creates purchase**: Returns Purchase object with `id` and `checkout_url`
5. **Store CHIP ID**: Payment record updated with CHIP purchase ID
6. **User completes payment on CHIP**: CHIP redirects back to success/failure/cancel URLs
7. **Payment status updated**: Based on the callback, payment and invoice statuses are updated
8. **Webhook handling**: CHIP also sends webhook notifications for payment status changes

## Testing:

1. Set up CHIP sandbox credentials in `.env`
2. Create an invoice
3. Use "Make Payment" action
4. Select "CHIP" as payment gateway
5. Enter amount and submit
6. You should be redirected to CHIP's checkout page

## Notes:

- CHIP payments start with `PENDING` status
- Bank transfer payments are immediately marked as `PAID`
- The system handles partial payments
- Invoice status becomes `PAID` when total payments >= invoice total
- All payment interactions are logged for debugging
