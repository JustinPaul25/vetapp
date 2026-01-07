<?php

namespace App\Notifications;

use App\Models\Prescription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpCheckupNotification extends Notification
{

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
        return ['mail', 'database'];
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

        $content = '<h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 22px; font-weight: 600;">Hello!</h2>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'This is a friendly reminder about an upcoming follow-up checkup for <strong>' . e($petName) . '</strong>.' .
            '</p>' .
            '<p style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; font-weight: 600;">Follow-Up Details:</p>' .
            '<ul style="margin: 0 0 20px 0; padding-left: 20px; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            '<li>Prescription Number: #' . $prescriptionNumber . '</li>' .
            '<li>Pet: ' . e($petName) . '</li>' .
            '<li>Previous Diagnosis: ' . e($diseases ?: 'N/A') . '</li>' .
            '<li>Scheduled Follow-Up Date: <strong>' . e($followUpDate) . '</strong></li>' .
            '</ul>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Please schedule an appointment for your pet\'s follow-up checkup to ensure their continued health and recovery.' .
            '</p>' .
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 30px 0;">' .
            '<tr>' .
            '<td align="center">' .
            '<a href="' . url('/appointments') . '" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">Schedule Appointment</a>' .
            '</td>' .
            '</tr>' .
            '</table>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'If you have already scheduled an appointment, please disregard this message.' .
            '</p>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'If you have any questions, please don\'t hesitate to contact our clinic.' .
            '</p>' .
            '<p style="margin: 30px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Thank you for trusting us with your pet\'s health!' .
            '</p>';

        return (new MailMessage)
            ->subject('Follow-Up Checkup Reminder for ' . $petName . ' - Prescription #' . $prescriptionNumber)
            ->view('emails.notification', [
                'subject' => 'Follow-Up Checkup Reminder for ' . $petName . ' - Prescription #' . $prescriptionNumber,
                'content' => new \Illuminate\Support\HtmlString($content),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
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

        $message = "Follow-up checkup reminder for {$petName} (Prescription #{$prescriptionNumber}). Scheduled date: {$followUpDate}";

        return [
            'link' => url('/appointments'),
            'subject' => 'Follow-Up Checkup Reminder for ' . $petName . ' - Prescription #' . $prescriptionNumber,
            'message' => $message,
        ];
    }
}
