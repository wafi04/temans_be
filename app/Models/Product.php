<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model {
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'description', 'seller_id', 'category_id'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::random(20);
        });
    }

    // Relasi dengan model lain
    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    // Tambahkan relasi seller
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    // Tambahkan relasi category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}