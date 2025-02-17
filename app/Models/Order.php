<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'pickup_id',
        'firebase_uid',
        'delivery_type',
        'branch_id',
        'delivery_name',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'status',
        'notes',
        'payment_method',
        'payment_status',
        'transaction_id'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'firebase_uid', 'firebase_uid');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}