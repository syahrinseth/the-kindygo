# Email Configuration Troubleshooting Guide

## Overview
This guide helps resolve email configuration issues in the KindyGo application, particularly when encountering mailer configuration errors.

## Common Issues

### 1. "Mailer [mailgun] is not defined" Error

**Symptoms:**
- Error message: `Mailer [mailgun] is not defined`
- Invoice notifications fail to send
- Email functionality breaks

**Solutions:**

#### Option A: Configure Mailgun (Recommended for Production)
1. Ensure Mailgun package is installed:
   ```bash
   composer require symfony/mailgun-mailer
   ```

2. Set environment variables in `.env`:
   ```bash
   MAIL_MAILER=mailgun
   MAILGUN_DOMAIN=your-domain.mailgun.org
   MAILGUN_SECRET=your-mailgun-secret-key
   MAILGUN_ENDPOINT=api.mailgun.net
   ```

3. Test the configuration:
   ```bash
   php artisan mail:test --mailer=mailgun
   ```

#### Option B: Use SMTP as Fallback
1. Set environment variables in `.env`:
   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=your-smtp-host
   MAIL_PORT=587
   MAIL_USERNAME=your-email@domain.com
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=tls
   ```

2. Test the configuration:
   ```bash
   php artisan mail:test --mailer=smtp
   ```

#### Option C: Use Failover Configuration
1. Set environment variables in `.env`:
   ```bash
   MAIL_MAILER=failover
   ```

2. The failover mailer will try mailgun first, then smtp, then log.

## Testing Email Configuration

Use the built-in test command to verify your email setup:

```bash
# Test default mailer
php artisan mail:test

# Test specific mailer
php artisan mail:test --mailer=mailgun
php artisan mail:test --mailer=smtp

# Verbose output for debugging
php artisan mail:test --mailer=mailgun -v
```

## Environment Configuration Examples

### Development (.env.example)
```bash
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@kindygo.local"
MAIL_FROM_NAME="KindyGo"
```

### Staging/Production with Mailgun
```bash
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=key-xxxxxxxxxx
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="KindyGo"
```

### Staging/Production with SMTP
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="KindyGo"
```

## Troubleshooting Steps

1. **Check Current Configuration:**
   ```bash
   php artisan config:show mail
   ```

2. **Clear Configuration Cache:**
   ```bash
   php artisan config:clear
   ```

3. **Test Email Configuration:**
   ```bash
   php artisan mail:test
   ```

4. **Check Log Files:**
   - Laravel logs: `storage/logs/laravel.log`
   - Look for mailer-related errors

5. **Verify Environment Variables:**
   ```bash
   php artisan env:show | grep MAIL
   ```

## Error Handling

The `SendNotificationAction` class now includes improved error handling for:
- Mailer configuration issues
- Transport exceptions
- General mail failures

Users will see appropriate error messages instead of generic failures.

## Support

If email issues persist:
1. Check the application logs for detailed error messages
2. Verify all required environment variables are set
3. Test the email configuration using the provided commands
4. Contact the system administrator if needed

## Related Files

- `config/mail.php` - Mail configuration
- `config/services.php` - Service provider configurations (Mailgun, etc.)
- `app/Console/Commands/TestMailConfiguration.php` - Email testing command
- `app/Filament/Resources/InvoiceResource/Actions/SendNotificationAction.php` - Notification action with error handling
