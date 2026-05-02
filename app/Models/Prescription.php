<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'follow_up_date' => 'date',
        'follow_up_notified_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function disease()
    {
        return $this->belongsTo(Disease::class);
    }

    public function medicines()
    {
        return $this->hasMany(PrescriptionMedicine::class);
    }

    public function diagnoses()
    {
        return $this->hasMany(PrescriptionDiagnosis::class);
    }

    /**
     * Visit date/time for display ("issued on"). Uses the linked appointment's date/time
     * (e.g. walk-in visit day), not when the prescription row was saved.
     */
    public function issuedOnDisplay(string $format = 'Y-m-d g:i A'): string
    {
        $appointment = $this->appointment;
        if (! $appointment || ! $appointment->appointment_date) {
            return $this->created_at->format($format);
        }

        if ($format === 'Y-m-d') {
            return $appointment->appointment_date->format('Y-m-d');
        }

        $dateStr = $appointment->appointment_date->format('Y-m-d');
        $timeRaw = $appointment->appointment_time;
        $timePart = ($timeRaw !== null && $timeRaw !== '') ? $timeRaw : '00:00:00';

        try {
            return Carbon::parse($dateStr.' '.$timePart)->format($format);
        } catch (\Exception $e) {
            return $this->created_at->format($format);
        }
    }
}
