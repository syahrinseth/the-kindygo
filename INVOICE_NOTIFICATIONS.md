# Invoice Notification System

This document describes the invoice notification features that have been added to the KindyGo application.

## Features Added

### 1. Middleware for CHIP Payment Controller
- Added authentication middleware to protect payment callback routes
- Added signed URL middleware for payment success, failure, and cancel callbacks
- Webhook endpoint remains unprotected for external CHIP service calls

### 2. Invoice Notifications

#### InvoicePendingNotification
- Sent to users for invoices that are approaching their due date
- Contains invoice details, amount, due date, and payment link
- Professional and friendly tone

#### InvoiceOverdueNotification  
- Sent to users for invoices that are past their due date
- More urgent tone with warning about service interruption
- Includes days overdue calculation
- Contains invoice details and payment link

### 3. Manual Notification Actions

#### Send Notification Action (Individual)
- Available in the invoice table actions
- Sends appropriate notification based on invoice status (pending vs overdue)
- Only visible for pending or overdue invoices
- Requires update permission on the invoice
- Shows confirmation dialog before sending

#### Send Bulk Notification Action
- Available in bulk actions for multiple invoice selection
- Processes multiple invoices at once
- Skips ineligible invoices (wrong status, no permissions)
- Provides summary of sent, skipped, and error counts
- Requires update permission on Invoice model

### 4. Automated Console Commands

#### SendInvoiceReminders Command
```bash
php artisan invoice:send-reminders [options]
```

Options:
- `--dry-run`: Preview what would be sent without sending emails
- `--days-before=3`: Days before due date to send reminder (default: 3)
- `--overdue-only`: Only send overdue notifications
- `--pending-only`: Only send pending notifications
- `--tenant=ID`: Process invoices for specific tenant ID only
- `--tenant-slug=SLUG`: Process invoices for specific tenant slug only

Features:
- Automatically marks overdue invoices before sending
- Processes both pending and overdue invoices
- Supports tenant-specific processing for multi-tenant environments
- Comprehensive logging and error handling
- Summary report of actions taken

#### MarkOverdueInvoices Command
```bash
php artisan invoice:mark-overdue [options]
```

Options:
- `--dry-run`: Preview what would be updated without updating
- `--tenant=ID`: Process invoices for specific tenant ID only
- `--tenant-slug=SLUG`: Process invoices for specific tenant slug only

Features:
- Marks pending invoices as overdue when past due date
- Supports tenant-specific processing
- Can be run independently or as part of reminder process

### 5. Scheduling (Recommended)

Add to `app/Console/Kernel.php` schedule method:

```php
// Mark overdue invoices daily at 6 AM (all tenants)
$schedule->command('invoice:mark-overdue')->dailyAt('06:00');

// Send reminders daily at 9 AM for invoices due within 3 days (all tenants)
$schedule->command('invoice:send-reminders --days-before=3')->dailyAt('09:00');

// Send additional overdue reminders at 2 PM (all tenants)
$schedule->command('invoice:send-reminders --overdue-only')->dailyAt('14:00');

// Example: Tenant-specific scheduling
$schedule->command('invoice:send-reminders --tenant-slug=acme-corp --days-before=7')->dailyAt('10:00');
$schedule->command('invoice:mark-overdue --tenant=123')->dailyAt('05:30');
```

### 6. Tenant-Specific Usage

For multi-tenant environments, you can run commands for specific tenants:

```bash
# By tenant ID
php artisan invoice:send-reminders --tenant=123
php artisan invoice:mark-overdue --tenant=123

# By tenant slug
php artisan invoice:send-reminders --tenant-slug=acme-corp
php artisan invoice:mark-overdue --tenant-slug=acme-corp

# Combined with other options
php artisan invoice:send-reminders --tenant-slug=acme-corp --overdue-only --dry-run
```

## Usage Instructions

### Manual Notifications
1. Navigate to Finance > Invoices in the admin panel
2. For individual invoices: Click the bell icon in the actions column
3. For multiple invoices: Select invoices and choose "Send Notifications" from bulk actions
4. Confirm the action in the dialog

### Automated Setup
1. Add the scheduling commands to your Laravel scheduler
2. Ensure your cron job is running: `* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1`
3. Monitor logs for notification sending activity

### Testing
Use the `--dry-run` option to test commands without sending actual emails:
```bash
php artisan invoice:send-reminders --dry-run
php artisan invoice:mark-overdue --dry-run

# Test tenant-specific operations
php artisan invoice:send-reminders --tenant-slug=acme-corp --dry-run
php artisan invoice:mark-overdue --tenant=123 --dry-run
```

## Security Features

### CHIP Payment Security
- Authentication required for all payment callbacks except webhooks
- Signed URLs ensure payment callbacks can't be tampered with
- Payment processing logging for audit trail

### Notification Security
- Permission checks ensure only authorized users can send notifications
- Tenant isolation ensures notifications only go to correct users
- Comprehensive logging of all notification activities

## Logging

All notification activities are logged with:
- Invoice details (ID, number, amount)
- User details (ID, email)
- Notification type (pending/overdue)
- Sender information
- Tenant information (ID, name) when applicable
- Timestamps
- Error details when applicable

Log entries can be found in `storage/logs/laravel.log`.

## Email Templates

The notifications use Laravel's MailMessage class with:
- Professional styling consistent with other app emails
- Clear call-to-action buttons
- Invoice details and payment information
- Centre-specific branding
- Mobile-responsive design

## Error Handling

- Graceful handling of email sending failures
- Detailed error logging for troubleshooting
- User-friendly error messages in the admin interface
- Bulk operations continue even if individual emails fail
- Comprehensive error reporting in command summaries
