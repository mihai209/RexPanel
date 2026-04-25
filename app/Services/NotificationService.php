<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserBrowserSubscription;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NotificationService
{
    private const DELIVERY_KEY = 'notificationDeliveryConfig';
    private const RESEND_KEY = 'resendConfig';

    public function __construct(private UiWebsocketRedisService $uiWebsocket)
    {
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $safePerPage = max(1, min(100, $perPage));

        return UserNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($safePerPage);
    }

    public function latestForUser(User $user, int $limit = 8): Collection
    {
        $safeLimit = max(1, min(50, $limit));

        return UserNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($safeLimit)
            ->get();
    }

    public function markRead(User $user, int $notificationId): ?UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->find($notificationId);

        if (! $notification) {
            return null;
        }

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        $this->pushReadEvents($user, $notification);

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        $affected = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $this->pushUserEvent($user->id, [
            'type' => 'notification:read',
            'notificationId' => null,
        ]);
        $this->pushUnreadCount($user);

        return $affected;
    }

    public function saveBrowserSubscription(User $user, array $payload): UserBrowserSubscription
    {
        $subscription = UserBrowserSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => trim((string) ($payload['endpoint'] ?? '')),
            ],
            [
                'keys' => $payload['keys'] ?? null,
                'user_agent' => $payload['user_agent'] ?? request()?->userAgent(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return $subscription->fresh();
    }

    public function revokeBrowserSubscription(User $user, string $endpoint): void
    {
        UserBrowserSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->update([
                'revoked_at' => now(),
            ]);
    }

    public function createAdminNotifications(User $actor, array $payload): array
    {
        $recipients = $this->resolveRecipients($payload);

        if ($recipients->isEmpty()) {
            throw new \InvalidArgumentException('No valid recipients were selected.');
        }

        $title = $this->sanitizeText($payload['title'] ?? null, 160);
        $message = $this->sanitizeText($payload['message'] ?? null, 8000);

        if ($title === '' || $message === '') {
            throw new \InvalidArgumentException('Title and message are required.');
        }

        $browserEligible = (bool) ($payload['send_browser'] ?? false);
        $emailEligible = (bool) ($payload['send_email'] ?? false);
        $severity = $this->sanitizeSeverity($payload['severity'] ?? 'info');
        $category = $this->sanitizeCategory($payload['category'] ?? 'general');
        $linkUrl = $this->sanitizeLinkUrl($payload['link_url'] ?? null);

        $notifications = new Collection();

        foreach ($recipients as $recipient) {
            $notification = UserNotification::query()->create([
                'user_id' => $recipient->id,
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'category' => $category,
                'link_url' => $linkUrl,
                'source_type' => 'admin_manual',
                'created_by_user_id' => $actor->id,
                'browser_eligible' => $browserEligible,
                'email_eligible' => $emailEligible,
            ]);

            $this->createLog([
                'channel' => 'panel',
                'status' => 'sent',
                'target' => 'user:' . $recipient->id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => [
                    'notification_id' => $notification->id,
                    'target_mode' => $payload['target_mode'] ?? 'selected',
                ],
                'metadata' => [
                    'source_type' => 'admin_manual',
                ],
            ]);

            $this->pushNewNotification($recipient, $notification->fresh());

            if ($browserEligible) {
                $this->deliverBrowserChannel($notification, $actor);
            }

            if ($emailEligible) {
                $this->deliverEmailChannel($notification, $actor);
            }

            $notifications->push($notification->fresh(['user']));
        }

        return [
            'recipients' => $recipients,
            'notifications' => $notifications,
        ];
    }

    public function adminNotificationsPayload(): array
    {
        $recentNotifications = UserNotification::query()
            ->with('user:id,username,email')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (UserNotification $notification) => $this->serializeNotification($notification, true))
            ->values()
            ->all();

        return [
            'users' => User::query()
                ->select(['id', 'username', 'email', 'is_suspended'])
                ->orderBy('username')
                ->limit(300)
                ->get(),
            'recent_notifications' => $recentNotifications,
            'recent_logs' => $this->logsQuery()->latest()->limit(20)->get()->map(fn (NotificationDeliveryLog $log) => $this->serializeLog($log))->values(),
            'logs_payload' => $this->logsPayload(),
            'settings' => $this->settingsPayload(false),
            'test_center' => $this->notificationsTestConfig(),
        ];
    }

    public function logsPayload(array $filters = []): array
    {
        $query = $this->logsQuery();

        if (($filters['channel'] ?? 'all') !== 'all') {
            $query->where('channel', $filters['channel']);
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $logs = $query->latest()->paginate($perPage);

        return [
            'logs' => $logs->through(fn (NotificationDeliveryLog $log) => $this->serializeLog($log)),
            'last_failed_log' => optional(
                NotificationDeliveryLog::query()->where('status', 'failed')->latest()->first(),
                fn (NotificationDeliveryLog $log) => $this->serializeLog($log)
            ),
        ];
    }

    public function notificationsTestConfig(): array
    {
        return [
            'settings' => $this->settingsPayload(false),
            'masked_targets' => [
                'discord' => $this->maskTarget('discord', $this->settingsPayload(true)['delivery']['discordWebhook'] ?? null),
                'telegram' => $this->maskTarget('telegram', $this->settingsPayload(true)['delivery']['telegramChatId'] ?? null),
                'resend' => $this->maskTarget('resend', $this->settingsPayload(true)['resend']['fromEmail'] ?? null),
            ],
            'last_failed_log' => optional(
                NotificationDeliveryLog::query()->where('status', 'failed')->latest()->first(),
                fn (NotificationDeliveryLog $log) => $this->serializeLog($log)
            ),
        ];
    }

    public function saveNotificationSettings(array $payload): array
    {
        $current = $this->settingsPayload(true);

        $delivery = [
            'browserEnabled' => (bool) ($payload['browser_enabled'] ?? $current['delivery']['browserEnabled']),
            'resendEnabled' => (bool) ($payload['resend_enabled'] ?? $current['delivery']['resendEnabled']),
            'senderName' => $this->sanitizeText($payload['sender_name'] ?? $current['delivery']['senderName'], 120),
            'replyTo' => $this->sanitizeText($payload['reply_to'] ?? $current['delivery']['replyTo'], 255),
            'discordWebhook' => $this->sanitizeText($payload['discord_webhook'] ?? $current['delivery']['discordWebhook'], 1024),
            'telegramBotToken' => $this->sanitizeText($payload['telegram_bot_token'] ?? $current['delivery']['telegramBotToken'], 255),
            'telegramChatId' => $this->sanitizeText($payload['telegram_chat_id'] ?? $current['delivery']['telegramChatId'], 255),
        ];

        $resend = [
            'apiKey' => $this->sanitizeText($payload['resend_api_key'] ?? $current['resend']['apiKey'], 255),
            'fromEmail' => $this->sanitizeText($payload['resend_from_email'] ?? $current['resend']['fromEmail'], 255),
            'fromName' => $this->sanitizeText($payload['resend_from_name'] ?? $current['resend']['fromName'], 120),
        ];

        SystemSetting::query()->updateOrCreate(['key' => self::DELIVERY_KEY], ['value' => json_encode($delivery, JSON_UNESCAPED_SLASHES)]);
        SystemSetting::query()->updateOrCreate(['key' => self::RESEND_KEY], ['value' => json_encode($resend, JSON_UNESCAPED_SLASHES)]);

        return $this->settingsPayload(false);
    }

    public function sendTestChannel(User $actor, array $payload): NotificationDeliveryLog
    {
        $channel = strtolower(trim((string) ($payload['channel'] ?? '')));
        $title = $this->sanitizeText($payload['title'] ?? 'RA-panel test notification', 160, 'RA-panel test notification');
        $message = $this->sanitizeText($payload['message'] ?? 'This is a manual notification probe from the admin test center.', 4000, 'This is a manual notification probe from the admin test center.');
        $requestPayload = [
            'channel' => $channel,
            'title' => $title,
            'message' => $message,
            'webhook_url' => $payload['webhook_url'] ?? null,
        ];

        return match ($channel) {
            'discord' => $this->sendDiscordTest($actor, $requestPayload, null),
            'telegram' => $this->sendTelegramTest($actor, $requestPayload, null),
            'webhook' => $this->sendWebhookTest($actor, $requestPayload, null),
            'email' => $this->createEmailPreviewLog($actor, $requestPayload, null),
            default => throw new \InvalidArgumentException('Unsupported notification test channel.'),
        };
    }

    public function retryLog(NotificationDeliveryLog $log, User $actor): NotificationDeliveryLog
    {
        $request = $log->request_payload ?? [];
        $channel = strtolower(trim((string) ($log->channel ?? '')));

        return match ($channel) {
            'discord' => $this->sendDiscordTest($actor, $request, $log),
            'telegram' => $this->sendTelegramTest($actor, $request, $log),
            'webhook' => $this->sendWebhookTest($actor, $request, $log),
            'email' => $this->createEmailPreviewLog($actor, $request, $log),
            'browser' => $this->retryBrowserLog($actor, $log),
            default => throw new \InvalidArgumentException('This log cannot be retried.'),
        };
    }

    public function retryLastFailed(User $actor): ?NotificationDeliveryLog
    {
        $log = NotificationDeliveryLog::query()->where('status', 'failed')->latest()->first();

        return $log ? $this->retryLog($log, $actor) : null;
    }

    public function serializeNotification(UserNotification $notification, bool $includeUser = false): array
    {
        $payload = [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'severity' => $notification->severity,
            'category' => $notification->category,
            'link_url' => $notification->link_url,
            'linkUrl' => $notification->link_url,
            'source_type' => $notification->source_type,
            'sourceType' => $notification->source_type,
            'is_read' => (bool) $notification->is_read,
            'isRead' => (bool) $notification->is_read,
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'readAt' => optional($notification->read_at)?->toIso8601String(),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'createdAt' => optional($notification->created_at)?->toIso8601String(),
            'browser_eligible' => (bool) $notification->browser_eligible,
            'browserEligible' => (bool) $notification->browser_eligible,
            'email_eligible' => (bool) $notification->email_eligible,
            'emailEligible' => (bool) $notification->email_eligible,
        ];

        if ($includeUser) {
            $payload['user'] = $notification->user ? [
                'id' => $notification->user->id,
                'username' => $notification->user->username,
                'email' => $notification->user->email,
            ] : null;
        }

        return $payload;
    }

    public function serializeLog(NotificationDeliveryLog $log): array
    {
        return [
            'id' => $log->id,
            'channel' => $log->channel,
            'status' => $log->status,
            'target' => $this->maskTarget($log->channel, $log->target),
            'raw_target' => $log->target,
            'template_key' => $log->template_key,
            'event_key' => $log->event_key,
            'request_payload' => $log->request_payload,
            'response_payload' => $log->response_payload,
            'error_text' => $log->error_text,
            'metadata' => $log->metadata,
            'created_at' => optional($log->created_at)?->toIso8601String(),
            'createdAt' => optional($log->created_at)?->toIso8601String(),
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'username' => $log->actor->username,
                'email' => $log->actor->email,
            ] : null,
            'retried_from_id' => $log->retried_from_id,
            'retriedFromId' => $log->retried_from_id,
        ];
    }

    private function resolveRecipients(array $payload): Collection
    {
        $targetMode = strtolower(trim((string) ($payload['target_mode'] ?? 'selected')));

        return match ($targetMode) {
            'all' => User::query()->orderBy('username')->get(),
            'single' => User::query()->whereKey((int) ($payload['user_id'] ?? 0))->get(),
            default => User::query()
                ->whereIn('id', collect($payload['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values())
                ->orderBy('username')
                ->get(),
        };
    }

    private function logsQuery()
    {
        return NotificationDeliveryLog::query()->with('actor:id,username,email');
    }

    private function settingsPayload(bool $includeSecrets): array
    {
        $delivery = $this->decodeSetting(self::DELIVERY_KEY);
        $resend = $this->decodeSetting(self::RESEND_KEY);

        $payload = [
            'delivery' => [
                'browserEnabled' => (bool) ($delivery['browserEnabled'] ?? true),
                'resendEnabled' => (bool) ($delivery['resendEnabled'] ?? false),
                'senderName' => (string) ($delivery['senderName'] ?? ''),
                'replyTo' => (string) ($delivery['replyTo'] ?? ''),
                'discordWebhook' => $includeSecrets ? (string) ($delivery['discordWebhook'] ?? '') : $this->maskTarget('discord', $delivery['discordWebhook'] ?? null),
                'telegramBotToken' => $includeSecrets ? (string) ($delivery['telegramBotToken'] ?? '') : $this->maskTarget('telegram_token', $delivery['telegramBotToken'] ?? null),
                'telegramChatId' => $includeSecrets ? (string) ($delivery['telegramChatId'] ?? '') : $this->maskTarget('telegram', $delivery['telegramChatId'] ?? null),
            ],
            'resend' => [
                'apiKey' => $includeSecrets ? (string) ($resend['apiKey'] ?? '') : $this->maskTarget('api_key', $resend['apiKey'] ?? null),
                'fromEmail' => (string) ($resend['fromEmail'] ?? ''),
                'fromName' => (string) ($resend['fromName'] ?? ''),
            ],
        ];

        $payload['channels'] = [
            'discordConfigured' => filled($delivery['discordWebhook'] ?? null),
            'telegramConfigured' => filled($delivery['telegramBotToken'] ?? null) && filled($delivery['telegramChatId'] ?? null),
            'resendConfigured' => filled($resend['apiKey'] ?? null) && filled($resend['fromEmail'] ?? null),
        ];

        return $payload;
    }

    private function decodeSetting(string $key): array
    {
        $row = SystemSetting::query()->find($key);
        if (! $row || blank($row->value)) {
            return [];
        }

        $decoded = json_decode((string) $row->value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function deliverBrowserChannel(UserNotification $notification, User $actor): NotificationDeliveryLog
    {
        $settings = $this->settingsPayload(true);
        $subscriptionCount = UserBrowserSubscription::query()
            ->where('user_id', $notification->user_id)
            ->whereNull('revoked_at')
            ->count();
        $socketCount = $this->activeSocketCount($notification->user_id);

        if (! $settings['delivery']['browserEnabled']) {
            return $this->createLog([
                'channel' => 'browser',
                'status' => 'skipped',
                'target' => 'user:' . $notification->user_id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'error_text' => 'Browser delivery is disabled in settings.',
            ]);
        }

        if ($subscriptionCount < 1) {
            return $this->createLog([
                'channel' => 'browser',
                'status' => 'skipped',
                'target' => 'user:' . $notification->user_id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'error_text' => 'User has no active browser subscription.',
            ]);
        }

        $this->pushUserEvent($notification->user_id, [
            'type' => 'notification:new',
            'notification' => $this->serializeNotification($notification),
            'browserDelivery' => true,
        ]);

        return $this->createLog([
            'channel' => 'browser',
            'status' => $socketCount > 0 ? 'sent' : 'failed',
            'target' => 'user:' . $notification->user_id,
            'template_key' => 'admin_manual',
            'event_key' => 'admin_user_notification',
            'attempted_by_user_id' => $actor->id,
            'request_payload' => ['notification_id' => $notification->id],
            'response_payload' => ['sockets' => $socketCount],
            'error_text' => $socketCount > 0 ? null : 'No active UI websocket connection available.',
        ]);
    }

    private function deliverEmailChannel(UserNotification $notification, User $actor): NotificationDeliveryLog
    {
        $settings = $this->settingsPayload(true);
        $notification->loadMissing('user');

        if (! $settings['delivery']['resendEnabled']) {
            return $this->createLog([
                'channel' => 'email',
                'status' => 'skipped',
                'target' => $notification->user?->email ?: 'user:' . $notification->user_id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'error_text' => 'Resend delivery is disabled in settings.',
            ]);
        }

        $apiKey = $settings['resend']['apiKey'] ?? '';
        $fromEmail = $settings['resend']['fromEmail'] ?? '';

        if (blank($apiKey) || blank($fromEmail) || blank($notification->user?->email)) {
            return $this->createLog([
                'channel' => 'email',
                'status' => 'failed',
                'target' => $notification->user?->email ?: 'user:' . $notification->user_id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'error_text' => 'Resend is not fully configured or the user has no email address.',
            ]);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->post('https://api.resend.com/emails', [
                    'from' => $this->buildFromAddress($settings),
                    'to' => [$notification->user->email],
                    'reply_to' => $settings['delivery']['replyTo'] ?: null,
                    'subject' => $notification->title,
                    'html' => $this->buildEmailHtml($notification),
                ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->json('message') ?: $response->body());
            }

            return $this->createLog([
                'channel' => 'email',
                'status' => 'sent',
                'target' => $notification->user->email,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'response_payload' => $response->json(),
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'email',
                'status' => 'failed',
                'target' => $notification->user?->email ?: 'user:' . $notification->user_id,
                'template_key' => 'admin_manual',
                'event_key' => 'admin_user_notification',
                'attempted_by_user_id' => $actor->id,
                'request_payload' => ['notification_id' => $notification->id],
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
            ]);
        }
    }

    private function sendDiscordTest(User $actor, array $requestPayload, ?NotificationDeliveryLog $retriedFrom): NotificationDeliveryLog
    {
        $settings = $this->settingsPayload(true);
        $url = (string) ($settings['delivery']['discordWebhook'] ?? '');

        if (blank($url)) {
            return $this->createLog([
                'channel' => 'discord',
                'status' => 'failed',
                'target' => null,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => 'Discord webhook is not configured.',
            ]);
        }

        try {
            $response = Http::acceptJson()->post($url, [
                'content' => "**{$requestPayload['title']}**\n{$requestPayload['message']}",
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            return $this->createLog([
                'channel' => 'discord',
                'status' => 'sent',
                'target' => $url,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'response_payload' => ['status' => $response->status()],
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'discord',
                'status' => 'failed',
                'target' => $url,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
            ]);
        }
    }

    private function sendTelegramTest(User $actor, array $requestPayload, ?NotificationDeliveryLog $retriedFrom): NotificationDeliveryLog
    {
        $settings = $this->settingsPayload(true);
        $token = (string) ($settings['delivery']['telegramBotToken'] ?? '');
        $chatId = (string) ($settings['delivery']['telegramChatId'] ?? '');

        if (blank($token) || blank($chatId)) {
            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'failed',
                'target' => $chatId,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => 'Telegram bot token or chat ID is missing.',
            ]);
        }

        try {
            $response = Http::acceptJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $requestPayload['title'] . "\n\n" . $requestPayload['message'],
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'sent',
                'target' => $chatId,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'response_payload' => $response->json(),
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'failed',
                'target' => $chatId,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
            ]);
        }
    }

    private function sendWebhookTest(User $actor, array $requestPayload, ?NotificationDeliveryLog $retriedFrom): NotificationDeliveryLog
    {
        $url = (string) ($requestPayload['webhook_url'] ?? '');

        if (blank($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->createLog([
                'channel' => 'webhook',
                'status' => 'failed',
                'target' => $url,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => 'A valid webhook URL is required.',
            ]);
        }

        try {
            $response = Http::acceptJson()->post($url, [
                'event' => 'ra_panel.notification_test',
                'title' => $requestPayload['title'],
                'message' => $requestPayload['message'],
                'sent_at' => now()->toIso8601String(),
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            return $this->createLog([
                'channel' => 'webhook',
                'status' => 'sent',
                'target' => $url,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'response_payload' => [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'webhook',
                'status' => 'failed',
                'target' => $url,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $retriedFrom?->id,
                'request_payload' => $requestPayload,
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
            ]);
        }
    }

    private function createEmailPreviewLog(User $actor, array $requestPayload, ?NotificationDeliveryLog $retriedFrom): NotificationDeliveryLog
    {
        return $this->createLog([
            'channel' => 'email',
            'status' => 'sent',
            'target' => $this->settingsPayload(true)['resend']['fromEmail'] ?? null,
            'attempted_by_user_id' => $actor->id,
            'retried_from_id' => $retriedFrom?->id,
            'request_payload' => $requestPayload,
            'response_payload' => [
                'preview' => [
                    'subject' => $requestPayload['title'],
                    'html' => nl2br(e($requestPayload['message'])),
                ],
            ],
            'metadata' => [
                'preview_only' => true,
            ],
        ]);
    }

    private function retryBrowserLog(User $actor, NotificationDeliveryLog $log): NotificationDeliveryLog
    {
        $notificationId = (int) ($log->request_payload['notification_id'] ?? 0);
        $notification = UserNotification::query()->find($notificationId);

        if (! $notification) {
            return $this->createLog([
                'channel' => 'browser',
                'status' => 'failed',
                'target' => $log->target,
                'attempted_by_user_id' => $actor->id,
                'retried_from_id' => $log->id,
                'request_payload' => $log->request_payload,
                'error_text' => 'The notification record no longer exists.',
            ]);
        }

        return $this->deliverBrowserChannel($notification, $actor);
    }

    private function createLog(array $payload): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::query()->create([
            'channel' => $this->sanitizeText($payload['channel'] ?? null, 32, 'unknown'),
            'status' => $this->sanitizeText($payload['status'] ?? null, 24, 'failed'),
            'target' => $payload['target'] ?? null,
            'template_key' => $payload['template_key'] ?? null,
            'event_key' => $payload['event_key'] ?? null,
            'request_payload' => $payload['request_payload'] ?? null,
            'response_payload' => $payload['response_payload'] ?? null,
            'error_text' => $payload['error_text'] ?? null,
            'attempted_by_user_id' => $payload['attempted_by_user_id'] ?? null,
            'retried_from_id' => $payload['retried_from_id'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    private function pushNewNotification(User $user, UserNotification $notification): void
    {
        $this->pushUserEvent($user->id, [
            'type' => 'notification:new',
            'notification' => $this->serializeNotification($notification),
        ]);
        $this->pushUnreadCount($user);
    }

    private function pushReadEvents(User $user, UserNotification $notification): void
    {
        $this->pushUserEvent($user->id, [
            'type' => 'notification:read',
            'notificationId' => $notification->id,
        ]);
        $this->pushUnreadCount($user);
    }

    private function pushUnreadCount(User $user): void
    {
        $this->pushUserEvent($user->id, [
            'type' => 'notification:unread_count',
            'unreadCount' => $this->unreadCount($user),
        ]);
    }

    private function pushUserEvent(int $userId, array $payload): void
    {
        $this->uiWebsocket->publishUserEvent($userId, $payload);
    }

    private function activeSocketCount(int $userId): int
    {
        return $this->uiWebsocket->activeSocketCount($userId);
    }

    private function sanitizeSeverity(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['info', 'success', 'warning', 'danger'], true) ? $normalized : 'info';
    }

    private function sanitizeCategory(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? Str::limit($normalized, 40, '') : 'general';
    }

    private function sanitizeText(?string $value, int $maxLength, string $fallback = ''): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : Str::limit($text, $maxLength, '');
    }

    private function sanitizeLinkUrl(?string $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return Str::limit($url, 512, '');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? Str::limit($url, 512, '') : null;
    }

    private function buildFromAddress(array $settings): string
    {
        $fromEmail = $settings['resend']['fromEmail'] ?: 'notifications@example.invalid';
        $fromName = $settings['resend']['fromName'] ?: ($settings['delivery']['senderName'] ?: 'RA-panel');

        return sprintf('%s <%s>', $fromName, $fromEmail);
    }

    private function buildEmailHtml(UserNotification $notification): string
    {
        $title = e($notification->title);
        $message = nl2br(e($notification->message));
        $link = $notification->link_url
            ? '<p style="margin-top:24px;"><a href="' . e($notification->link_url) . '" style="display:inline-block;padding:12px 16px;border-radius:12px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;">Open notification</a></p>'
            : '';

        return <<<HTML
<div style="font-family:Inter,Arial,sans-serif;background:#0f172a;padding:32px;color:#e2e8f0;">
  <div style="max-width:640px;margin:0 auto;background:#111827;border:1px solid rgba(148,163,184,0.18);border-radius:20px;padding:28px;">
    <div style="font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#93c5fd;margin-bottom:12px;">RA-panel Notification</div>
    <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#f8fafc;">{$title}</h1>
    <p style="margin:0;font-size:15px;line-height:1.7;color:#cbd5e1;">{$message}</p>
    {$link}
  </div>
</div>
HTML;
    }

    public function maskTarget(string $channel, mixed $target): ?string
    {
        $value = trim((string) $target);

        if ($value === '') {
            return null;
        }

        return match (strtolower($channel)) {
            'discord' => preg_replace('#https://discord\.com/api/webhooks/(\d+)/.+$#', 'discord:$1:***', $value) ?: 'discord:***',
            'telegram', 'telegram_token' => strlen($value) <= 4 ? str_repeat('*', strlen($value)) : substr($value, 0, 2) . str_repeat('*', max(strlen($value) - 4, 2)) . substr($value, -2),
            'email', 'resend' => preg_replace('/(^.).*(@.*$)/', '$1***$2', $value) ?: '***',
            'api_key' => strlen($value) <= 8 ? str_repeat('*', strlen($value)) : substr($value, 0, 4) . str_repeat('*', max(strlen($value) - 8, 4)) . substr($value, -4),
            default => strlen($value) <= 8 ? str_repeat('*', strlen($value)) : substr($value, 0, 4) . '***' . substr($value, -4),
        };
    }
}
