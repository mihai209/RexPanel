<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelIncident;
use App\Models\PanelMaintenanceWindow;
use App\Models\PanelSecurityAlert;
use App\Services\ExtensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminExtensionController extends Controller
{
    public function __construct(private ExtensionService $extensions)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->extensions->adminPayload());
    }

    public function updateAnnouncer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'severity' => ['required', 'string', 'in:normal,warning,critical'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json([
            'message' => 'Announcer settings updated.',
            'settings' => $this->extensions->updateAnnouncer($data),
        ]);
    }

    public function updateWebhooks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'moduleEnabled' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'discordWebhook' => ['nullable', 'url', 'max:1024'],
            'telegramBotToken' => ['nullable', 'string', 'max:255', 'required_with:telegramChatId'],
            'telegramChatId' => ['nullable', 'string', 'max:255', 'required_with:telegramBotToken'],
            'events' => ['nullable', 'array'],
            'events.incidentCreated' => ['nullable', 'boolean'],
            'events.incidentResolved' => ['nullable', 'boolean'],
            'events.maintenanceScheduled' => ['nullable', 'boolean'],
            'events.maintenanceCompleted' => ['nullable', 'boolean'],
            'events.securityAlertCreated' => ['nullable', 'boolean'],
            'events.securityAlertResolved' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Extensions webhook settings updated.',
            'settings' => $this->extensions->updateWebhookSettings($data),
        ]);
    }

    public function testWebhooks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $logs = $this->extensions->testWebhooks($request->user(), $data);

        return response()->json([
            'message' => 'Extensions webhook test executed.',
            'logs' => collect($logs)->map(fn ($log) => $this->extensions->serializeLog($log->load('actor')))->values(),
        ]);
    }

    public function updateIncidentSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Incidents settings updated.',
            'settings' => $this->extensions->updateIncidentSettings($data),
        ]);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:4000'],
            'severity' => ['required', 'string', 'in:normal,warning,critical'],
        ]);

        $incident = $this->extensions->createIncident($request->user(), $data);

        return response()->json([
            'message' => 'Incident created.',
            'incident' => $this->extensions->serializeIncident($incident),
        ]);
    }

    public function toggleIncident(PanelIncident $incident, Request $request): JsonResponse
    {
        $incident = $this->extensions->toggleIncident($incident, $request->user());

        return response()->json([
            'message' => 'Incident state updated.',
            'incident' => $this->extensions->serializeIncident($incident),
        ]);
    }

    public function destroyIncident(PanelIncident $incident): JsonResponse
    {
        $this->extensions->deleteIncident($incident);

        return response()->json([
            'message' => 'Incident deleted.',
        ]);
    }

    public function updateMaintenanceSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Maintenance settings updated.',
            'settings' => $this->extensions->updateMaintenanceSettings($data),
        ]);
    }

    public function storeMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:4000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ]);

        $maintenance = $this->extensions->createMaintenance($request->user(), $data);

        return response()->json([
            'message' => 'Maintenance window created.',
            'maintenance' => $this->extensions->serializeMaintenance($maintenance),
        ]);
    }

    public function toggleMaintenanceComplete(PanelMaintenanceWindow $maintenance, Request $request): JsonResponse
    {
        $maintenance = $this->extensions->toggleMaintenanceComplete($maintenance, $request->user());

        return response()->json([
            'message' => 'Maintenance state updated.',
            'maintenance' => $this->extensions->serializeMaintenance($maintenance),
        ]);
    }

    public function destroyMaintenance(PanelMaintenanceWindow $maintenance): JsonResponse
    {
        $this->extensions->deleteMaintenance($maintenance);

        return response()->json([
            'message' => 'Maintenance window deleted.',
        ]);
    }

    public function updateSecuritySettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Security Center settings updated.',
            'settings' => $this->extensions->updateSecuritySettings($data),
        ]);
    }

    public function storeSecurity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:4000'],
            'severity' => ['required', 'string', 'in:normal,warning,critical'],
        ]);

        $alert = $this->extensions->createSecurityAlert($request->user(), $data);

        return response()->json([
            'message' => 'Security alert created.',
            'alert' => $this->extensions->serializeSecurityAlert($alert),
        ]);
    }

    public function toggleSecurity(PanelSecurityAlert $alert, Request $request): JsonResponse
    {
        $alert = $this->extensions->toggleSecurityAlert($alert, $request->user());

        return response()->json([
            'message' => 'Security alert state updated.',
            'alert' => $this->extensions->serializeSecurityAlert($alert),
        ]);
    }

    public function destroySecurity(PanelSecurityAlert $alert): JsonResponse
    {
        $this->extensions->deleteSecurityAlert($alert);

        return response()->json([
            'message' => 'Security alert deleted.',
        ]);
    }
}
