<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Http\Services\SellerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OrderResource;
use Illuminate\Support\Str;


class CartController extends Controller
{
    protected $sellerNotificationService;

    public function __construct(SellerNotificationService $sellerNotificationService)
    {
        $this->sellerNotificationService = $sellerNotificationService;
    }
 public function getOrCreateCart()
{
    $user = auth()->user();
    
    // Check existing cart
    $cart = DB::select(
        "SELECT id, user_id, status, total_amount 
         FROM orders 
         WHERE user_id = ? AND status = 'cart' 
         LIMIT 1",
        [$user->id]
    );
    
    if (empty($cart)) {
        // Create new cart
        $cartId = (string) Str::uuid();
        DB::insert(
            "INSERT INTO orders (id, user_id, status, total_amount, created_at, updated_at)
             VALUES (?, ?, 'cart', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [$cartId, $user->id]
        );
    } else {
        $cartId = $cart[0]->id;
    }
    
    // Get complete cart data
    $cartData = DB::select(
        "SELECT 
            o.id,
            o.user_id,
            o.status,
            o.total_amount,
            o.shipping_address,
            o.payment_method,
            o.checkout_at,
            o.created_at as order_created_at,
            o.updated_at as order_updated_at,
            
            oi.id as order_item_id,
            oi.quantity,
            oi.price,
            oi.subtotal,
            oi.created_at as order_item_created_at,
            oi.updated_at as order_item_updated_at,
            
            pv.id as product_variant_id,
            pv.image as product_variant_image,
            
            i.id as inventory_id,
            i.quantity as stock_quantity,
            i.size  as inventory_size,
            
            p.id as product_id,
            p.name as product_name,
            
            c.id as category_id,
            c.name as category_name
         FROM orders o
         LEFT JOIN order_items oi ON o.id = oi.order_id
         LEFT JOIN product_variants pv ON oi.product_variant_id = pv.id
         LEFT JOIN inventories i ON oi.inventory_id = i.id
         LEFT JOIN products p ON pv.product_id = p.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE o.id = ?",
        [$cartId]
    );
    
    // Transform flat data into nested structure
    $formattedCart = null;
    $orderItems = [];
    
    foreach ($cartData as $row) {
        if (!$formattedCart) {
            $formattedCart = [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'status' => $row->status,
                'total_amount' => $row->total_amount,
                'shipping_address' => $row->shipping_address,
                'payment_method' => $row->payment_method,
                'checkout_at' => $row->checkout_at,
                'created_at' => $row->order_created_at,
                'updated_at' => $row->order_updated_at,
                'order_items' => []
            ];
        }
        
        if ($row->order_item_id) {
            $orderItems[$row->order_item_id] = [
                'id' => $row->order_item_id,
                'quantity' => $row->quantity,
                'price' => $row->price,
                'subtotal' => $row->subtotal,
                'created_at' => $row->order_item_created_at,
                'updated_at' => $row->order_item_updated_at,
                'product_variant' => [
                    'id' => $row->product_variant_id,
                    'image'  =>  $row-> product_variant_image,
                    
                    'product' => [
                        'id' => $row->product_id,
                        'name' => $row->product_name,
                        'category' => [
                            'id' => $row->category_id,
                            'name' => $row->category_name
                        ]
                    ]
                ],
                'inventory' => [
                    'id' => $row->inventory_id,
                    'quantity' => $row->stock_quantity,
                    'size'  => $row->inventory_size
                ]
            ];
        }
    }
    
    if ($formattedCart) {
        $formattedCart['order_items'] = array_values($orderItems);
    }
    
    return response()->json([
        'data' =>  $formattedCart,
        'status'  => 'success'
    ]);
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
        
        // Find existing cart
        $cart = DB::select(
            "SELECT id, user_id, status, total_amount 
             FROM orders 
             WHERE user_id = ? AND status = 'cart' 
             LIMIT 1",
            [$userId]
        );

        // Create new cart if doesn't exist
        if (empty($cart)) {
            $cartId = Str::random(20);
            DB::insert(
                "INSERT INTO orders (id, user_id, status, total_amount, created_at, updated_at)
                 VALUES (?, ?, 'cart', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [$cartId, $userId]
            );
        } else {
            $cartId = $cart[0]->id;
        }

        // Get variant price
        $variant = DB::select(
            "SELECT price FROM product_variants WHERE id = ? LIMIT 1",
            [$request->input('variant_id')]
        );

        if (empty($variant)) {
            throw new \Exception('Product variant not found');
        }

        $variantPrice = $variant[0]->price;

        // Check existing cart item
        $existingItem = DB::select(
            "SELECT id, quantity 
             FROM order_items 
             WHERE order_id = ? 
             AND product_variant_id = ? 
             AND inventory_id = ? 
             LIMIT 1",
            [
                $cartId,
                $request->input('variant_id'),
                $request->input('inventory_id')
            ]
        );

        if (!empty($existingItem)) {
            // Update existing item
            $newQuantity = $existingItem[0]->quantity + $request->input('quantity');
            $newSubtotal = $variantPrice * $newQuantity;

            DB::update(
                "UPDATE order_items 
                 SET quantity = ?, 
                     subtotal = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$newQuantity, $newSubtotal, $existingItem[0]->id]
            );
        } else {
            // Create new item
            DB::insert(
                "INSERT INTO order_items 
                 (id, order_id, product_variant_id, inventory_id, quantity, price, subtotal, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [
                    Str::random(20),
                    $cartId,
                    $request->input('variant_id'),
                    $request->input('inventory_id'),
                    $request->input('quantity'),
                    $variantPrice,
                    $variantPrice * $request->input('quantity')
                ]
            );
        }

        // Update cart total
        DB::update(
            "UPDATE orders 
             SET total_amount = (
                SELECT SUM(subtotal) 
                FROM order_items 
                WHERE order_id = ?
             ),
             updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$cartId, $cartId]
        );

        DB::commit();

        return response()->json([
            'message' => 'Item added to cart successfully',
            'cartId' => $cartId
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
        'payment_method' => 'required|string',
        'bank_name'  =>  'nullable|string',
        'virtual_account'  => 'nullable|string'
    ]);

    $userId = auth()->id();

    try {
        DB::beginTransaction();

        // Cari order cart aktif
        $cartQuery = "
            SELECT id, total_amount 
            FROM orders 
            WHERE user_id = ? AND status = 'cart'
        ";
        $cart = DB::selectOne($cartQuery, [$userId]);

        if (!$cart) {
            return response()->json(['message' => 'Cart kosong'], 400);
        }

        // Cek apakah cart memiliki items
        $itemsQuery = "
            SELECT COUNT(*) as item_count 
            FROM order_items 
            WHERE order_id = ?
        ";
        $itemCount = DB::selectOne($itemsQuery, [$cart->id])->item_count;

        if ($itemCount == 0) {
            return response()->json(['message' => 'Cart kosong'], 400);
        }

        // Update order dari cart ke pending
        $updateOrderQuery = "
            UPDATE orders 
            SET 
                status = 'pending', 
                shipping_address = ?, 
                payment_method = ?, 
                bank_name = ?, 
                virtual_account = ?, 
                checkout_at = NOW()
            WHERE id = ?
        ";
        DB::update($updateOrderQuery, [
            $validated['shipping_address'],
            $validated['payment_method'],
            $validated['bank_name'] ?? null,
            $validated['virtual_account']  ?? null,
            $cart->id
        ]);

        // Ambil order yang baru diupdate
        $orderQuery = "
            SELECT o.*, 
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', oi.id, 
                    'product_variant_id', oi.product_variant_id, 
                    'quantity', oi.quantity, 
                    'price', oi.price, 
                    'subtotal', oi.subtotal
                )
            ) as order_items
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.id = ?
            GROUP BY o.id
        ";
        $order = DB::selectOne($orderQuery, [$cart->id]);

        $this->sellerNotificationService->notifyNewOrder($order);

        DB::commit();

        return response()->json($order);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Checkout error', [
            'user_id' => $userId,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Checkout gagal',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 400);
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
public function getSellerOrders()
{
    $seller = auth()->user();

    $query = "
        SELECT 
            o.id AS order_id,
            o.user_id,
            u.name AS user_name,
            u.email AS user_email,
            o.status,
            o.total_amount,
            o.shipping_address,
            o.payment_method,
            o.virtual_account,
            o.bank_name,
            o.checkout_at,
            o.created_at,
            oi.id AS order_item_id,
            oi.quantity,
            oi.price,
            oi.subtotal,
            pv.id AS product_variant_id,
            pv.color,
            pv.sku,
            pv.image,
            p.id AS product_id,
            p.name AS product_name,
            p.description AS product_description,
            c.id AS category_id,
            c.name AS category_name,
            i.size,
            i.quantity AS inventory_quantity
        FROM 
            orders o
        JOIN 
            users u ON o.user_id = u.id
        JOIN 
            order_items oi ON o.id = oi.order_id
        JOIN 
            product_variants pv ON oi.product_variant_id = pv.id
        JOIN 
            products p ON pv.product_id = p.id
        JOIN 
            categories c ON p.category_id = c.id
        JOIN 
            inventories i ON oi.inventory_id = i.id
        WHERE 
            p.seller_id = ? 
            AND o.status != 'cart'
        ORDER BY 
            o.created_at DESC
    ";

    $orders = DB::select($query, [$seller->id]);

    // Transformasi hasil query menjadi struktur yang diinginkan
    $groupedOrders = collect($orders)->groupBy('order_id')->map(function ($items) {
        $firstItem = $items[0];
        return [
            'id' => $firstItem->order_id,
            'user' => [
                'id' => $firstItem->user_id,
                'name' => $firstItem->user_name,
                'email' => $firstItem->user_email,
            ],
            'status' => $firstItem->status,
            'total_amount' => $firstItem->total_amount,
            'shipping_address' => $firstItem->shipping_address,
            'payment_method' => $firstItem->payment_method,
            'bank_name'  => $firstItem->bank_name,
            'virtual_account'  => $firstItem->virtual_account,
            'checkout_at' => $firstItem->checkout_at,
            'created_at' => $firstItem->created_at,
            'order_items' => $items->map(function ($item) {
                return [
                    'id' => $item->order_item_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'product_variant' => [
                        'id' => $item->product_variant_id,
                        'color' => $item->color,
                        'sku' => $item->sku,
                        'image' => $item->image,
                        'product' => [
                            'id' => $item->product_id,
                            'name' => $item->product_name,
                            'description' => $item->product_description,
                            'category' => [
                                'id' => $item->category_id,
                                'name' => $item->category_name,
                            ]
                        ]
                    ],
                    'inventory' => [
                        'size' => $item->size,
                        'quantity' => $item->inventory_quantity
                    ]
                ];
            })
        ];
    })->values();

    return response()->json([
        'data' => $groupedOrders
    ]);
}

// Untuk laporan penjualan, tambahkan informasi pembeli
public function getSellerSalesReport()
{
    $seller = auth()->user();

    $topSellingProductsQuery = "
        SELECT 
            pv.id AS product_variant_id,
            p.name AS product_name,
            SUM(oi.quantity) AS total_quantity,
            SUM(oi.subtotal) AS total_revenue,
            COUNT(DISTINCT o.user_id) AS unique_buyers
        FROM 
            order_items oi
        JOIN 
            orders o ON oi.order_id = o.id
        JOIN 
            product_variants pv ON oi.product_variant_id = pv.id
        JOIN 
            products p ON pv.product_id = p.id
        WHERE 
            p.seller_id = ?
        GROUP BY 
            pv.id, p.name
        ORDER BY 
            total_quantity DESC
        LIMIT 5
    ";

    $buyerBreakdownQuery = "
        SELECT 
            u.id AS user_id,
            u.name AS user_name,
            u.email AS user_email,
            COUNT(DISTINCT o.id) AS total_orders,
            SUM(o.total_amount) AS total_spent
        FROM 
            orders o
        JOIN 
            order_items oi ON o.id = oi.order_id
        JOIN 
            product_variants pv ON oi.product_variant_id = pv.id
        JOIN 
            products p ON pv.product_id = p.id
        JOIN 
            users u ON o.user_id = u.id
        WHERE 
            p.seller_id = ?
            AND o.status != 'cart'
        GROUP BY 
            u.id, u.name, u.email
        ORDER BY 
            total_spent DESC
        LIMIT 10
    ";

    $topSellingProducts = DB::select($topSellingProductsQuery, [$seller->id]);
    $buyerBreakdown = DB::select($buyerBreakdownQuery, [$seller->id]);

    return response()->json([
        'top_selling_products' => $topSellingProducts,
        'buyer_breakdown' => $buyerBreakdown
    ]);
}

public function getBuyerOrderDetails($buyerId)
{
    $seller = auth()->user();

    $buyerOrdersQuery = "
        SELECT 
            o.id AS order_id,
            o.status,
            o.total_amount,
            o.created_at,
            oi.quantity,
            oi.price,
            oi.subtotal,
            p.name AS product_name,
            pv.color,
            pv.sku
        FROM 
            orders o
        JOIN 
            order_items oi ON o.id = oi.order_id
        JOIN 
            product_variants pv ON oi.product_variant_id = pv.id
        JOIN 
            products p ON pv.product_id = p.id
        WHERE 
            p.seller_id = ?
            AND o.user_id = ?
            AND o.status != 'cart'
        ORDER BY 
            o.created_at DESC
    ";

    $buyerOrders = DB::select($buyerOrdersQuery, [$seller->id, $buyerId]);

    return response()->json([
        'buyer_orders' => $buyerOrders
    ]);
}

}