<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        $unread = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'items' => $items,
                'unread_count' => $unread,
            ],
        ]);
    }

    public function markRead(Request $request, UserNotification $user_notification): JsonResponse
    {
        if ($user_notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $user_notification->read_at) {
            $user_notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
