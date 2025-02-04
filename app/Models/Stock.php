<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'xs_quantity',
        's_quantity',
        'm_quantity',
        'l_quantity',
        'xl_quantity',
        'xxl_quantity',
        'is_active'
    ];

    protected $casts = [
        'xs_quantity' => 'integer',
        's_quantity' => 'integer',
        'm_quantity' => 'integer',
        'l_quantity' => 'integer',
        'xl_quantity' => 'integer',
        'xxl_quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    protected $appends = ['total_quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalQuantityAttribute()
    {
        return $this->xs_quantity +
               $this->s_quantity +
               $this->m_quantity +
               $this->l_quantity +
               $this->xl_quantity +
               $this->xxl_quantity;
    }
}
