<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'admin') {
            return response()->json([
                'status' => true,
                'message' => 'Welcome to Admin Dashboard',
                'data' => [
                    'stats' => [
                        'total_users' => 100,
                        'total_orders' => 500,
                        'revenue' => 50000
                    ]
                ]
            ]);
        }

        if ($user->role === 'user') {
            return response()->json([
                'status' => true,
                'message' => 'Welcome to User Dashboard',
                'data' => [
                    'stats' => [
                        'your_orders' => 5,
                        'points' => 100,
                        'notifications' => 3
                    ]
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid role'
        ], 403);
    }

    public function adminOnly()
    {
        return response()->json([
            'status' => true,
            'message' => 'Admin Only Content',
            'data' => [
                'sensitive_data' => 'This is admin only data',
                'admin_stats' => [
                    'system_health' => 'good',
                    'pending_approvals' => 10
                ]
            ]
        ]);
    }

    public function userOnly()
    {
        return response()->json([
            'status' => true,
            'message' => 'User Only Content',
            'data' => [
                'user_specific_data' => 'This is user only data',
                'user_stats' => [
                    'profile_completion' => '80%',
                    'rewards_points' => 150
                ]
            ]
        ]);
    }
}