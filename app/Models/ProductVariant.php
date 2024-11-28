<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class ProductVariant extends Model
{
    public $incrementing = false; // Nonaktifkan auto-increment
    protected $keyType = 'string'; // Gunakan string untuk primary key

    protected $fillable = [
        'id', 'product_id', 'color', 'image', 'price', 'sku'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::random(20); // Generate string ID acak
        });
    }

    // Relasi dengan model lain
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_variant_id', 'id');
    }
}

