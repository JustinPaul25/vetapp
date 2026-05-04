<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    // Keep for backward compatibility - returns first appointment type
    public function appointment_type()
    {
        return $this->belongsTo(AppointmentType::class);
    }

    // Many-to-many relationship for multiple appointment types
    public function appointment_types()
    {
        return $this->belongsToMany(AppointmentType::class, 'appointment_appointment_type')
            ->withTimestamps();
    }

    // Keep for backward compatibility - returns first patient
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // Many-to-many relationship for multiple patients
    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'appointment_patient')
            ->withTimestamps();
    }

    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diagnoses()
    {
        return $this->hasMany(PrescriptionDiagnosis::class);
    }

    /**
     * Visit instant for this appointment (scheduled date + time). Used for weight/prescription
     * display so history matches visit time, not when the row was saved.
     */
    public function visitDateTime(): Carbon
    {
        if (! $this->appointment_date) {
            return $this->created_at;
        }

        $dateStr = $this->appointment_date->format('Y-m-d');
        $timeRaw = $this->appointment_time;
        $timePart = ($timeRaw !== null && $timeRaw !== '') ? (string) $timeRaw : '00:00:00';

        try {
            return Carbon::parse($dateStr.' '.$timePart);
        } catch (\Exception $e) {
            return $this->created_at;
        }
    }
}
