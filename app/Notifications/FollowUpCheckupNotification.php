<?php

namespace App\Notifications;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpCheckupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $prescription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Prescription $prescription)
    {
        $this->prescription = $prescription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // Refresh the prescription to ensure we have the latest data
        $this->prescription->refresh();
        
        // Load necessary relationships
        $this->prescription->load([
            'patient.petType',
            'patient.user',
            'diagnoses.disease'
        ]);

        $patient = $this->prescription->patient;
        $petName = $patient->pet_name ?? 'Your Pet';
        $prescriptionNumber = str_pad($this->prescription->id, 6, '0', STR_PAD_LEFT);
        $followUpDate = $this->prescription->follow_up_date->format('F d, Y');
        
        // Get disease names
        $diseases = $this->prescription->diagnoses->pluck('disease.name')->filter()->implode(', ');

        return (new MailMessage)
            ->subject('Follow-Up Checkup Reminder for ' . $petName . ' - Prescription #' . $prescriptionNumber)
            ->greeting('Hello!')
            ->line('This is a friendly reminder about an upcoming follow-up checkup for **' . $petName . '**.')
            ->line('**Follow-Up Details:**')
            ->line('- Prescription Number: #' . $prescriptionNumber)
            ->line('- Pet: ' . $petName)
            ->line('- Previous Diagnosis: ' . ($diseases ?: 'N/A'))
            ->line('- Scheduled Follow-Up Date: **' . $followUpDate . '**')
            ->line('')
            ->line('Please schedule an appointment for your pet\'s follow-up checkup to ensure their continued health and recovery.')
            ->action('Schedule Appointment', url('/client/appointments/create'))
            ->line('If you have already scheduled an appointment, please disregard this message.')
            ->line('If you have any questions, please don\'t hesitate to contact our clinic.')
            ->salutation('Thank you for trusting us with your pet\'s health!');
    }
}
