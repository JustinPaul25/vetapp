# Follow-Up Checkup Feature

## Overview
This feature allows veterinary staff to schedule follow-up checkups when creating prescriptions. Pet owners will automatically receive email reminders 3 days before the scheduled follow-up date.

## Features Implemented

### 1. Database Changes
- **Migration**: Added `follow_up_date` and `follow_up_notified_at` columns to the `prescriptions` table
  - `follow_up_date`: Stores the scheduled follow-up checkup date (nullable)
  - `follow_up_notified_at`: Tracks when the reminder email was sent (nullable)
  - Location: `database/migrations/2025_12_19_012146_add_follow_up_date_to_prescriptions_table.php`

### 2. Model Updates
- **Prescription Model**: Added date casting for the new fields
  - Location: `app/Models/Prescription.php`
  - Casts: `follow_up_date` as date, `follow_up_notified_at` as datetime

### 3. Email Notification
- **FollowUpCheckupNotification**: New queued notification class that sends reminder emails
  - Location: `app/Notifications/FollowUpCheckupNotification.php`
  - Email includes:
    - Pet name
    - Prescription number
    - Previous diagnosis
    - Scheduled follow-up date
    - Link to schedule an appointment

### 4. Scheduled Command
- **SendFollowUpCheckupReminders**: Automated command to send reminders
  - Location: `app/Console/Commands/SendFollowUpCheckupReminders.php`
  - Command signature: `followup:send-reminders`
  - Options: `--days=3` (configurable number of days before follow-up)
  - Scheduled to run daily at 9:00 AM
  - Configuration: `routes/console.php`

### 5. Form Updates
- **Prescription Create Form**: Added follow-up date picker
  - Location: `resources/js/pages/Admin/Prescriptions/Create.vue`
  - Uses `CalendarDatePicker` component
  - Shows helpful hint about 3-day reminder
  - Optional field with future date validation

### 6. Controller Updates
- **AppointmentController**: Updated prescribe method
  - Location: `app/Http/Controllers/Admin/AppointmentController.php`
  - Validates follow-up date (must be after today)
  - Saves follow-up date with prescription

- **PrescriptionController**: Updated index method
  - Location: `app/Http/Controllers/Admin/PrescriptionController.php`
  - Includes follow-up date and notification status in list view

### 7. View Updates
- **Prescription Index**: Added follow-up column
  - Location: `resources/js/pages/Admin/Prescriptions/Index.vue`
  - Displays follow-up date with calendar icon
  - Shows "Notified" badge when reminder has been sent
  - Shows "-" when no follow-up is scheduled

## Usage

### Creating a Prescription with Follow-Up
1. Navigate to an approved appointment
2. Click "Create Prescription"
3. Fill in all required prescription details
4. Optionally, select a "Follow-up Checkup Date" using the date picker
5. Submit the prescription

### Viewing Follow-Up Information
1. Navigate to Admin → Prescriptions
2. The "Follow-up" column shows:
   - The scheduled follow-up date (if set)
   - A "Notified" badge (if reminder was sent)
   - "-" (if no follow-up is scheduled)

### Manual Testing of Reminders
To manually test the reminder system:
```bash
# Send reminders for follow-ups scheduled 3 days from now
php artisan followup:send-reminders

# Send reminders for follow-ups scheduled 7 days from now
php artisan followup:send-reminders --days=7
```

## Automated Scheduling

The reminder command runs automatically every day at 9:00 AM via Laravel's task scheduler.

### To Enable Scheduling (Production)
Add this cron entry to your server:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### For Development/Testing
Run the scheduler manually:
```bash
php artisan schedule:work
```

## Email Content

The follow-up reminder email includes:
- Subject: "Follow-Up Checkup Reminder for [Pet Name] - Prescription #[Number]"
- Greeting to the pet owner
- Pet details and previous diagnosis
- Scheduled follow-up date (highlighted)
- Call-to-action button to schedule an appointment
- Contact information

## Technical Details

### Date Validation
- Follow-up dates must be in the future (after today)
- Date picker automatically disables past dates
- Format: YYYY-MM-DD

### Notification Logic
- Reminders are sent 3 days before the scheduled follow-up date (configurable)
- Each prescription is notified only once (tracked via `follow_up_notified_at`)
- Emails are queued for better performance
- Failed notifications are logged

### Command Output
The reminder command provides detailed output:
- Number of reminders found
- Success/failure for each email
- Summary statistics

## Files Modified/Created

### Created Files
1. `database/migrations/2025_12_19_012146_add_follow_up_date_to_prescriptions_table.php`
2. `app/Notifications/FollowUpCheckupNotification.php`
3. `app/Console/Commands/SendFollowUpCheckupReminders.php`
4. `FOLLOW_UP_CHECKUP_FEATURE.md` (this file)

### Modified Files
1. `app/Models/Prescription.php`
2. `app/Http/Controllers/Admin/AppointmentController.php`
3. `app/Http/Controllers/Admin/PrescriptionController.php`
4. `resources/js/pages/Admin/Prescriptions/Create.vue`
5. `resources/js/pages/Admin/Prescriptions/Index.vue`
6. `routes/console.php`

## Future Enhancements

Possible improvements for future iterations:
1. Allow editing of follow-up dates after prescription creation
2. Add ability to manually trigger reminder emails
3. Send multiple reminders (e.g., 7 days before, 3 days before, 1 day before)
4. Add follow-up status tracking (completed, cancelled, rescheduled)
5. Dashboard widget showing upcoming follow-ups
6. SMS reminders in addition to email
7. Allow customization of reminder days per prescription
8. Auto-mark follow-ups as completed when new appointment is scheduled

## Testing Checklist

- [x] Database migration runs successfully
- [x] Follow-up date field appears in prescription form
- [x] Date picker only allows future dates
- [x] Prescription saves with follow-up date
- [x] Prescription saves without follow-up date (optional field)
- [x] Follow-up column appears in prescription list
- [x] Follow-up date displays correctly
- [x] Manual command execution works
- [x] No linter errors

### To Test Email Functionality
1. Ensure mail configuration is set up in `.env`
2. Create a prescription with follow-up date 3 days from now
3. Run: `php artisan followup:send-reminders`
4. Check the recipient's email inbox
5. Verify the prescription shows "Notified" badge

## Support

For questions or issues with this feature, please contact the development team.


