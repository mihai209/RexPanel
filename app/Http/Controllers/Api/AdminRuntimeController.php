<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseScoreWindow;
use App\Models\AntiMinerSample;
use App\Models\Node;
use App\Models\PolicyEvent;
use App\Models\RemediationAction;
use App\Models\ServiceHealthCheck;
use App\Services\RuntimeMonitoringService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRuntimeController extends Controller
{
    public function __construct(
        private SystemSettingsService $settings,
        private RuntimeMonitoringService $monitoring,
    )
    {
    }

    public function policyEvents(): JsonResponse
    {
        $this->ensureFeatureEnabled('featurePolicyEngineEnabled', 'Policy engine');

        return response()->json(
            PolicyEvent::query()
                ->with('user:id,username,email')
                ->orderByDesc('created_at')
                ->paginate(25)
        );
    }

    public function remediationActions(): JsonResponse
    {
        $this->ensureFeatureEnabled('featureAutoRemediationEnabled', 'Auto-remediation');

        return response()->json(
            RemediationAction::query()
                ->with('policyEvent')
                ->orderByDesc('created_at')
                ->paginate(25)
        );
    }

    public function abuseScores(): JsonResponse
    {
        $this->ensureFeatureEnabled('featureAbuseScoreEnabled', 'Abuse score engine');

        return response()->json(
            AbuseScoreWindow::query()
                ->orderByDesc('updated_at')
                ->paginate(25)
        );
    }

    public function serviceHealth(): JsonResponse
    {
        $this->ensureFeatureEnabled('featureServiceHealthChecksEnabled', 'Service health checks');

        return response()->json(
            ServiceHealthCheck::query()
                ->with(['node:id,name', 'server:id,name'])
                ->orderByDesc('checked_at')
                ->paginate(25)
        );
    }

    public function antiMiner(): JsonResponse
    {
        $this->ensureFeatureEnabled('featureAntiMinerEnabled', 'Anti-miner');

        return response()->json(
            AntiMinerSample::query()
                ->with(['node:id,name', 'server:id,name', 'user:id,username,email'])
                ->orderByDesc('sampled_at')
                ->paginate(25)
        );
    }

    public function ingestTelemetry(Request $request): JsonResponse
    {
        $runtime = $this->settings->policyRuntimeValues();

        if (! $runtime['features']['antiMinerEnabled'] && ! $runtime['features']['serviceHealthChecksEnabled']) {
            return response()->json([
                'message' => 'Telemetry ingestion is disabled by feature flags.',
            ], 403);
        }

        $data = $request->validate([
            'node_id' => ['required', 'exists:nodes,id'],
            'status' => ['nullable', 'string'],
            'response_time_ms' => ['nullable', 'integer', 'min:0'],
            'usage' => ['nullable', 'array'],
            'server_samples' => ['nullable', 'array'],
            'server_samples.*.server_id' => ['required_with:server_samples', 'integer'],
            'server_samples.*.cpu_percent' => ['required_with:server_samples', 'integer', 'min:0'],
        ]);

        $node = Node::query()->findOrFail($data['node_id']);
        $result = $this->monitoring->ingestNodeTelemetry($node, $data);

        return response()->json([
            'message' => 'Telemetry processed.',
            'result' => $result,
        ]);
    }

    public function runPlaybook(PolicyEvent $policyEvent, Request $request): JsonResponse
    {
        $data = $request->validate([
            'playbook' => ['required', 'string'],
        ]);

        $results = $this->monitoring->runPlaybook($policyEvent, $data['playbook']);

        return response()->json([
            'message' => 'Playbook executed.',
            'actions' => $results,
        ]);
    }

    private function ensureFeatureEnabled(string $key, string $label): void
    {
        if (! (bool) $this->settings->getValue($key, false)) {
            abort(response()->json([
                'message' => "{$label} is disabled by an administrator.",
            ], 403));
        }
    }
}
