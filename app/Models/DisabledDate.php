<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisabledDate extends Model
{
    protected $fillable = [
        'date',
        'reason',
        'disabled_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the user who disabled this date.
     */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }
}
