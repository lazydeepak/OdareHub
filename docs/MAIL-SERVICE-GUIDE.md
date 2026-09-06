# Mail Service Configuration & Testing Guide

## Overview

The mail service handles:
- **Password reset emails** - sent when users request account recovery
- **Password change notifications** - sent after successful password reset
- **Future features** - account invites, security alerts, notifications

All emails use **PHPMailer v6.10+** with SMTP transport and configurable TLS/SSL encryption.

## Current Status

✅ **Implemented:**
- Mail configuration storage (database + environment variables)
- MailService (PHPMailer wrapper with error handling)
- MailConfigService (settings management, validation)
- PasswordResetService (generates tokens, sends emails)
- Admin UI at `/admin/system-tools/email-settings`
- Test email endpoint with success/error feedback
- CLI diagnostics tool for troubleshooting

❌ **Not Yet Implemented:**
- Production mail delivery (requires SMTP configuration)
- Email scheduling/queuing
- Email template versioning

## How to Configure Mail

### Option 1: Web UI (Recommended for Admin Setup)

1. Log in as Platform Admin
2. Navigate to `/admin/system-tools/email-settings`
3. Fill in:
   - **SMTP Host** (e.g., `smtp.gmail.com`, `mail.example.com`)
   - **SMTP Port** (typically 587 for TLS, 465 for SSL)
   - **Encryption** (TLS or SSL)
   - **SMTP Username** (usually your email address)
   - **SMTP Password** (or app-specific password)
   - **From Email** (sender address, e.g., `noreply@company.com`)
   - **From Name** (sender display name)
   - **Reply-To** (optional, for support redirects)
4. Click "Save Email Settings"
5. Enter a test email address
6. Click "Send Test Email" to verify

### Option 2: Environment Variables (Recommended for Server Deployment)

Set these environment variables before starting the app:

```bash
export ERP_MAIL_DRIVER=smtp
export ERP_MAIL_SMTP_HOST=smtp.gmail.com
export ERP_MAIL_SMTP_PORT=587
export ERP_MAIL_SMTP_USERNAME=your-email@gmail.com
export ERP_MAIL_SMTP_PASSWORD=your-app-password
export ERP_MAIL_SMTP_ENCRYPTION=tls
export ERP_MAIL_FROM_EMAIL=noreply@company.com
export ERP_MAIL_FROM_NAME="Company Name"
export ERP_MAIL_REPLY_TO_EMAIL=support@company.com
export ERP_MAIL_REPLY_TO_NAME="Support Team"
```

Environment variables take precedence over database settings.

## Configuration Requirements

### Minimum Setup
- **SMTP Host**: Required (e.g., `smtp.gmail.com`)
- **From Email**: Required (must be valid email)
- **SMTP Port**: Optional (defaults to 587)
- **Encryption**: Optional (defaults to TLS)

### Common SMTP Providers

#### Gmail
```
Host: smtp.gmail.com
Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: Your app-specific password (not regular password)
```

