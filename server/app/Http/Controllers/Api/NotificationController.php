<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(\App\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications with pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $type = $request->get('type'); // Filter by type
        $unreadOnly = $request->get('unread_only', false);

        $query = Notification::where('notifiable_type', User::class)
                            ->where('notifiable_id', $user->id);

        if ($unreadOnly) {
            $query->unread();
        }

        if ($type) {
            $query->ofType($type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
                              ->paginate($perPage, ['*'], 'page', $page);

        // Add computed properties
        $notifications->getCollection()->transform(function ($notification) {
            $notification->icon = $notification->getIcon();
            $notification->link = $notification->getLink();
            $notification->formatted_date = $notification->getFormattedDate();
            $notification->is_read = $notification->isRead();
            return $notification;
        });

        // Get unread count
        $unreadCount = Notification::where('notifiable_type', User::class)
                                   ->where('notifiable_id', $user->id)
                                   ->unread()
                                   ->count();

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        
        $count = Notification::where('notifiable_type', User::class)
                            ->where('notifiable_id', $user->id)
                            ->unread()
                            ->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $count
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        $notification = Notification::where('id', $id)
                                  ->where('notifiable_type', User::class)
                                  ->where('notifiable_id', $user->id)
                                  ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        Notification::where('notifiable_type', User::class)
                   ->where('notifiable_id', $user->id)
                   ->unread()
                   ->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Mark all notifications as unread
     */
    public function markAllAsUnread(Request $request)
    {
        $user = $request->user();
        
        Notification::where('notifiable_type', User::class)
                   ->where('notifiable_id', $user->id)
                   ->update(['read_at' => null]);

        // Get count
        $count = Notification::where('notifiable_type', User::class)
                            ->where('notifiable_id', $user->id)
                            ->unread()
                            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as unread',
            'unread_count' => $count
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $notification = Notification::where('id', $id)
                                  ->where('notifiable_type', User::class)
                                  ->where('notifiable_id', $user->id)
                                  ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Get user's notification preferences
     */
    public function preferencesIndex(Request $request)
    {
        $user = $request->user();
        
        $preferences = NotificationPreference::getDefaultForUser($user->id);
        
        return response()->json([
            'status' => 'success',
            'data' => $preferences
        ]);
    }

    /**
     * Update user's notification preferences
     */
    public function preferencesUpdate(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'memo_approved' => 'sometimes|boolean',
            'memo_rejected' => 'sometimes|boolean',
            'memo_received' => 'sometimes|boolean',
            'memo_acknowledged' => 'sometimes|boolean',
            'calendar_invitation' => 'sometimes|boolean',
            'calendar_updated' => 'sometimes|boolean',
            'profile_updated' => 'sometimes|boolean',
            'calendar_secretary_created' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean'
        ]);

        $preferences = NotificationPreference::where('user_id', $user->id)->first();
        
        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => $user->id,
                ...$validated
            ]);
        } else {
            $preferences->update($validated);
            $preferences->refresh();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification preferences updated successfully',
            'data' => $preferences
        ]);
    }

    /**
     * Check if user should receive email notifications
     */
    public function shouldReceiveEmail(Request $request)
    {
        $user = $request->user();
        
        $preferences = NotificationPreference::where('user_id', $user->id)->first();
        
        if (!$preferences) {
            return response()->json([
                'should_receive' => true,
                'reason' => 'default'
            ]);
        }

        return response()->json([
            'should_receive' => $preferences->wantsEmail(),
            'reason' => $preferences->wantsEmail() ? 'enabled' : 'disabled_by_user'
        ]);
    }
}
