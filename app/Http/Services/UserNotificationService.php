<?php


namespace App\Http\Services;

use App\Models\Order;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Tambahkan import ini



class SellerNotificationService
{
   public function notifyNewOrder(Order $order)
{
    try {
        // Eager load relationships untuk optimasi
        $order->load('items.productVariant.product');
        
        // Grup items berdasarkan seller
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
            
            UserNotification::create([
                'user_id' => $sellerId,
                'order_id' => $order->id,
                'title' => 'New Order Received',
                'message' => "You have received a new order with {$itemCount} item(s) worth Rp " .
                           number_format($totalAmount, 0, ',', '.'),
            ]);
        }
        
    } catch (\Exception $e) {
        Log::error('Error creating seller notification: ' . $e->getMessage());
        throw $e;
    }
}

public function getNotifications()
    {
        $sellerId = Auth::id();
        
       $notifications = UserNotification::where('seller_id', $sellerId)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    return [
        'data' => $notifications,
        'total_unread' => UserNotification::where('seller_id', $sellerId)
            ->where('is_read', false)
            ->count()
    ];
    }

    public function markAsRead($id)
    {
        $notification = UserNotification::where('seller_id', Auth::id())
            ->findOrFail($id);
            
        $notification->update(['is_read' => true]);
        
        return $notification;
    }

    public function deleteNotification($id)
    {
        $notification = UserNotification::where('seller_id', Auth::id())
            ->findOrFail($id);
            
        return $notification->delete();
    }

    // Helper method untuk mendapatkan jumlah notifikasi yang belum dibaca
    public function getUnreadCount()
    {
        return UserNotification::where('seller_id', Auth::id())
            ->where('is_read', false)
            ->count();
    }
}

