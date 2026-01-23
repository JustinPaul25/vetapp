<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AntiRabiesVaccinationNotification extends Notification
{
    public $patient;

    /**
     * Create a new notification instance.
     */
    public function __construct(Patient $patient)
    {
        $this->patient = $patient;
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
        // Refresh the patient to ensure we have the latest data
        $this->patient->refresh();
        
        // Load necessary relationships
        $this->patient->load([
            'petType',
            'user'
        ]);

        $petName = $this->patient->pet_name ?? 'Your Pet';
        $nextDueDate = $this->patient->next_anti_rabies_due_date->format('F d, Y');
        $lastVaccinationDate = $this->patient->last_anti_rabies_date 
            ? $this->patient->last_anti_rabies_date->format('F d, Y') 
            : 'N/A';

        $content = '<h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 22px; font-weight: 600;">Hello!</h2>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'This is a friendly reminder that <strong>' . e($petName) . '</strong> is due for an annual anti-rabies vaccination.' .
            '</p>' .
            '<p style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; font-weight: 600;">Anti-Rabies Vaccination Details:</p>' .
            '<ul style="margin: 0 0 20px 0; padding-left: 20px; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            '<li>Pet: ' . e($petName) . '</li>' .
            '<li>Last Vaccination Date: ' . e($lastVaccinationDate) . '</li>' .
            '<li>Next Due Date: <strong>' . e($nextDueDate) . '</strong></li>' .
            '</ul>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Anti-rabies vaccinations are required annually to protect your pet and comply with local regulations. Please schedule an appointment to ensure your pet\'s vaccination is up to date.' .
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
            ->subject('Anti-Rabies Vaccination Reminder for ' . $petName)
            ->view('emails.notification', [
                'subject' => 'Anti-Rabies Vaccination Reminder for ' . $petName,
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
        // Refresh the patient to ensure we have the latest data
        $this->patient->refresh();
        
        // Load necessary relationships
        $this->patient->load([
            'petType',
            'user'
        ]);

        $petName = $this->patient->pet_name ?? 'Your Pet';
        $nextDueDate = $this->patient->next_anti_rabies_due_date->format('F d, Y');

        $message = "Anti-rabies vaccination reminder for {$petName}. Next due date: {$nextDueDate}";

        return [
            'link' => url('/appointments'),
            'subject' => 'Anti-Rabies Vaccination Reminder for ' . $petName,
            'message' => $message,
        ];
    }
}
