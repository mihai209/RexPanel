<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BillingEvent;
use App\Models\User;

class BillingAuditService
{
    public function __construct(
        private SystemSettingsService $settings,
        private ExtensionService $extensions,
    ) {
    }

    public function record(
        User $user,
        string $eventType,
        string $action,
        int $coinsDelta = 0,
        array $resourceDelta = [],
        ?string $sourceType = null,
        ?string $sourceId = null,
        array $details = [],
        ?string $ip = null,
    ): BillingEvent {
        $walletBefore = max(0, (int) $user->fresh()->coins - $coinsDelta);
        $walletAfter = (int) $user->fresh()->coins;

        $event = BillingEvent::query()->create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'coins_delta' => $coinsDelta,
            'wallet_before' => $walletBefore,
            'wallet_after' => $walletAfter,
            'resource_delta' => $resourceDelta,
            'details' => $details,
            'created_at' => now(),
        ]);

        ActivityLog::log($user->id, $action, $ip, 'commerce.' . $eventType, [
            'coinsDelta' => $coinsDelta,
            'resourceDelta' => $resourceDelta,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
            ...$details,
        ]);

        $runtime = $this->settings->commerceRuntimeValues();
        if ($runtime['features']['billingInvoicesEnabled'] && $runtime['features']['billingInvoiceWebhookEnabled']) {
            $this->extensions->dispatchCommerceInvoiceEvent($user, $event);
        }

        return $event;
    }
}
