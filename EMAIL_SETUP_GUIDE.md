# Email Setup Guide

This guide will help you configure email functionality in your Laravel veterinary application.

## Current Status

Your application currently uses the `log` mailer, which means emails are written to log files instead of being sent. Your notifications are also queued, so you'll need to run a queue worker.

## Step 1: Configure Email Settings

Add the following environment variables to your `.env` file:

### Option A: SMTP (Recommended for Production)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**For Gmail:**
- You'll need to generate an "App Password" (not your regular password)
- Go to: Google Account → Security → 2-Step Verification → App Passwords
- Use port 587 with TLS encryption

**For Other SMTP Providers:**
- **Outlook/Hotmail**: `smtp-mail.outlook.com`, port 587, TLS
- **Yahoo**: `smtp.mail.yahoo.com`, port 587, TLS
- **Custom SMTP**: Use your provider's SMTP settings

### Option B: Mailtrap (Recommended for Development/Testing)

Mailtrap is perfect for testing emails without sending real emails:

1. Sign up at [mailtrap.io](https://mailtrap.io) (free tier available)
2. Create an inbox and get your credentials
3. Add to `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Option C: Other Services

**Postmark:**
```env
MAIL_MAILER=postmark
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Amazon SES:**
```env
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Resend:**
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Step 2: Set Up Queue Worker

Since your email notifications are queued, you need to run a queue worker:

### For Development (Windows with Herd)

Open a new terminal and run:

```bash
php artisan queue:work
```

Keep this running while you develop. To stop it, press `Ctrl+C`.

### For Production

You'll need to set up a supervisor or use a process manager. For Windows, you can:

1. **Use a scheduled task** to run the queue worker
2. **Use Laravel Horizon** (if using Redis)
3. **Use a service** like NSSM (Non-Sucking Service Manager)

Or temporarily use the `sync` queue driver for testing (not recommended for production):

```env
QUEUE_CONNECTION=sync
```

**Note:** With `sync`, emails will be sent immediately without a queue worker, but this blocks the request until the email is sent.

## Step 3: Verify Configuration

After updating your `.env` file:

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

2. **Test email sending:**
   You can test by triggering one of your email notifications, or create a test route:

   ```php
   // Add to routes/web.php temporarily
   Route::get('/test-email', function () {
       \Mail::raw('Test email from Laravel!', function ($message) {
           $message->to('your-email@example.com')
                   ->subject('Test Email');
       });
       return 'Email sent!';
   });
   ```

3. **Check queue jobs:**
   If using database queue, make sure you've run migrations:
   ```bash
   php artisan migrate
   ```

## Step 4: Monitor Email Activity

### Check Logs
If using `log` mailer, check:
```
storage/logs/laravel.log
```

### Check Queue Jobs
View pending jobs:
```bash
php artisan queue:work --once
```

View failed jobs:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan queue:retry all
```

## Troubleshooting

### Emails Not Sending

1. **Check .env file** - Make sure variables are set correctly (no quotes around values)
2. **Clear config cache** - Run `php artisan config:clear`
3. **Check queue worker** - Make sure `php artisan queue:work` is running
4. **Check logs** - Look in `storage/logs/laravel.log` for errors
5. **Test SMTP connection** - Use a tool like `telnet` or an SMTP tester

### Queue Jobs Not Processing

1. **Check queue connection** - Verify `QUEUE_CONNECTION` in `.env`
2. **Run migrations** - Ensure `jobs` and `failed_jobs` tables exist
3. **Start queue worker** - Run `php artisan queue:work`
4. **Check failed jobs** - Run `php artisan queue:failed`

### Gmail Issues

- Use App Password, not regular password
- Enable "Less secure app access" (if App Passwords not available)
- Check if 2-Step Verification is enabled (required for App Passwords)

## Quick Start (Development)

For quick testing, use Mailtrap:

1. Sign up at mailtrap.io
2. Add Mailtrap credentials to `.env`
3. Set `QUEUE_CONNECTION=sync` (temporarily)
4. Test sending an email
5. Check Mailtrap inbox

## Production Checklist

- [ ] Use a reliable SMTP service (not Gmail personal account)
- [ ] Set up proper queue worker (supervisor/service)
- [ ] Configure proper `MAIL_FROM_ADDRESS` with your domain
- [ ] Set up SPF/DKIM records for your domain
- [ ] Monitor failed jobs regularly
- [ ] Set up email delivery monitoring
- [ ] Test email delivery before going live

## Email Notifications in This App

1. **PrescriptionEmailNotification** - Sends prescription PDFs to clients
2. **ClientEmailNotification** - General client communications
3. **FollowUpCheckupNotification** - Follow-up appointment reminders
4. **VerifyEmail** - Email verification for user accounts

All notifications are queued, so ensure your queue worker is running!

