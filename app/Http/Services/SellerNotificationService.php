<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\SellerNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Tambahkan import ini



class SellerNotificationService
{
   public function notifyNewOrder(Order $order)
{
    try {
            // Eager load relationships for optimization
            $order->load('items.productVariant.product', 'user');

            // Group items by seller
            $sellerItems = [];

            foreach ($order->items as $item) {
                if (!$item->productVariant?->product?->seller_id) {
                    Log::warning('Missing seller_id for item: ' . $item->id);
                    continue;
                }

                $sellerId = $item->productVariant->product->seller_id;

                if (!isset($sellerItems[$sellerId])) {
                    $sellerItems[$sellerId] = [];
                }

                $sellerItems[$sellerId][] = $item;
            }

            foreach ($sellerItems as $sellerId => $items) {
                $itemCount = count($items);
                $totalAmount = collect($items)->sum('subtotal');

                SellerNotification::create([
                    'seller_id' => $sellerId,
                    'order_id' => $order->id,
                    'title' => 'New Order Received',
                    'message' => "You have received a new order with {$itemCount} item(s) worth Rp " .
                        number_format($totalAmount, 0, ',', '.')
                ]);
            }

            // Notify the order user
            UserNotification::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'title' => 'Order Confirmation',
                'message' => "Your order with {$itemCount} item(s) worth Rp " .
                    number_format($totalAmount, 0, ',', '.') . " has been received by the seller."
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating notifications: ' . $e->getMessage());
            throw $e;
        }
}
 public function notifyOrderStatusUpdate(Order $order, string $newStatus) {
        try {
            // Notify the order user about status update
            UserNotification::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'title' => 'Order Status Update',
                'message' => "Your order status has been updated to: {$newStatus}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating user notification for order status: ' . $e->getMessage());
            throw $e;
        }
    }

public function getNotifications()
    {
        $sellerId = Auth::id();
        
       $notifications = SellerNotification::where('seller_id', $sellerId)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    return [
        'data' => $notifications,
        'total_unread' => SellerNotification::where('seller_id', $sellerId)
            ->where('is_read', false)
            ->count()
    ];
    }
    public function getUserNotifications() {
        $userId = Auth::id();
        $notifications = UserNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'data' => $notifications,
            'total_unread' => UserNotification::where('user_id', $userId)
                ->where('is_read', false)
                ->count()
        ];
    }

    public function markAsRead($id)
    {
        $notification = SellerNotification::where('seller_id', Auth::id())
            ->findOrFail($id);
            
        $notification->update(['is_read' => true]);
        
        return $notification;
    }

    public function markUserNotificationAsRead($id) {
        $notification = UserNotification::where('user_id', Auth::id())
            ->findOrFail($id);

        $notification->update(['is_read' => true]);
        return $notification;
    }
    public function deleteNotification($id)
    {
        $notification = SellerNotification::where('seller_id', Auth::id())
            ->findOrFail($id);
            
        return $notification->delete();
    }
     public function deleteNotificationUser($id)
    {
        $notification = UserNotification::where('user_id', Auth::id())
            ->findOrFail($id);
            
        return $notification->delete();
    }


    // Helper method untuk mendapatkan jumlah notifikasi yang belum dibaca
    public function getUnreadCount()
    {
        return SellerNotification::where('seller_id', Auth::id())
            ->where('is_read', false)
            ->count();
    }
    public function getUserUnreadCount() {
        return UserNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();
    }
}

