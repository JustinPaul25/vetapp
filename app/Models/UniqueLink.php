<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniqueLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date_expiry' => 'datetime',
        'date_processed' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function linkType()
    {
        return $this->belongsTo(UniqueLinkType::class, 'link_type_id');
    }
}
