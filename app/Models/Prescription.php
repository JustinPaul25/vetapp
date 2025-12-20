<?php

namespace App\Models;

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
}
