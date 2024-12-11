<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\SellerNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 



class SellerNotificationService
{
   public function notifyNewOrder($order)
{
    try {
        $itemsQuery = "
             SELECT 
                p.seller_id,
                COUNT(oi.id) as item_count,
                SUM(oi.subtotal) as total_amount
            FROM order_items oi
            JOIN product_variants pv ON oi.product_variant_id = pv.id
            JOIN products p ON pv.product_id = p.id
            WHERE oi.order_id = ?
            GROUP BY p.seller_id
        ";
        $orderItems = DB::select($itemsQuery, [$order->id]);

        foreach ($orderItems as $item) {
            $insertSellerNotifQuery = "
                INSERT INTO seller_notifications 
                (seller_id, order_id, title, message, is_read, created_at, updated_at)
                VALUES 
                (?, ?, 'New Order Received', ?, false, NOW(), NOW())
            ";
            
            $message = sprintf(
                "You have received a new order with %d item(s) worth Rp %s", 
                $item->item_count, 
                number_format($item->total_amount, 0, ',', '.')
            );

            DB::insert($insertSellerNotifQuery, [
                $item->seller_id, 
                $order->id, 
                $message
            ]);
        }

        // Insert notifikasi untuk user
        $userNotifQuery = "
            INSERT INTO user_notifications 
            (user_id, order_id, title, message, is_read, created_at, updated_at)
            VALUES 
            (?, ?, 'Order Confirmation', ?, false, NOW(), NOW())
        ";

        $userMessage = sprintf(
            "Your order with %d item(s) worth Rp %s has been received by the seller.", 
            array_sum(array_column($orderItems, 'item_count')),
            number_format(array_sum(array_column($orderItems, 'total_amount')), 0, ',', '.')
        );

        DB::insert($userNotifQuery, [
            $order->user_id, 
            $order->id, 
            $userMessage
        ]);

    } catch (\Exception $e) {
        Log::error('Error creating notifications: ' . $e->getMessage());
        throw $e;
    }
}
 public function notifyOrderStatusUpdate(Order $order, string $newStatus) {
        try {
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
    $updateQuery = "
        UPDATE seller_notifications 
        SET is_read = true 
        WHERE id = ? AND seller_id = ?
    ";

    $affected = DB::update($updateQuery, [$id, Auth::id()]);

    if ($affected == 0) {
        throw new \Exception('Notification not found or not authorized');
    }

    return DB::table('seller_notifications')->find($id);
}
    public function markUserNotificationAsRead($id) {
       
          $updateQuery = "
        UPDATE user_notifications
        SET is_read = true 
        WHERE id = ? AND user_id = ?
    ";

    $affected = DB::update($updateQuery, [$id, Auth::id()]);

    if ($affected == 0) {
        throw new \Exception('Notification not found or not authorized');
    }

    return DB::table('user_notifications')->find($id);
    }
    public function deleteNotification($id)
    {
         $deleteQuery = "
            DELETE FROM seller_notifications 
            WHERE id = ? AND seller_id = ?
        ";

        $affected = DB::delete($deleteQuery, [$id, Auth::id()]);

        return $affected > 0;
    }
    public function deleteNotificationUser($id)
{
    try {
        $deleteQuery = "
            DELETE FROM user_notifications 
            WHERE id = ? AND user_id = ?
        ";

        $affected = DB::delete($deleteQuery, [$id, Auth::id()]);

        return $affected > 0;
    } catch (\Exception $e) {
        Log::error('Error deleting user notification: ' . $e->getMessage());
        throw $e;
    }
}

// Helper method untuk mendapatkan jumlah notifikasi yang belum dibaca untuk seller
public function getUnreadCount()
{
    try {
        $unreadCountQuery = "
            SELECT COUNT(*) as unread_count 
            FROM seller_notifications 
            WHERE seller_id = ? AND is_read = false
        ";

        $result = DB::selectOne($unreadCountQuery, [Auth::id()]);
        
        return $result->unread_count;
    } catch (\Exception $e) {
        Log::error('Error getting seller unread count: ' . $e->getMessage());
        return 0;
    }
}

// Helper method untuk mendapatkan jumlah notifikasi yang belum dibaca untuk user
public function getUserUnreadCount() 
{
    try {
        $unreadCountQuery = "
            SELECT COUNT(*) as unread_count 
            FROM user_notifications 
            WHERE user_id = ? AND is_read = false
        ";

        $result = DB::selectOne($unreadCountQuery, [Auth::id()]);
        
        return $result->unread_count;
    } catch (\Exception $e) {
        Log::error('Error getting user unread count: ' . $e->getMessage());
        return 0;
    }
}
}

