<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // The Frontend calls this to see the list in the "Bell" icon
    public function index(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        return response()->json([
            'unread_count' => $user->unreadNotifications->count(),
            // latest() ensures the newest alerts are at the top
            'notifications' => $user->notifications()->latest()->get() 
        ]);
    }

    // The Frontend calls this when a user clicks a notification to clear the red dot
    public function markAsRead(Request $request, $userId, $notificationId)
    {
        $user = User::findOrFail($userId);
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }
}