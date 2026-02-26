<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * NotificationController
 *
 * Handles notification CRUD for authenticated users (parents, teachers).
 * The mobile app polls these endpoints to display notifications.
 */
class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     * GET /api/notifications
     *
     * Returns paginated list + unread_count.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = Notification::where('user_id', $userId);

        // Optional: filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Optional: filter by read status
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        // Count unread notifications
        $unreadCount = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read.
     * PATCH /api/notifications/read-all
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
