<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use App\Notifications\FollowUpCheckupNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendFollowUpCheckupReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'followup:send-reminders {--days=3 : Number of days before follow-up to send reminder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up checkup reminder emails to pet owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysBeforeFollowUp = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($daysBeforeFollowUp);

        $this->info("Checking for follow-up checkups scheduled for {$targetDate->format('Y-m-d')}...");

        // Find prescriptions with follow-up dates that match the target date
        // and haven't been notified yet
        $prescriptions = Prescription::with(['patient.user', 'diagnoses.disease'])
            ->whereDate('follow_up_date', $targetDate)
            ->whereNull('follow_up_notified_at')
            ->whereHas('patient.user') // Ensure the patient has an associated user
            ->get();

        if ($prescriptions->isEmpty()) {
            $this->info('No follow-up reminders to send.');
            return Command::SUCCESS;
        }

        $this->info("Found {$prescriptions->count()} follow-up reminder(s) to send.");

        $sentCount = 0;
        $errorCount = 0;

        foreach ($prescriptions as $prescription) {
            try {
                $user = $prescription->patient->user;
                
                if (!$user || !$user->email) {
                    $this->warn("Skipping prescription #{$prescription->id}: No valid email found.");
                    $errorCount++;
                    continue;
                }

                // Send the notification
                $user->notify(new FollowUpCheckupNotification($prescription));

                // Mark as notified
                $prescription->update([
                    'follow_up_notified_at' => now(),
                ]);

                $this->info("Sent follow-up reminder for prescription #{$prescription->id} to {$user->email}");
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for prescription #{$prescription->id}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->info("\nSummary:");
        $this->info("Successfully sent: {$sentCount}");
        if ($errorCount > 0) {
            $this->warn("Failed: {$errorCount}");
        }

        return Command::SUCCESS;
    }
}
