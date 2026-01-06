<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display all notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform notifications for frontend
        $notifications->getCollection()->transform(function ($notification) {
            $data = $notification->data;
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'subject' => $data['message'] ?? 'Notification',
                'link' => $data['link'] ?? null,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
            ];
        });

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Get unread notifications count (API endpoint).
     */
    public function unreadCount()
    {
        $user = Auth::user();
        $count = $user->unreadNotifications->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Get notifications (API endpoint for real-time sync).
     */
    public function getNotifications(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 20);
        
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        // Transform notifications for frontend
        $transformed = $notifications->map(function ($notification) {
            $data = $notification->data;
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'subject' => $data['message'] ?? 'Notification',
                'link' => $data['link'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'notifications' => $transformed,
            'unread_count' => $user->unreadNotifications->count(),
        ]);
    }
}




