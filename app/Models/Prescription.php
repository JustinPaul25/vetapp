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
     * Visit instant for this prescription (from the linked appointment when present).
     */
    public function visitDateTime(): Carbon
    {
        if ($this->appointment) {
            return $this->appointment->visitDateTime();
        }

        return $this->created_at;
    }

    /**
     * Visit date/time for display ("issued on"). Uses the linked appointment's date/time
     * (e.g. walk-in visit day), not when the prescription row was saved.
     */
    public function issuedOnDisplay(string $format = 'Y-m-d g:i A'): string
    {
        return $this->visitDateTime()->format($format);
    }

    /**
     * Client/pet owner for labels (print, PDF, admin UI).
     * Prefer the patient's linked user so walk-ins still show the correct name when
     * appointments.user_id is null (column is nullable) but patients.user_id is set.
     */
    public function ownerUser(): ?User
    {
        return $this->patient?->user ?? $this->appointment?->user;
    }

    public function ownerDisplayName(): string
    {
        $user = $this->ownerUser();
        if (! $user) {
            return 'N/A';
        }

        $fromParts = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $fromParts !== '' ? $fromParts : ($user->name ?: 'N/A');
    }
}
