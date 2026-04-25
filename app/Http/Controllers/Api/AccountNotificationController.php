<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountNotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notifications->listForUser($request->user(), (int) $request->query('per_page', 20));

        return response()->json([
            'notifications' => $paginator->getCollection()->map(fn ($notification) => $this->notifications->serializeNotification($notification))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'unread_count' => $this->notifications->unreadCount($request->user()),
            'unreadCount' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 8);
        $notifications = $this->notifications->latestForUser($request->user(), $limit);

        return response()->json([
            'notifications' => $notifications->map(fn ($notification) => $this->notifications->serializeNotification($notification))->values(),
            'unread_count' => $this->notifications->unreadCount($request->user()),
            'unreadCount' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notifications->unreadCount($request->user());

        return response()->json([
            'unread_count' => $count,
            'unreadCount' => $count,
        ]);
    }

    public function markRead(int $notification, Request $request): JsonResponse
    {
        $row = $this->notifications->markRead($request->user(), $notification);

        if (! $row) {
            return response()->json([
                'message' => 'Notification not found.',
            ], 404);
        }

        $count = $this->notifications->unreadCount($request->user());

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $this->notifications->serializeNotification($row),
            'unread_count' => $count,
            'unreadCount' => $count,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $affected = $this->notifications->markAllRead($request->user());

        return response()->json([
            'message' => 'Notifications marked as read.',
            'affected' => $affected,
            'unread_count' => 0,
            'unreadCount' => 0,
        ]);
    }

    public function storeBrowserSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
            'user_agent' => ['nullable', 'string'],
        ]);

        $subscription = $this->notifications->saveBrowserSubscription($request->user(), $data);

        return response()->json([
            'message' => 'Browser subscription saved.',
            'subscription' => [
                'id' => $subscription->id,
                'endpoint' => $subscription->endpoint,
                'last_seen_at' => optional($subscription->last_seen_at)?->toIso8601String(),
                'lastSeenAt' => optional($subscription->last_seen_at)?->toIso8601String(),
            ],
        ]);
    }

    public function deleteBrowserSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $this->notifications->revokeBrowserSubscription($request->user(), $data['endpoint']);

        return response()->json([
            'message' => 'Browser subscription revoked.',
        ]);
    }
}
