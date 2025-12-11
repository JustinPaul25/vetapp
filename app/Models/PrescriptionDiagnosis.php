<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionDiagnosis extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function disease()
    {
        return $this->belongsTo(Disease::class);
    }
}
