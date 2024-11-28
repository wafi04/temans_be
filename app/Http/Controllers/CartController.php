<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Http\Services\SellerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OrderResource;
use Illuminate\Support\Str;


class CartController extends Controller
{
    protected $sellerNotificationService;

    public function __construct(SellerNotificationService $sellerNotificationService)
    {
        $this->sellerNotificationService = $sellerNotificationService;
    }
    // Dapatkan atau buat cart aktif
 public function getOrCreateCart()
{
    $user = auth()->user();
    $cart = Order::activeCart($user->id);

    if (!$cart) {
        $cart = Order::create([
            'user_id' => $user->id,
            'status' => 'cart',
            'total_amount' => 0
        ]);
    }

    // Load order items with product variant, inventory, and product
     return response()->json(
        $cart->load([
            'orderItems' => function ($query) {
                $query->with([
                    'productVariant' => function ($query) {
                        $query->with([
                            'product' => function ($query) {
                                $query->with('category'); // Load category for each product
                            }
                        ]); 
                    }, 
                    'inventory'
                ]);
            }
        ])
    );
}
public function addToCart(Request $request)
{
    $request->validate([
        'variant_id' => 'required|exists:product_variants,id',
        'inventory_id' => 'required|exists:inventories,id',
        'quantity' => 'required|integer|min:1'
    ]);

    DB::beginTransaction();
    try {
        $userId = auth()->id();

        // Find existing cart or create a new one
        $cart = Order::where('user_id', $userId)
            ->where('status', 'cart')
            ->first();

        if (!$cart) {
            $cart = new Order();
            $cart->id = Str::random(20);
            $cart->user_id = $userId;
            $cart->status = 'cart';
            $cart->total_amount = 0;
            $cart->save();
        }

        // Add item to cart
        $variant = ProductVariant::findOrFail($request->input('variant_id'));
        
        // Check existing cart item
        $existingItem = OrderItem::where('order_id', $cart->id)
            ->where('product_variant_id', $request->input('variant_id'))
            ->where('inventory_id', $request->input('inventory_id'))
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $request->input('quantity'),
                'subtotal' => $variant->price * ($existingItem->quantity + $request->input('quantity'))
            ]);
        } else {
            OrderItem::create([
                'id' => Str::random(20),
                'order_id' => $cart->id,
                'product_variant_id' => $request->input('variant_id'),
                'inventory_id' => $request->input('inventory_id'),
                'quantity' => $request->input('quantity'),
                'price' => $variant->price,
                'subtotal' => $variant->price * $request->input('quantity')
            ]);
        }

        // Update cart total
        $cart->updateTotalAmount();

        DB::commit();

        return response()->json([
            'message' => 'Item added to cart successfully',
            'cart_id' => $cart->id
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error adding item to cart',
            'error' => $e->getMessage()
        ], 400);
    }
}
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'shipping_address' => 'required|string',
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();
        $cart = Order::activeCart($user->id);

        if (!$cart || $cart->orderItems->isEmpty()) {
            return response()->json(['message' => 'Cart kosong'], 400);
        }

        try {
            $order = $cart->checkout(
                $validated['shipping_address'], 
                $validated['payment_method']
            );
            $this->sellerNotificationService->notifyNewOrder($order);

            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
   public function removeOrderItem($itemId)
{
    DB::beginTransaction();
    try {
        $user = auth()->user();
        $cart = Order::activeCart($user->id);

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $orderItem = $cart->orderItems()->where('id', $itemId)->first();

        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        // Remove the order item
        $orderItem->delete();

        // Recalculate cart total
        $cart->updateTotalAmount(); // Gunakan method yang sama dengan saat menambah item

        DB::commit();

        return response()->json([
            'message' => 'Order item removed successfully',
            'cart' => $cart->load('orderItems')
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error removing order item',
            'error' => $e->getMessage()
        ], 400);
    }
}
public function clearCart()
{
    DB::beginTransaction();
    try {
        $user = auth()->user();
        $cart = Order::activeCart($user->id);

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        // Remove all order items for this cart
        $cart->orderItems()->delete();

        // Reset total amount menggunakan method update
        $cart->total_amount = 0;
        $cart->save();

        DB::commit();

        return response()->json([
            'message' => 'Cart cleared successfully',
            'cart' => $cart
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error clearing cart',
            'error' => $e->getMessage()
        ], 400);
    }
}
// Update order item quantity
public function updateOrderItemQuantity(Request $request, $itemId)
{
    $validated = $request->validate([
        'quantity' => 'required|integer|min:1'
    ]);

    $user = auth()->user();
    $cart = Order::activeCart($user->id);

    if (!$cart) {
        return response()->json(['message' => 'Cart not found'], 404);
    }

    $orderItem = $cart->orderItems()->where('id', $itemId)->first();

    if (!$orderItem) {
        return response()->json(['message' => 'Order item not found'], 404);
    }

    // Update quantity and recalculate subtotal
    $orderItem->quantity = $validated['quantity'];
    $orderItem->subtotal = $orderItem->price * $validated['quantity'];
    $orderItem->save();

    // Recalculate cart total
    $cart->recalculateTotalAmount();

    return response()->json([
        'message' => 'Order item quantity updated',
        'orderItem' => $orderItem,
        'cart' => $cart->load('orderItems')
    ]);
}
public function show(Order $order)
{
    return new OrderResource($order);
}
public function getUserOrders()
{
    $user = auth()->user();

    // Retrieve all orders except those in 'cart' status, ordered by most recent first
    $orders = Order::where('user_id', $user->id)
        ->where('status', '!=', 'cart')
      ->with([
            'orderItems' => function ($query) {
                $query->with([
                    'productVariant' => function ($query) {
                        $query->with([
                            'product' => function ($query) {
                                $query->with('category'); // Load category for each product
                            }
                        ]);
                    },
                    'inventory'
                ]);
            }
        ])
        ->orderByDesc('created_at')
        ->get();

    return response()->json([
        'data' => $orders
    ]);
}

}