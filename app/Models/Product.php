<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'added_by',
        'gender_id',
        'category_id',
        'collection_id',
        'name',
        'description',
        'price',
        'material',
        'color',
        'main_image',
        'image_1',
        'image_2',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    protected $appends = ['main_image_url', 'image_1_url', 'image_2_url'];

    public function addedBy()
    {
        return $this->belongsTo(Admin::class, 'added_by');
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function getMainImageUrlAttribute()
    {
        return $this->main_image ? asset('storage/' . $this->main_image) : null;
    }

    public function getImage1UrlAttribute()
    {
        return $this->image_1 ? asset(path: 'storage/' . $this->image_1) : null;
    }

    public function getImage2UrlAttribute()
    {
        return $this->image_2 ? asset('storage/' . $this->image_2) : null;
    }
}
