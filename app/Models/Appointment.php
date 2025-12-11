<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function appointment_type()
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
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
}
