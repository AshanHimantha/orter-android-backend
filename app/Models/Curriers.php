<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class curriers extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'charge',
        'extra_per_kg',
        'description',
        'is_active'
    ];

    protected $casts = [
        'charge' => 'decimal:2',
        'extra_per_kg' => 'decimal:2',
        'is_active' => 'boolean'
    ];
}