<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\PanelIncident;
use App\Models\PanelMaintenanceWindow;
use App\Models\PanelSecurityAlert;
use App\Models\SystemSetting;
use App\Models\BillingEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExtensionService
{
    private const ANNOUNCER_ENABLED_KEY = 'extensionAnnouncerEnabled';
    private const ANNOUNCER_SEVERITY_KEY = 'extensionAnnouncerSeverity';
    private const ANNOUNCER_MESSAGE_KEY = 'extensionAnnouncerMessage';
    private const WEBHOOKS_FEATURE_KEY = 'featureExtensionWebhooksEnabled';
    private const INCIDENTS_FEATURE_KEY = 'featureExtensionIncidentsEnabled';
    private const MAINTENANCE_FEATURE_KEY = 'featureExtensionMaintenanceEnabled';
    private const SECURITY_FEATURE_KEY = 'featureExtensionSecurityCenterEnabled';
    private const WEBHOOKS_CONFIG_KEY = 'extensionWebhooksConfig';

    private const DEFAULT_EVENTS = [
        'incidentCreated' => true,
        'incidentResolved' => true,
        'maintenanceScheduled' => true,
        'maintenanceCompleted' => true,
        'securityAlertCreated' => true,
        'securityAlertResolved' => true,
        'commerceInvoicePreview' => false,
    ];

    public function dispatchCommerceInvoiceEvent(User $actor, BillingEvent $event): void
    {
        $this->dispatchConfiguredEvent(
            'commerceInvoicePreview',
            'Commerce invoice preview',
            sprintf('Billing event %s changed wallet by %d coins.', $event->event_type, (int) $event->coins_delta),
            [
                'billingEventId' => $event->id,
                'eventType' => $event->event_type,
                'coinsDelta' => (int) $event->coins_delta,
                'sourceType' => $event->source_type,
                'sourceId' => $event->source_id,
                'details' => $event->details ?? [],
            ],
            $actor,
        );
    }

    public function adminPayload(): array
    {
        return [
            'settings' => $this->settingsPayload(),
            'incidents' => PanelIncident::query()->latest()->limit(100)->get()->map(fn (PanelIncident $incident) => $this->serializeIncident($incident))->values(),
            'maintenance' => PanelMaintenanceWindow::query()->orderByDesc('starts_at')->limit(100)->get()->map(fn (PanelMaintenanceWindow $item) => $this->serializeMaintenance($item))->values(),
            'security' => PanelSecurityAlert::query()->latest()->limit(100)->get()->map(fn (PanelSecurityAlert $alert) => $this->serializeSecurityAlert($alert))->values(),
            'webhook_logs' => NotificationDeliveryLog::query()
                ->with('actor:id,username,email')
                ->whereIn('template_key', ['extension_webhook', 'extension_webhook_test'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (NotificationDeliveryLog $log) => $this->serializeLog($log))
                ->values(),
        ];
    }

    public function runtimePayload(): array
    {
        $settings = $this->settingsPayload();
        $now = CarbonImmutable::now();

        return [
            'announcer' => $settings['announcer']['enabled'] && filled($settings['announcer']['message'])
                ? $settings['announcer']
                : null,
            'features' => $settings['features'],
            'incidents' => $settings['features']['incidentsEnabled']
                ? PanelIncident::query()
                    ->where('status', 'open')
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (PanelIncident $incident) => $this->serializeIncident($incident))
                    ->values()
                : [],
            'maintenance' => $settings['features']['maintenanceEnabled']
                ? PanelMaintenanceWindow::query()
                    ->where('is_completed', false)
                    ->where('ends_at', '>=', $now->subDay())
                    ->orderBy('starts_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (PanelMaintenanceWindow $item) => $this->serializeMaintenance($item))
                    ->values()
                : [],
            'security' => $settings['features']['securityEnabled']
                ? PanelSecurityAlert::query()
                    ->where('status', 'open')
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (PanelSecurityAlert $alert) => $this->serializeSecurityAlert($alert))
                    ->values()
                : [],
            'generatedAt' => $now->toIso8601String(),
        ];
    }

    public function settingsPayload(): array
    {
        $webhooksConfig = $this->webhooksConfig(true);

        return [
            'announcer' => [
                'enabled' => $this->getBooleanSetting(self::ANNOUNCER_ENABLED_KEY),
                'severity' => $this->normalizeAnnouncerSeverity($this->getSetting(self::ANNOUNCER_SEVERITY_KEY, 'normal')),
                'message' => $this->sanitizeText($this->getSetting(self::ANNOUNCER_MESSAGE_KEY, ''), 500),
            ],
            'features' => [
                'webhooksEnabled' => $this->getBooleanSetting(self::WEBHOOKS_FEATURE_KEY),
                'incidentsEnabled' => $this->getBooleanSetting(self::INCIDENTS_FEATURE_KEY),
                'maintenanceEnabled' => $this->getBooleanSetting(self::MAINTENANCE_FEATURE_KEY),
                'securityEnabled' => $this->getBooleanSetting(self::SECURITY_FEATURE_KEY),
            ],
            'webhooks' => [
                'enabled' => (bool) ($webhooksConfig['enabled'] ?? false),
                'discordWebhook' => (string) ($webhooksConfig['discordWebhook'] ?? ''),
                'telegramBotToken' => (string) ($webhooksConfig['telegramBotToken'] ?? ''),
                'telegramChatId' => (string) ($webhooksConfig['telegramChatId'] ?? ''),
                'events' => $this->normalizedEvents($webhooksConfig['events'] ?? []),
            ],
            'channels' => [
                'discordConfigured' => filled($webhooksConfig['discordWebhook'] ?? null),
                'telegramConfigured' => filled($webhooksConfig['telegramBotToken'] ?? null) && filled($webhooksConfig['telegramChatId'] ?? null),
            ],
        ];
    }

    public function updateAnnouncer(array $payload): array
    {
        $this->putSetting(self::ANNOUNCER_ENABLED_KEY, $this->boolString((bool) ($payload['enabled'] ?? false)));
        $this->putSetting(self::ANNOUNCER_SEVERITY_KEY, $this->normalizeAnnouncerSeverity($payload['severity'] ?? 'normal'));
        $this->putSetting(self::ANNOUNCER_MESSAGE_KEY, $this->sanitizeText($payload['message'] ?? '', 500));

        return $this->settingsPayload();
    }

    public function updateWebhookSettings(array $payload): array
    {
        $current = $this->webhooksConfig(true);
        $config = [
            'enabled' => (bool) ($payload['enabled'] ?? ($current['enabled'] ?? false)),
            'discordWebhook' => $this->sanitizeText($payload['discordWebhook'] ?? ($current['discordWebhook'] ?? ''), 1024),
            'telegramBotToken' => $this->sanitizeText($payload['telegramBotToken'] ?? ($current['telegramBotToken'] ?? ''), 255),
            'telegramChatId' => $this->sanitizeText($payload['telegramChatId'] ?? ($current['telegramChatId'] ?? ''), 255),
            'events' => $this->normalizedEvents($payload['events'] ?? ($current['events'] ?? [])),
        ];

        $this->putSetting(self::WEBHOOKS_FEATURE_KEY, $this->boolString((bool) ($payload['moduleEnabled'] ?? false)));
        $this->putSetting(self::WEBHOOKS_CONFIG_KEY, json_encode($config, JSON_UNESCAPED_SLASHES));

        return $this->settingsPayload();
    }

    public function testWebhooks(User $actor, array $payload = []): array
    {
        $settings = $this->settingsPayload();
        $title = $this->sanitizeText($payload['title'] ?? 'RA-panel extension test', 160, 'RA-panel extension test');
        $message = $this->sanitizeText($payload['message'] ?? 'This is an extension webhook test event from the admin center.', 2000, 'This is an extension webhook test event from the admin center.');
        $eventKey = 'extension.test';

        if (! $settings['features']['webhooksEnabled']) {
            return [
                $this->createLog([
                    'channel' => 'webhook',
                    'status' => 'skipped',
                    'template_key' => 'extension_webhook_test',
                    'event_key' => $eventKey,
                    'attempted_by_user_id' => $actor->id,
                    'error_text' => 'The Extensions webhooks module is disabled.',
                    'request_payload' => ['title' => $title, 'message' => $message],
                    'metadata' => ['source' => 'extensions'],
                ]),
            ];
        }

        if (! $settings['webhooks']['enabled']) {
            return [
                $this->createLog([
                    'channel' => 'webhook',
                    'status' => 'skipped',
                    'template_key' => 'extension_webhook_test',
                    'event_key' => $eventKey,
                    'attempted_by_user_id' => $actor->id,
                    'error_text' => 'Webhook dispatch is disabled in the saved Extensions config.',
                    'request_payload' => ['title' => $title, 'message' => $message],
                    'metadata' => ['source' => 'extensions'],
                ]),
            ];
        }

        $logs = [];

        $logs[] = $this->sendDiscordWebhook(
            $actor,
            $settings['webhooks']['discordWebhook'],
            $title,
            $message,
            $eventKey,
            'extension_webhook_test'
        );

        $logs[] = $this->sendTelegramWebhook(
            $actor,
            $settings['webhooks']['telegramBotToken'],
            $settings['webhooks']['telegramChatId'],
            $title,
            $message,
            $eventKey,
            'extension_webhook_test'
        );

        return $logs;
    }

    public function updateIncidentSettings(array $payload): array
    {
        $this->putSetting(self::INCIDENTS_FEATURE_KEY, $this->boolString((bool) ($payload['enabled'] ?? false)));

        return $this->settingsPayload();
    }

    public function createIncident(User $actor, array $payload): PanelIncident
    {
        $incident = PanelIncident::query()->create([
            'title' => $this->sanitizeText($payload['title'] ?? '', 160),
            'message' => $this->sanitizeText($payload['message'] ?? '', 4000),
            'severity' => $this->normalizeExtensionSeverity($payload['severity'] ?? 'warning'),
            'status' => 'open',
            'created_by_user_id' => $actor->id,
        ]);

        $this->dispatchConfiguredEvent('incidentCreated', $incident->title, $incident->message ?: 'A new incident was opened.', [
            'incident' => $this->serializeIncident($incident),
        ], $actor);

        return $incident->fresh();
    }

    public function toggleIncident(PanelIncident $incident, User $actor): PanelIncident
    {
        $isResolving = $incident->status === 'open';

        $incident->forceFill([
            'status' => $isResolving ? 'resolved' : 'open',
            'resolved_at' => $isResolving ? now() : null,
        ])->save();

        if ($isResolving) {
            $this->dispatchConfiguredEvent('incidentResolved', $incident->title, $incident->message ?: 'An incident was resolved.', [
                'incident' => $this->serializeIncident($incident->fresh()),
            ], $actor);
        }

        return $incident->fresh();
    }

    public function deleteIncident(PanelIncident $incident): void
    {
        $incident->delete();
    }

    public function updateMaintenanceSettings(array $payload): array
    {
        $this->putSetting(self::MAINTENANCE_FEATURE_KEY, $this->boolString((bool) ($payload['enabled'] ?? false)));

        return $this->settingsPayload();
    }

    public function createMaintenance(User $actor, array $payload): PanelMaintenanceWindow
    {
        $maintenance = PanelMaintenanceWindow::query()->create([
            'title' => $this->sanitizeText($payload['title'] ?? '', 160),
            'message' => $this->sanitizeText($payload['message'] ?? '', 4000),
            'starts_at' => CarbonImmutable::parse($payload['starts_at']),
            'ends_at' => CarbonImmutable::parse($payload['ends_at']),
            'created_by_user_id' => $actor->id,
            'is_completed' => false,
        ]);

        $this->dispatchConfiguredEvent('maintenanceScheduled', $maintenance->title, $maintenance->message ?: 'A maintenance window was scheduled.', [
            'maintenance' => $this->serializeMaintenance($maintenance),
        ], $actor);

        return $maintenance->fresh();
    }

    public function toggleMaintenanceComplete(PanelMaintenanceWindow $maintenance, User $actor): PanelMaintenanceWindow
    {
        $completed = ! $maintenance->is_completed;

        $maintenance->forceFill([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ])->save();

        if ($completed) {
            $this->dispatchConfiguredEvent('maintenanceCompleted', $maintenance->title, $maintenance->message ?: 'A maintenance window was completed.', [
                'maintenance' => $this->serializeMaintenance($maintenance->fresh()),
            ], $actor);
        }

        return $maintenance->fresh();
    }

    public function deleteMaintenance(PanelMaintenanceWindow $maintenance): void
    {
        $maintenance->delete();
    }

    public function updateSecuritySettings(array $payload): array
    {
        $this->putSetting(self::SECURITY_FEATURE_KEY, $this->boolString((bool) ($payload['enabled'] ?? false)));

        return $this->settingsPayload();
    }

    public function createSecurityAlert(User $actor, array $payload): PanelSecurityAlert
    {
        $alert = PanelSecurityAlert::query()->create([
            'title' => $this->sanitizeText($payload['title'] ?? '', 160),
            'message' => $this->sanitizeText($payload['message'] ?? '', 4000),
            'severity' => $this->normalizeExtensionSeverity($payload['severity'] ?? 'warning'),
            'status' => 'open',
            'created_by_user_id' => $actor->id,
        ]);

        $this->dispatchConfiguredEvent('securityAlertCreated', $alert->title, $alert->message ?: 'A new security alert was created.', [
            'securityAlert' => $this->serializeSecurityAlert($alert),
        ], $actor);

        return $alert->fresh();
    }

    public function toggleSecurityAlert(PanelSecurityAlert $alert, User $actor): PanelSecurityAlert
    {
        $isResolving = $alert->status === 'open';

        $alert->forceFill([
            'status' => $isResolving ? 'resolved' : 'open',
            'resolved_at' => $isResolving ? now() : null,
        ])->save();

        if ($isResolving) {
            $this->dispatchConfiguredEvent('securityAlertResolved', $alert->title, $alert->message ?: 'A security alert was resolved.', [
                'securityAlert' => $this->serializeSecurityAlert($alert->fresh()),
            ], $actor);
        }

        return $alert->fresh();
    }

    public function deleteSecurityAlert(PanelSecurityAlert $alert): void
    {
        $alert->delete();
    }

    public function serializeIncident(PanelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'message' => $incident->message,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'isOpen' => $incident->status === 'open',
            'createdAt' => optional($incident->created_at)?->toIso8601String(),
            'resolvedAt' => optional($incident->resolved_at)?->toIso8601String(),
        ];
    }

    public function serializeMaintenance(PanelMaintenanceWindow $maintenance): array
    {
        $now = CarbonImmutable::now();
        $state = 'active';

        if ($maintenance->is_completed) {
            $state = 'completed';
        } elseif ($maintenance->starts_at && $maintenance->starts_at->isFuture()) {
            $state = 'upcoming';
        } elseif ($maintenance->ends_at && $maintenance->ends_at->lt($now)) {
            $state = 'awaiting_completion';
        }

        return [
            'id' => $maintenance->id,
            'title' => $maintenance->title,
            'message' => $maintenance->message,
            'startsAt' => optional($maintenance->starts_at)?->toIso8601String(),
            'endsAt' => optional($maintenance->ends_at)?->toIso8601String(),
            'startsAtMs' => optional($maintenance->starts_at)?->valueOf(),
            'endsAtMs' => optional($maintenance->ends_at)?->valueOf(),
            'isCompleted' => (bool) $maintenance->is_completed,
            'completedAt' => optional($maintenance->completed_at)?->toIso8601String(),
            'state' => $state,
        ];
    }

    public function serializeSecurityAlert(PanelSecurityAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'title' => $alert->title,
            'message' => $alert->message,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'isOpen' => $alert->status === 'open',
            'createdAt' => optional($alert->created_at)?->toIso8601String(),
            'resolvedAt' => optional($alert->resolved_at)?->toIso8601String(),
        ];
    }

    public function serializeLog(NotificationDeliveryLog $log): array
    {
        return [
            'id' => $log->id,
            'channel' => $log->channel,
            'status' => $log->status,
            'target' => $this->maskTarget($log->channel, $log->target),
            'rawTarget' => $log->target,
            'eventKey' => $log->event_key,
            'errorText' => $log->error_text,
            'requestPayload' => $log->request_payload,
            'responsePayload' => $log->response_payload,
            'createdAt' => optional($log->created_at)?->toIso8601String(),
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'username' => $log->actor->username,
                'email' => $log->actor->email,
            ] : null,
        ];
    }

    private function dispatchConfiguredEvent(string $eventName, string $title, string $message, array $context, User $actor): void
    {
        $settings = $this->settingsPayload();

        if (! $settings['features']['webhooksEnabled'] || ! $settings['webhooks']['enabled']) {
            return;
        }

        if (! ($settings['webhooks']['events'][$eventName] ?? false)) {
            return;
        }

        $eventKey = 'extensions.' . Str::snake($eventName);
        $jsonContext = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->sendDiscordWebhook(
            $actor,
            $settings['webhooks']['discordWebhook'],
            $title,
            $message . ($jsonContext ? "\n\n" . $jsonContext : ''),
            $eventKey,
            'extension_webhook'
        );

        $this->sendTelegramWebhook(
            $actor,
            $settings['webhooks']['telegramBotToken'],
            $settings['webhooks']['telegramChatId'],
            $title,
            $message,
            $eventKey,
            'extension_webhook'
        );
    }

    private function sendDiscordWebhook(User $actor, ?string $url, string $title, string $message, string $eventKey, string $templateKey): NotificationDeliveryLog
    {
        if (blank($url)) {
            return $this->createLog([
                'channel' => 'discord',
                'status' => 'failed',
                'target' => $url,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'error_text' => 'Discord webhook is not configured.',
                'metadata' => ['source' => 'extensions'],
            ]);
        }

        try {
            $response = Http::acceptJson()->post($url, [
                'content' => "**{$title}**\n{$message}",
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            return $this->createLog([
                'channel' => 'discord',
                'status' => 'sent',
                'target' => $url,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'response_payload' => ['status' => $response->status()],
                'metadata' => ['source' => 'extensions'],
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'discord',
                'status' => 'failed',
                'target' => $url,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
                'metadata' => ['source' => 'extensions'],
            ]);
        }
    }

    private function sendTelegramWebhook(User $actor, ?string $token, ?string $chatId, string $title, string $message, string $eventKey, string $templateKey): NotificationDeliveryLog
    {
        if (blank($token) || blank($chatId)) {
            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'failed',
                'target' => $chatId,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'error_text' => 'Telegram bot token or chat ID is missing.',
                'metadata' => ['source' => 'extensions'],
            ]);
        }

        try {
            $response = Http::acceptJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $title . "\n\n" . $message,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'sent',
                'target' => $chatId,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'response_payload' => $response->json(),
                'metadata' => ['source' => 'extensions'],
            ]);
        } catch (\Throwable $exception) {
            return $this->createLog([
                'channel' => 'telegram',
                'status' => 'failed',
                'target' => $chatId,
                'template_key' => $templateKey,
                'event_key' => $eventKey,
                'attempted_by_user_id' => $actor->id,
                'request_payload' => compact('title', 'message'),
                'error_text' => Str::limit($exception->getMessage(), 5000, ''),
                'metadata' => ['source' => 'extensions'],
            ]);
        }
    }

    private function createLog(array $payload): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::query()->create([
            'channel' => $payload['channel'] ?? 'unknown',
            'status' => $payload['status'] ?? 'failed',
            'target' => $payload['target'] ?? null,
            'template_key' => $payload['template_key'] ?? null,
            'event_key' => $payload['event_key'] ?? null,
            'request_payload' => $payload['request_payload'] ?? null,
            'response_payload' => $payload['response_payload'] ?? null,
            'error_text' => $payload['error_text'] ?? null,
            'attempted_by_user_id' => $payload['attempted_by_user_id'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);
    }

    private function getSetting(string $key, string $fallback = ''): string
    {
        return (string) optional(SystemSetting::query()->find($key))->value ?: $fallback;
    }

    private function getBooleanSetting(string $key, bool $fallback = false): bool
    {
        $value = optional(SystemSetting::query()->find($key))->value;

        if ($value === null) {
            return $fallback;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function putSetting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function boolString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function webhooksConfig(bool $includeSecrets): array
    {
        $raw = optional(SystemSetting::query()->find(self::WEBHOOKS_CONFIG_KEY))->value;
        $decoded = json_decode((string) $raw, true);
        $config = is_array($decoded) ? $decoded : [];

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'discordWebhook' => $includeSecrets ? (string) ($config['discordWebhook'] ?? '') : $this->maskTarget('discord', $config['discordWebhook'] ?? null),
            'telegramBotToken' => $includeSecrets ? (string) ($config['telegramBotToken'] ?? '') : $this->maskTarget('telegram_token', $config['telegramBotToken'] ?? null),
            'telegramChatId' => $includeSecrets ? (string) ($config['telegramChatId'] ?? '') : $this->maskTarget('telegram', $config['telegramChatId'] ?? null),
            'events' => $this->normalizedEvents($config['events'] ?? []),
        ];
    }

    private function normalizedEvents(array $events): array
    {
        $normalized = [];

        foreach (self::DEFAULT_EVENTS as $key => $default) {
            $normalized[$key] = array_key_exists($key, $events) ? (bool) $events[$key] : $default;
        }

        return $normalized;
    }

    private function normalizeAnnouncerSeverity(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['normal', 'warning', 'critical'], true) ? $normalized : 'normal';
    }

    private function normalizeExtensionSeverity(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['normal', 'warning', 'critical'], true) ? $normalized : 'warning';
    }

    private function sanitizeText(?string $value, int $maxLength, string $fallback = ''): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : Str::limit($text, $maxLength, '');
    }

    private function maskTarget(string $channel, ?string $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return match ($channel) {
            'discord' => preg_replace('/(https?:\\/\\/[^\\/]+\\/api\\/webhooks\\/\\d+\\/).+/', '$1••••', $text) ?: 'configured',
            'telegram_token', 'api_key' => Str::mask($text, '•', 4),
            'telegram' => Str::mask($text, '•', 2),
            default => Str::mask($text, '•', 4),
        };
    }
}
