<?php
namespace App\Models;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class Order extends Model
{
    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // Set primary key type to string
    protected $fillable = [
        'user_id',
        'bank_name',
        'virtual_account', 
        'status', 
        'total_amount', 
        'shipping_address', 
        'payment_method',
        'checkout_at'
    ];
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::random(20);
        });
    }

    protected $dates = ['checkout_at'];

    // Relasi dengan user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi dengan order items
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    // Scope untuk mendapatkan cart aktif user
    public function scopeActiveCart($query, $userId)
    {
        return $query->where('user_id', $userId)
                     ->where('status', 'cart')
                     ->first();
    }

   public static function getOrCreateCart($userId = null)
{
    $userId = $userId ?? auth()->id();
    
    return self::firstOrCreate(
        [
            'user_id' => $userId,
            'status' => 'cart'
        ],
        [
            'id' => Str::random(20), // Ensure a random ID is set
            'total_amount' => 0
        ]
    );
}

    // Method untuk menambah item ke cart
 public function addItem($variantId, $inventoryId, $quantity)
{
    // Pastikan order sudah tersimpan dengan ID yang valid
    if (!$this->exists || !$this->id) {
        $this->id = Str::random(20);
        $this->save();
    }

    $variant = ProductVariant::findOrFail($variantId);

    // Cek apakah item sudah ada di cart
    $existingItem = $this->orderItems()
        ->where('product_variant_id', $variantId)
        ->where('inventory_id', $inventoryId)
        ->first();

    if ($existingItem) {
        $existingItem->update([
            'quantity' => $existingItem->quantity + $quantity,
            'subtotal' => $variant->price * ($existingItem->quantity + $quantity)
        ]);
    } else {
        $this->orderItems()->create([
            'product_variant_id' => $variantId,
            'inventory_id' => $inventoryId,
            'quantity' => $quantity,
            'price' => $variant->price,
            'subtotal' => $variant->price * $quantity,
            'order_id' => $this->id // Eksplisit set order_id
        ]);
    }

    $this->updateTotalAmount();
}
    // Method untuk update total amount
    public function updateTotalAmount()
    {
        $total = $this->orderItems()->sum('subtotal');
        $this->update(['total_amount' => $total]);
    }

    // Method untuk checkout
   public function checkout($shippingAddress, $paymentMethod,$bankName,$virtualAccount)
    {
        return DB::transaction(function () use ($shippingAddress, $paymentMethod,$bankName,$virtualAccount) {
            // Validate cart is not empty
            if ($this->orderItems()->count() === 0) {
                throw new \Exception("Cannot checkout an empty cart");
            }

            // Stock validation
            foreach ($this->orderItems as $item) {
                $inventory = $item->inventory;
                if ($inventory->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for {$item->productVariant->name}");
                }
            }

            // Reduce stock
            foreach ($this->orderItems as $item) {
                $inventory = $item->inventory;
                $inventory->decrement('quantity', $item->quantity);
            }

            // Update order status
            $this->update([
                'status' => 'pending',
                'shipping_address' => $shippingAddress,
                'payment_method' => $paymentMethod,
                'bank_name'  => $bankName,
                'virtual_account'  => $virtualAccount,
                'checkout_at' => now()
            ]);
            $order = $this->fresh(['orderItems.productVariant.product']);


           dispatch(function () use ($order) {
                try {
                    // Notifikasi ke seller
                    app(SellerNotificationService::class)->notifyNewOrder($order);
                    
                    // Notifikasi ke user
                    // app(UserNotificationService::class)->notifyOrderSuccess($order);
                } catch (\Exception $e) {
                    // Log error tapi tidak membatalkan transaction
                }
            })->afterCommit();

            return $order;
        
        });
    }
}

class OrderItem extends Model

{
     public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'order_id', 
        'product_variant_id', 
        'inventory_id', 
        'quantity', 
        'price', 
        'subtotal'
    ];
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::random(20);
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}