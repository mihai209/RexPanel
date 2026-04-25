<?php

namespace App\Services;

use App\Models\AbuseScoreWindow;
use App\Models\ActivityLog;
use App\Models\AntiMinerSample;
use App\Models\Node;
use App\Models\PolicyEvent;
use App\Models\RemediationAction;
use App\Models\ServiceHealthCheck;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RuntimeMonitoringService
{
    public function __construct(
        private SystemSettingsService $settings,
        private RewardsRuntimeService $rewards,
    )
    {
    }

    public function ingestNodeTelemetry(Node $node, array $payload): array
    {
        $settings = $this->settings->allValues();
        $now = now();
        $events = [];

        return DB::transaction(function () use ($node, $payload, $settings, $now, &$events) {
            $this->storeConnectorDiagnostics($node, $payload, $now);

            if ((bool) $settings['featureServiceHealthChecksEnabled']) {
                $events[] = $this->recordHealthCheck(
                    nodeId: $node->id,
                    serverId: null,
                    status: (string) ($payload['status'] ?? 'healthy'),
                    responseTimeMs: isset($payload['response_time_ms']) ? (int) $payload['response_time_ms'] : null,
                    metadata: [
                        'source' => 'telemetry',
                        'usage' => $payload['usage'] ?? null,
                    ],
                    checkedAt: $now,
                );
            }

            $serverSamples = is_array($payload['server_samples'] ?? null)
                ? $payload['server_samples']
                : (is_array($payload['usage']['servers'] ?? null) ? $payload['usage']['servers'] : []);

            $antiMinerResults = [];

            if ((bool) $settings['featureAntiMinerEnabled']) {
                foreach ($serverSamples as $sample) {
                    $serverId = (int) ($sample['server_id'] ?? $sample['serverId'] ?? 0);
                    $cpuPercent = (int) ($sample['cpu_percent'] ?? $sample['cpuPercent'] ?? 0);

                    if ($serverId <= 0) {
                        continue;
                    }

                    $server = Server::query()->with('user')->find($serverId);

                    if (! $server) {
                        continue;
                    }

                    $antiMinerResults[] = $this->recordAntiMinerSample(
                        $node,
                        $server,
                        $cpuPercent,
                        [
                            'source' => 'telemetry',
                            'raw' => $sample,
                        ],
                        $settings,
                        $now,
                    );
                }
            }

            return [
                'health' => $events,
                'antiMiner' => array_values(array_filter($antiMinerResults)),
            ];
        });
    }

    public function recordHealthCheck(?int $nodeId, ?int $serverId, string $status, ?int $responseTimeMs, array $metadata = [], ?Carbon $checkedAt = null): ServiceHealthCheck
    {
        $checkedAt ??= now();
        $status = strtolower(trim($status)) ?: 'unknown';
        $previous = ServiceHealthCheck::query()
            ->when($nodeId, fn ($query) => $query->where('node_id', $nodeId))
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->latest('checked_at')
            ->first();

        $record = ServiceHealthCheck::query()->create([
            'node_id' => $nodeId,
            'server_id' => $serverId,
            'status' => $status,
            'response_time_ms' => $responseTimeMs,
            'checked_at' => $checkedAt,
            'metadata' => $metadata,
        ]);

        $featureEnabled = (bool) $this->settings->getValue('featurePolicyEngineEnabled', false);

        if ($featureEnabled && $previous && $previous->status !== $status) {
            $policyKey = in_array($status, ['healthy', 'ok'], true)
                ? 'health.recovered'
                : 'health.failed';

            $this->createPolicyEvent(
                subjectType: $serverId ? 'server' : 'node',
                subjectId: $serverId ?: $nodeId,
                userId: null,
                policyKey: $policyKey,
                severity: in_array($status, ['healthy', 'ok'], true) ? 'info' : 'warning',
                scoreDelta: 0,
                reason: sprintf('Health state changed from %s to %s.', $previous->status, $status),
                metadata: [
                    'previousStatus' => $previous->status,
                    'status' => $status,
                    'responseTimeMs' => $responseTimeMs,
                ],
            );
        }

        return $record;
    }

    public function listLatestHealthByNode(): array
    {
        return ServiceHealthCheck::query()
            ->with(['node:id,name', 'server:id,name'])
            ->orderByDesc('checked_at')
            ->get()
            ->unique(fn (ServiceHealthCheck $check) => sprintf('%s:%s', $check->node_id ?: 0, $check->server_id ?: 0))
            ->values()
            ->all();
    }

    public function runPlaybook(PolicyEvent $event, string $playbook): array
    {
        if (! (bool) $this->settings->getValue('featurePlaybooksAutomationEnabled', false)) {
            abort(response()->json([
                'message' => 'Playbooks automation is disabled by an administrator.',
            ], 403));
        }

        $actions = match ($playbook) {
            'abuse-lockdown' => ['disable_reward_accrual', 'suspend_account'],
            'miner-response' => ['disable_reward_accrual', 'mark_node_unhealthy', 'suspend_account'],
            default => abort(response()->json([
                'message' => 'Unknown playbook.',
            ], 422)),
        };

        $results = [];

        foreach ($actions as $action) {
            $results[] = $this->executeRemediation($event, $action);
        }

        return $results;
    }

    public function executeRemediation(PolicyEvent $event, string $actionType): RemediationAction
    {
        $subjectType = $event->subject_type;
        $subjectId = $event->subject_id;
        $cooldownSeconds = (int) $this->settings->getValue('autoRemediationCooldownSeconds', 300);
        $cooldownUntil = now()->addSeconds($cooldownSeconds);

        $activeCooldown = RemediationAction::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('action_type', $actionType)
            ->where('cooldown_until', '>', now())
            ->latest('id')
            ->first();

        if ($activeCooldown) {
            return RemediationAction::query()->create([
                'policy_event_id' => $event->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action_type' => $actionType,
                'status' => 'cooldown',
                'cooldown_until' => $activeCooldown->cooldown_until,
                'metadata' => [
                    'reason' => 'cooldown_active',
                ],
            ]);
        }

        try {
            match ($actionType) {
                'suspend_account' => $this->suspendEventUser($event),
                'disable_reward_accrual' => $this->disableRewardAccrual($event),
                'mark_node_unhealthy' => $this->markNodeUnhealthy($event),
                default => throw new \RuntimeException('Unknown remediation action.'),
            };

            $action = RemediationAction::query()->create([
                'policy_event_id' => $event->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action_type' => $actionType,
                'status' => 'completed',
                'cooldown_until' => $cooldownUntil,
                'metadata' => [
                    'policyKey' => $event->policy_key,
                ],
            ]);

            if ($event->user_id) {
                ActivityLog::log($event->user_id, sprintf('Remediation executed: %s', $actionType), null, 'policy.remediation', [
                    'actionType' => $actionType,
                    'policyKey' => $event->policy_key,
                    'subjectType' => $subjectType,
                    'subjectId' => $subjectId,
                ]);
            }

            return $action;
        } catch (\Throwable $exception) {
            return RemediationAction::query()->create([
                'policy_event_id' => $event->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action_type' => $actionType,
                'status' => 'failed',
                'cooldown_until' => null,
                'metadata' => [
                    'error' => $exception->getMessage(),
                ],
            ]);
        }
    }

    public function createPolicyEvent(
        string $subjectType,
        ?int $subjectId,
        ?int $userId,
        string $policyKey,
        string $severity,
        int $scoreDelta,
        ?string $reason,
        array $metadata = [],
    ): PolicyEvent {
        $event = PolicyEvent::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'policy_key' => $policyKey,
            'severity' => $severity,
            'score_delta' => $scoreDelta,
            'reason' => $reason,
            'title' => $metadata['title'] ?? null,
            'status' => 'open',
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        if ($userId) {
            ActivityLog::log($userId, $reason ?: $policyKey, null, 'policy.event', [
                'policyKey' => $policyKey,
                'severity' => $severity,
                'scoreDelta' => $scoreDelta,
                'reason' => $reason,
                'subjectType' => $subjectType,
                'subjectId' => $subjectId,
            ]);
        }

        return $event;
    }

    public function storeConnectorDiagnostics(Node $node, array $payload, ?Carbon $capturedAt = null): void
    {
        $diagnostics = $payload['diagnostics']
            ?? $payload['connector_diagnostics']
            ?? $payload['diagnostic_payload']
            ?? null;

        if (! is_array($diagnostics)) {
            return;
        }

        $capturedAt ??= now();

        $node->forceFill([
            'connector_diagnostics' => $diagnostics,
            'diagnostics_updated_at' => $capturedAt,
        ])->save();
    }

    private function recordAntiMinerSample(Node $node, Server $server, int $cpuPercent, array $metadata, array $settings, Carbon $sampledAt): ?array
    {
        $sample = AntiMinerSample::query()->create([
            'node_id' => $node->id,
            'server_id' => $server->id,
            'user_id' => $server->user_id,
            'cpu_percent' => max(0, $cpuPercent),
            'sampled_at' => $sampledAt,
            'resulting_score_delta' => 0,
            'metadata' => $metadata,
        ]);

        if ($cpuPercent < (int) $settings['antiMinerHighCpuPercent']) {
            return null;
        }

        $windowStart = $sampledAt->copy()->subMinutes((int) $settings['antiMinerDecayMinutes']);
        $requiredSamples = (int) $settings['antiMinerHighCpuSamples'];
        $highSampleCount = AntiMinerSample::query()
            ->where('server_id', $server->id)
            ->where('cpu_percent', '>=', (int) $settings['antiMinerHighCpuPercent'])
            ->latest('id')
            ->take($requiredSamples)
            ->get()
            ->count();

        $sample->forceFill(['resulting_score_delta' => 1])->save();

        $event = $this->createPolicyEvent(
            subjectType: 'server',
            subjectId: $server->id,
            userId: $server->user_id,
            policyKey: 'anti_miner.high_cpu_detected',
            severity: 'warning',
            scoreDelta: 1,
            reason: sprintf('High CPU telemetry reached %d samples for server %d.', $highSampleCount, $server->id),
            metadata: [
                'cpuPercent' => $cpuPercent,
                'sampleCount' => $highSampleCount,
                'threshold' => $requiredSamples,
                'decayWindowStartedAt' => $windowStart->toIso8601String(),
                'nodeId' => $node->id,
            ],
        );

        $userScore = $server->user_id
            ? $this->bumpAbuseScore('user', $server->user_id, 1, $settings, $event, $server->user)
            : null;
        $serverScore = $this->bumpAbuseScore('server', $server->id, 1, $settings, $event, null);

        if ((int) ($userScore?->score ?? 0) >= (int) $settings['antiMinerSuspendScore']
            || (int) $serverScore->score >= (int) $settings['antiMinerSuspendScore']) {
            $thresholdEvent = $this->createPolicyEvent(
                subjectType: 'server',
                subjectId: $server->id,
                userId: $server->user_id,
                policyKey: 'anti_miner.suspend_threshold',
                severity: 'critical',
                scoreDelta: 0,
                reason: 'Anti-miner suspend threshold reached.',
                metadata: [
                    'threshold' => (int) $settings['antiMinerSuspendScore'],
                    'userScore' => (int) ($userScore?->score ?? 0),
                    'serverScore' => (int) $serverScore->score,
                    'nodeId' => $node->id,
                ],
            );

            if ((bool) $settings['featureAutoRemediationEnabled']) {
                $this->executeRemediation($thresholdEvent, 'disable_reward_accrual');
                $this->executeRemediation($thresholdEvent, 'mark_node_unhealthy');

                if ($server->user_id) {
                    $this->executeRemediation($thresholdEvent, 'suspend_account');
                }
            }
        }

        return [
            'sample_id' => $sample->id,
            'server_id' => $server->id,
            'cpu_percent' => $cpuPercent,
            'sample_count' => $highSampleCount,
        ];
    }

    private function bumpAbuseScore(string $subjectType, int $subjectId, int $delta, array $settings, PolicyEvent $sourceEvent, ?User $user): AbuseScoreWindow
    {
        $now = now();
        $window = AbuseScoreWindow::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('window_ends_at', '>', $now)
            ->latest('window_started_at')
            ->first();

        if (! $window) {
            $window = AbuseScoreWindow::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'score' => 0,
                'window_started_at' => $now,
                'window_ends_at' => $now->copy()->addHours((int) $settings['abuseScoreWindowHours']),
            ]);
        }

        $window->forceFill([
            'score' => (int) $window->score + $delta,
        ])->save();

        $threshold = (int) $settings['abuseScoreAlertThreshold'];

        if ((bool) $settings['featureAbuseScoreEnabled']
            && (int) $window->score >= $threshold
            && ! $window->last_triggered_at) {
            $window->forceFill(['last_triggered_at' => $now])->save();

            $thresholdEvent = $this->createPolicyEvent(
                subjectType: $subjectType,
                subjectId: $subjectId,
                userId: $user?->id,
                policyKey: 'abuse.threshold_hit',
                severity: 'warning',
                scoreDelta: $delta,
                reason: sprintf('Abuse score threshold reached for %s %d.', $subjectType, $subjectId),
                metadata: [
                    'threshold' => $threshold,
                    'score' => (int) $window->score,
                    'windowEndsAt' => $window->window_ends_at?->toIso8601String(),
                    'sourcePolicyEventId' => $sourceEvent->id,
                ],
            );

            if ((bool) $settings['featureAutoRemediationEnabled']) {
                $this->executeRemediation($thresholdEvent, 'disable_reward_accrual');
            }
        }

        return $window->fresh();
    }

    private function suspendEventUser(PolicyEvent $event): void
    {
        if (! $event->user_id) {
            return;
        }

        $user = User::query()->find($event->user_id);

        if ($user) {
            $user->forceFill(['is_suspended' => true])->save();
        }
    }

    private function disableRewardAccrual(PolicyEvent $event): void
    {
        if (! $event->user_id) {
            return;
        }

        $user = User::query()->find($event->user_id);

        if ($user) {
            $this->rewards->disableRewardAccrual($user);
        }
    }

    private function markNodeUnhealthy(PolicyEvent $event): void
    {
        $nodeId = $event->metadata['nodeId'] ?? ($event->subject_type === 'node' ? $event->subject_id : null);

        if (! $nodeId) {
            return;
        }

        $node = Node::query()->find($nodeId);

        if (! $node) {
            return;
        }

        $node->forceFill(['maintenance_mode' => true])->save();
        $this->recordHealthCheck(
            nodeId: $node->id,
            serverId: null,
            status: 'unhealthy',
            responseTimeMs: null,
            metadata: [
                'source' => 'remediation',
                'policyEventId' => $event->id,
            ],
        );
    }
}
