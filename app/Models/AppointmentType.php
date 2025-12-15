<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentType extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Keep for backward compatibility - hasMany for single appointment_type_id
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // Many-to-many relationship
    public function appointmentsMany()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_appointment_type')
            ->withTimestamps();
    }
}
