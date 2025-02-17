<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayHereLog extends Model
{
    protected $fillable = [
        'order_id',
        'merchant_id',
        'payhere_amount',
        'payhere_currency',
        'status_code',
        'md5sig',
        'status_message',
        'authorization_token',
        'error_message',
        'is_success'
    ];

    protected $casts = [
        'is_success' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}