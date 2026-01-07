<?php

namespace App\Notifications;

use App\Models\Prescription;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrescriptionEmailNotification extends Notification
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

        // Generate PDF - A5 landscape: 210mm × 148mm (8.27" × 5.83")
        // 1 inch = 72 points, so 8.27" = 595pt, 5.83" = 420pt
        $customPaper = [0, 0, 595, 420];
        
        $base64Logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/logo_for_print.png')));
        $base64PanaboLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/panabo.png')));
        $base64PrescriptionLogo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('media/prescription.png')));

        // Get veterinarian information from settings
        $veterinarianName = Setting::get('veterinarian_name', '');
        $veterinarianLicense = Setting::get('veterinarian_license_number', '');

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
            'veterinarianName' => $veterinarianName,
            'veterinarianLicense' => $veterinarianLicense,
        ])
        ->setPaper($customPaper, 'landscape');

        $pdfContent = $pdf->output();
        $fileName = 'prescription-' . $prescriptionNumber . '.pdf';

        // Generate signed URL for public download (valid for 30 days)
        $downloadUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'prescriptions.public.download',
            ['id' => $this->prescription->id],
            now()->addDays(30)
        );

        $content = '<h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 22px; font-weight: 600;">Hello!</h2>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Your prescription for <strong>' . e($petName) . '</strong> has been prepared and is attached to this email.' .
            '</p>' .
            '<p style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; font-weight: 600;">Prescription Details:</p>' .
            '<ul style="margin: 0 0 20px 0; padding-left: 20px; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            '<li>Prescription Number: #' . $prescriptionNumber . '</li>' .
            '<li>Date: ' . $this->prescription->created_at->format('F d, Y') . '</li>' .
            '<li>Pet: ' . e($petName) . '</li>' .
            '</ul>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Please find the complete prescription details in the attached PDF file. You can also ' .
            '<a href="' . e($downloadUrl) . '" style="color: #2563eb; text-decoration: underline; font-weight: 600;">download the prescription PDF here</a>.' .
            '</p>' .
            '<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'If you have any questions about the prescription, please contact our clinic.' .
            '</p>' .
            '<p style="margin: 30px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">' .
            'Thank you for choosing our veterinary services!' .
            '</p>';

        $message = (new MailMessage)
            ->subject('Prescription for ' . $petName . ' - Prescription #' . $prescriptionNumber)
            ->view('emails.notification', [
                'subject' => 'Prescription for ' . $petName . ' - Prescription #' . $prescriptionNumber,
                'content' => new \Illuminate\Support\HtmlString($content),
            ])
            ->attachData($pdfContent, $fileName, [
                'mime' => 'application/pdf',
            ]);

        return $message;
    }
}










