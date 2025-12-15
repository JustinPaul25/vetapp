<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'pet_birth_date' => 'date',
    ];

    // Keep for backward compatibility - returns appointments via patient_id
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // Many-to-many relationship for appointments
    public function appointmentPatients()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_patient')
            ->withTimestamps();
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function petType()
    {
        return $this->belongsTo(PetType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function weightHistory()
    {
        return $this->hasMany(PatientWeightHistory::class)->orderBy('recorded_at', 'desc');
    }
}
