<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function medicines()
    {
        return $this->belongsToMany(Medicine::class, 'disease_medicines');
    }

    public function symptoms()
    {
        return $this->belongsToMany(Symptom::class, 'disease_symptoms');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function diagnoses()
    {
        return $this->hasMany(PrescriptionDiagnosis::class);
    }
}
