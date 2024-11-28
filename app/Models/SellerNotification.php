<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerNotification extends Model
{
    protected $fillable = [
        'seller_id',
        'order_id',
        'title',
        'message',
        'is_read'
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}