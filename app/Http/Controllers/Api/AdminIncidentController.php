<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelIncident;
use App\Models\PolicyEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminIncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $runtimeIncidents = PolicyEvent::query()
            ->with('user:id,username,email')
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 25));

        $extensionIncidents = PanelIncident::query()
            ->with('actor:id,username,email')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'runtime' => [
                ...$runtimeIncidents->toArray(),
                'data' => collect($runtimeIncidents->items())->map(fn (PolicyEvent $event) => $this->serializeRuntimeIncident($event))->values(),
            ],
            'extension_incidents' => $extensionIncidents->map(fn (PanelIncident $incident) => $this->serializeExtensionIncident($incident))->values(),
            'summary' => [
                'open' => PolicyEvent::query()->where('status', 'open')->count(),
                'resolved' => PolicyEvent::query()->where('status', 'resolved')->count(),
                'extension_open' => PanelIncident::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    public function resolve(PolicyEvent $policyEvent): JsonResponse
    {
        $policyEvent->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Incident resolved.',
            'incident' => $this->serializeRuntimeIncident($policyEvent->fresh()->load('user')),
        ]);
    }

    public function reopen(PolicyEvent $policyEvent): JsonResponse
    {
        $policyEvent->update([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        return response()->json([
            'message' => 'Incident reopened.',
            'incident' => $this->serializeRuntimeIncident($policyEvent->fresh()->load('user')),
        ]);
    }

    public function clear(): JsonResponse
    {
        $deleted = PolicyEvent::query()->delete();

        return response()->json([
            'message' => 'Runtime incidents cleared.',
            'deleted' => $deleted,
        ]);
    }

    public function clearResolved(): JsonResponse
    {
        $deleted = PolicyEvent::query()->where('status', 'resolved')->delete();

        return response()->json([
            'message' => 'Resolved runtime incidents cleared.',
            'deleted' => $deleted,
        ]);
    }

    public function exportJson(): JsonResponse
    {
        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'runtime_incidents' => PolicyEvent::query()
                ->with('user:id,username,email')
                ->latest('created_at')
                ->get()
                ->map(fn (PolicyEvent $event) => $this->serializeRuntimeIncident($event))
                ->values(),
        ]);
    }

    public function exportHtml(): \Illuminate\Http\Response
    {
        $items = PolicyEvent::query()->with('user:id,username,email')->latest('created_at')->get();
        $rows = $items->map(function (PolicyEvent $event): string {
            $title = e($event->title ?: $event->reason ?: $event->policy_key);
            $reason = e($event->reason ?: 'No detail provided.');
            $status = e((string) $event->status);
            $severity = e((string) $event->severity);
            $createdAt = e(optional($event->created_at)?->toIso8601String() ?? '');

            return "<tr><td>{$title}</td><td>{$severity}</td><td>{$status}</td><td>{$reason}</td><td>{$createdAt}</td></tr>";
        })->implode('');

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Runtime Incidents</title></head><body>'
            . '<h1>Runtime Incidents</h1>'
            . '<table border="1" cellspacing="0" cellpadding="8"><thead><tr><th>Title</th><th>Severity</th><th>Status</th><th>Reason</th><th>Created At</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function serializeRuntimeIncident(PolicyEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title ?: $event->reason ?: $event->policy_key,
            'policy_key' => $event->policy_key,
            'severity' => $event->severity,
            'status' => $event->status ?: 'open',
            'reason' => $event->reason,
            'score_delta' => $event->score_delta,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'metadata' => $event->metadata,
            'resolved_at' => optional($event->resolved_at)?->toIso8601String(),
            'created_at' => optional($event->created_at)?->toIso8601String(),
            'user' => $event->user ? [
                'id' => $event->user->id,
                'username' => $event->user->username,
                'email' => $event->user->email,
            ] : null,
        ];
    }

    private function serializeExtensionIncident(PanelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'message' => $incident->message,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'resolved_at' => optional($incident->resolved_at)?->toIso8601String(),
            'created_at' => optional($incident->created_at)?->toIso8601String(),
            'read_only' => true,
            'actor' => $incident->actor ? [
                'id' => $incident->actor->id,
                'username' => $incident->actor->username,
                'email' => $incident->actor->email,
            ] : null,
        ];
    }
}