**Note:** Gmail requires an [app-specific password](https://support.google.com/accounts/answer/185833). Create one in your Google Account settings.

#### Outlook/Microsoft
```
Host: smtp-mail.outlook.com
Port: 587
Encryption: TLS
Username: your-email@outlook.com
Password: Your Outlook password
```

#### SendGrid
```
Host: smtp.sendgrid.net
Port: 587
Encryption: TLS
Username: apikey
Password: Your SendGrid API key
```

#### Custom Mail Server
```
Host: mail.your-domain.com
Port: 587 (TLS) or 465 (SSL)
Encryption: TLS or SSL
Username: your-account@your-domain.com
Password: Your password
```

## Testing Mail Delivery

### Using the Web UI
1. Go to `/admin/system-tools/email-settings`
2. In the "Send Test Email" section, enter your email
3. Click "Send Test Email"
4. Check your inbox (including spam/junk)
5. Look for email with subject: "IPM mail test"

### Using CLI Diagnostics

Run the mail diagnostics script from the terminal:

```bash
# Show current mail configuration
php tools/mail-diagnostics.php

# Test SMTP connection
php tools/mail-diagnostics.php '' connection

# Send a test email
php tools/mail-diagnostics.php your@email.com test

# Send a password reset email (to test the full flow)
php tools/mail-diagnostics.php user@example.com send-reset
```

**Output Example:**
```
================================================================================
Mail Diagnostics & Testing Utility
Debug Mode: DISABLED (generic messages)
================================================================================

📋 MAIL CONFIGURATION

Status: ✅ CONFIGURED

Current Settings:
Setting         Value
---             ---
Driver          smtp
SMTP Host       smtp.gmail.com
SMTP Port       587
Encryption      tls
Username        ✓ Set
Password        ✓ Set
From Email      noreply@app.local
From Name       Test App
```

## Email Flows

### 1. Password Reset (User-Initiated)

```
User visits /forgot-password
    ↓
User enters email address
    ↓
PasswordResetService.requestReset() called
    ↓
- Generates random token
- Hashes token for storage
- Creates password_reset_token record
- Calls MailService.send()
    ↓
MailService connects to SMTP
    ↓
Email sent to user with reset link
    ↓
User receives email with:
  • Personalized greeting
  • Reset link (expires in 1 hour)
  • Security notice if not requested by user
```

**Public Response:** Always generic ("Check your email...") to avoid account enumeration.

### 2. Password Changed (System-Initiated)

```
User submits new password on /reset-password
    ↓
PasswordResetService.completeReset() called
    ↓
- Validates token
- Hashes new password
- Marks token as used
- Invalidates all active sessions (security)
- Calls MailService.send() with password_changed template
    ↓
Notification email sent to user
```

## Security Considerations

✅ **Implemented:**
- SMTP credentials encrypted in transit (TLS/SSL)
- Tokens are one-time use
- Tokens expire (1 hour default)
- Rate limiting (3 requests per email per 15 min, 8 per IP)
- Public responses don't reveal whether account exists
- Database passwords hashed with bcrypt
- Session invalidation on password change
- CSRF protection on all forms

⚠️ **To Verify:**
- SMTP host reachability
- SSL/TLS certificate validity
- SMTP authentication success
- Email inbox delivery (may be caught by spam filters)

## Troubleshooting

### "Mail is not configured"
**Cause:** SMTP Host not set.
**Solution:** Set SMTP Host in web UI or env vars.

### "SMTP connection failed"
**Causes:**
- Host unreachable (DNS, firewall, ISP blocking)
- Port blocked (try port 25, 465, or 2525 if 587 fails)
- SSL/TLS mismatch (SSL ≠ TLS)

**Solution:**
1. Verify host is correct: `nslookup smtp.gmail.com`
2. Test port: `telnet smtp.gmail.com 587`
3. Try different port (e.g., 465 for SSL instead of 587 for TLS)
4. Check firewall rules

### "Authentication failed"
**Causes:**
- Wrong username/password
- Gmail requires app-specific password
- Account locked or disabled

**Solution:**
1. Verify credentials are correct
2. For Gmail: use app-specific password from [account settings](https://myaccount.google.com/apppasswords)
3. Check if account is locked/disabled at provider

### "Email sent but not received"
**Causes:**
- Delivered to spam/junk folder
- SPF/DKIM/DMARC alignment issues
- Rate limited by recipient

**Solution:**
1. Check spam/junk folder
2. Add sender to contacts/whitelist
3. Configure SPF/DKIM/DMARC records
4. Check email logs: `storage/logs/php-error.log`

### "Can't see detailed errors"
**Note:** Debug mode is disabled in production. Errors are vague for security.

**To Enable Debug:**
1. Set `APP_DEBUG=true` in environment or config
2. Run diagnostics script (shows full errors)
3. Check logs: `storage/logs/php-error.log`

## File Locations

```
app/
  Services/
    MailService.php              # PHPMailer wrapper, send logic
    MailConfigService.php        # Configuration management, validation
    MailTemplateService.php      # Template rendering
    PasswordResetService.php     # Token generation, reset flow
  MailTemplates/
    password_reset.php           # Email template for reset link
    password_changed.php         # Email template for notification
    test_email.php               # Test email template
  Locale/
    en.php, ja.php, ne.php       # Email subject strings

plugins/AdminTools/
  Views/admin/
    email_settings.php           # Admin UI form
  Controllers/
    AdminToolsController.php     # Settings page controller
  routes.php                     # Routes for settings and test endpoint

tools/
  mail-diagnostics.php           # CLI diagnostics and testing tool

storage/db_config.php            # Database configuration
storage/logs/php-error.log       # Error logs
```

## Email Templates

### password_reset.php
Sent when user requests a password reset. Includes:
- Personalized greeting (if name available)
- Reset link with token
- Expiration time (1 hour)
- Security notice

### password_changed.php
Sent after successful password change. Includes:
- Confirmation message
- Timestamp of change
- Security notice

### test_email.php
Used for testing. Includes:
- Simple test message
- Timestamp
- Confirmation that SMTP works

## API Reference

### MailService

```php
// Send email using template
$service = new MailService();
$service->send('user@example.com', 'Subject', 'template_name', [
    'key1' => 'value1',
    'key2' => 'value2'
]);

// Send raw HTML email
$service->sendRaw('user@example.com', 'Subject', '<p>HTML body</p>', 'Text body');

// Send test email
$service->sendTest('user@example.com');
```

### MailConfigService

```php
// Get current settings (from DB or env vars)
$config = new MailConfigService();
$settings = $config->currentSettings();

// Check if properly configured
$isReady = $config->isConfigured($settings);

// Get masked settings (passwords hidden)
$masked = $config->maskedSettings();

// Save settings from user input
$config->save($_POST);

// Validate configuration
$config->validate($settings); // throws RuntimeException if invalid
```

### PasswordResetService

```php
// Request password reset (generates token, sends email)
$service = new PasswordResetService();
$service->requestReset(
    'user@example.com',
    $_SERVER['REMOTE_ADDR'],
    $_SERVER['HTTP_USER_AGENT']
);

// Validate reset token
$record = $service->validateToken($token_from_email_link);
if (!$record) {
    // Token invalid or expired
}

// Complete password reset
$result = $service->completeReset($token, $password, $password_confirm, $ip);
```

## Database Tables

### user_password_reset_tokens
Stores password reset tokens.

```sql
CREATE TABLE user_password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,      -- SHA256 hash of token
    expires_at DATETIME NOT NULL,       -- Token expiration time
    used_at DATETIME NULL,              -- When token was redeemed
    request_ip_hash CHAR(64) NULL,      -- Hashed IP for audit
    request_user_agent VARCHAR(255),    -- User-Agent for audit
    created_at DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user (user_id, created_at),
    KEY idx_password_reset_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### core_settings (mail.* keys)
Stores mail configuration.

```
mail.driver              → 'smtp' or 'disabled'
mail.smtp_host           → SMTP server hostname
mail.smtp_port           → Port (587 for TLS, 465 for SSL)
mail.smtp_username       → SMTP auth username
mail.smtp_password       → SMTP auth password (encrypted at rest)
mail.smtp_encryption     → 'tls', 'ssl', or ''
mail.from_email          → Sender email address
mail.from_name           → Sender display name
mail.reply_to_email      → Reply-to address (optional)
mail.reply_to_name       → Reply-to name (optional)
mail.updated_by          → User who last changed settings
mail.updated_at          → Timestamp of last change
```

## Next Steps (Future Work)

- [ ] Email scheduling/queue system for high-volume sends
- [ ] Email templates in admin UI (no code changes needed)
- [ ] Bulk password reset invitations
- [ ] Account setup invitations
- [ ] Security alert notifications
- [ ] Email delivery logs and bounce handling
- [ ] Unsubscribe management
- [ ] A/B testing for templates

## Support

For issues:
1. Run diagnostics: `php tools/mail-diagnostics.php`
2. Check logs: `storage/logs/php-error.log`
3. Review this guide's troubleshooting section
4. Verify SMTP credentials with provider
5. Contact your mail provider for blocked ports or rate limits
