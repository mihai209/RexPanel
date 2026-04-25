<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->notifications->adminNotificationsPayload());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_enabled' => ['nullable', 'boolean'],
            'resend_enabled' => ['nullable', 'boolean'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'resend_api_key' => ['nullable', 'string', 'max:255'],
            'resend_from_email' => ['nullable', 'email', 'max:255'],
            'resend_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'message' => 'Notification delivery settings updated.',
            'settings' => $this->notifications->saveNotificationSettings($data),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_mode' => ['required', 'string', 'in:single,selected,all'],
            'user_id' => ['nullable', 'integer'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:8000'],
            'severity' => ['nullable', 'string', 'in:info,success,warning,danger'],
            'category' => ['nullable', 'string', 'max:40'],
            'link_url' => ['nullable', 'string', 'max:512'],
            'send_browser' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $result = $this->notifications->createAdminNotifications($request->user(), $data);

        return response()->json([
            'message' => 'Notifications created.',
            'recipients_count' => $result['recipients']->count(),
            'recipientsCount' => $result['recipients']->count(),
            'notifications' => $result['notifications']->map(fn ($notification) => $this->notifications->serializeNotification($notification, true))->values(),
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        return response()->json($this->notifications->logsPayload($request->all()));
    }

    public function retry(int $log, Request $request): JsonResponse
    {
        $row = \App\Models\NotificationDeliveryLog::query()->findOrFail($log);
        $retried = $this->notifications->retryLog($row, $request->user());

        return response()->json([
            'message' => 'Notification delivery retried.',
            'log' => $this->notifications->serializeLog($retried->load('actor')),
        ]);
    }

    public function retryLastFailed(Request $request): JsonResponse
    {
        $retried = $this->notifications->retryLastFailed($request->user());

        if (! $retried) {
            return response()->json([
                'message' => 'No failed delivery log was found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Last failed delivery retried.',
            'log' => $this->notifications->serializeLog($retried->load('actor')),
        ]);
    }
}
