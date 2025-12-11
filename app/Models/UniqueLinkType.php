<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniqueLinkType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function uniqueLinks()
    {
        return $this->hasMany(UniqueLink::class, 'link_type_id');
    }
}
