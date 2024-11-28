<?php

namespace App\Http\Controllers;

use App\Http\Services\SellerNotificationService;
use Illuminate\Http\Request;

class NotificationSellerController extends Controller
{
    protected $notificationService;

    public function __construct(SellerNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $notifications = $this->notificationService->getNotifications();
        return response()->json($notifications);
    }
     public function userIndex()
    {
        $notifications = $this->notificationService->getUserNotifications();
        return response()->json($notifications);
    }
 public function markAsReadUser($id)
    {
        $notification = $this->notificationService->markUserNotificationAsRead($id);
        return response()->json($notification);
    }

   
    public function markAsRead($id)
    {
        $notification = $this->notificationService->markAsRead($id);
        return response()->json($notification);
    }
     

    public function destroy($id)
    {
        $this->notificationService->deleteNotification($id);
        return response()->json(['message' => 'Notification deleted successfully']);
    }
    public function destroyUser($id)
    {
        $this->notificationService->deleteNotificationUser($id);
        return response()->json(['message' => 'Notification deleted successfully']);
    }

}