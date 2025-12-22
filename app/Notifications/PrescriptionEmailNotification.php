<?php

namespace App\Notifications;

use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrescriptionEmailNotification extends Notification implements ShouldQueue
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
            'medicines.medicine',
            'appointment',
            'patient.petType',
            'patient.user',
            'diagnoses.disease'
        ]);

        $patient = $this->prescription->patient;
        $petName = $patient->pet_name ?? 'Your Pet';
        $prescriptionNumber = str_pad($this->prescription->id, 6, '0', STR_PAD_LEFT);

        // Generate PDF
        $customPaper = [0, 0, 396, 612]; // 5.5in x 8.5in in points
        
        $base64Logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/logo_for_print.png')));
        $base64PanaboLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/panabo.png')));
        $base64PrescriptionLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/prescription.png')));

        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
        ])
        ->loadView('admin.appointments.pdf', [
            'prescription' => $this->prescription,
            'base64Logo' => $base64Logo,
            'base64PanaboLogo' => $base64PanaboLogo,
            'base64PrescriptionLogo' => $base64PrescriptionLogo,
        ])
        ->setPaper($customPaper, 'portrait');

        $pdfContent = $pdf->output();
        $fileName = 'prescription-' . $prescriptionNumber . '.pdf';

        return (new MailMessage)
            ->subject('Prescription for ' . $petName . ' - Prescription #' . $prescriptionNumber)
            ->greeting('Hello!')
            ->line('Your prescription for **' . $petName . '** has been prepared and is attached to this email.')
            ->line('**Prescription Details:**')
            ->line('- Prescription Number: #' . $prescriptionNumber)
            ->line('- Date: ' . $this->prescription->created_at->format('F d, Y'))
            ->line('- Pet: ' . $petName)
            ->line('')
            ->line('Please find the complete prescription details in the attached PDF file.')
            ->line('If you have any questions about the prescription, please contact our clinic.')
            ->attachData($pdfContent, $fileName, [
                'mime' => 'application/pdf',
            ])
            ->salutation('Thank you for choosing our veterinary services!');
    }
}










