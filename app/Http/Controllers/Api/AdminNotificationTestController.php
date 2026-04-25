<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationTestController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function config(): JsonResponse
    {
        return response()->json($this->notifications->notificationsTestConfig());
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'in:discord,telegram,webhook,email'],
            'title' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:4000'],
            'webhook_url' => ['nullable', 'url', 'max:1024'],
            'browser_enabled' => ['nullable', 'boolean'],
            'resend_enabled' => ['nullable', 'boolean'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'discord_webhook' => ['nullable', 'string', 'max:1024'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'resend_api_key' => ['nullable', 'string', 'max:255'],
            'resend_from_email' => ['nullable', 'email', 'max:255'],
            'resend_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $settingsPayload = collect($data)->only([
            'browser_enabled',
            'resend_enabled',
            'sender_name',
            'reply_to',
            'discord_webhook',
            'telegram_bot_token',
            'telegram_chat_id',
            'resend_api_key',
            'resend_from_email',
            'resend_from_name',
        ])->filter(fn ($value) => $value !== null)->all();

        if ($settingsPayload !== []) {
            $this->notifications->saveNotificationSettings($settingsPayload);
        }

        $log = $this->notifications->sendTestChannel($request->user(), $data);

        return response()->json([
            'message' => 'Notification test executed.',
            'log' => $this->notifications->serializeLog($log->load('actor')),
            'settings' => $this->notifications->notificationsTestConfig()['settings'],
        ]);
    }
}
