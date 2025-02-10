<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Cart extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'firebase_uid',
        'stock_id',
        'size',
        'quantity',
        'is_active'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'firebase_uid', 'firebase_uid');
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class)->withTrashed();
    }
}