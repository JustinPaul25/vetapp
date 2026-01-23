<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Notifications\AntiRabiesVaccinationNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAntiRabiesReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anti-rabies:send-reminders {--days=7 : Number of days before due date to send reminder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send anti-rabies vaccination reminder emails to pet owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysBeforeDue = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($daysBeforeDue);

        $this->info("Checking for anti-rabies vaccinations due on {$targetDate->format('Y-m-d')}...");

        // Find patients with anti-rabies due dates that match the target date
        // and haven't been notified yet
        $patients = Patient::with('user')
            ->whereNotNull('next_anti_rabies_due_date')
            ->whereDate('next_anti_rabies_due_date', $targetDate)
            ->whereNull('anti_rabies_notified_at')
            ->whereHas('user') // Ensure the patient has an associated user
            ->get();

        if ($patients->isEmpty()) {
            $this->info('No anti-rabies reminders to send.');
            return Command::SUCCESS;
        }

        $this->info("Found {$patients->count()} anti-rabies reminder(s) to send.");

        $sentCount = 0;
        $errorCount = 0;

        foreach ($patients as $patient) {
            try {
                $user = $patient->user;
                
                if (!$user || !$user->email) {
                    $this->warn("Skipping patient #{$patient->id} ({$patient->pet_name}): No valid email found.");
                    $errorCount++;
                    continue;
                }

                // Send the notification
                $user->notify(new AntiRabiesVaccinationNotification($patient));

                // Mark as notified
                $patient->update([
                    'anti_rabies_notified_at' => now(),
                ]);

                $this->info("Sent anti-rabies reminder for {$patient->pet_name} (Patient #{$patient->id}) to {$user->email}");
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for patient #{$patient->id} ({$patient->pet_name}): {$e->getMessage()}");
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
