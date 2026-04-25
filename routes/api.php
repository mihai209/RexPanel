<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthProviderController;
use App\Http\Controllers\Api\AdminAuthProviderController;
use App\Http\Controllers\Api\AdminExtensionController;
use App\Http\Controllers\Api\AdminConnectorLabController;
use App\Http\Controllers\Api\AdminIncidentController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminNotificationTestController;
use App\Http\Controllers\Api\AdminCaptchaController;
use App\Http\Controllers\Api\AdminMountController;
use App\Http\Controllers\Api\AdminForecastingController;
use App\Http\Controllers\Api\AdminRedeemCodeController;
use App\Http\Controllers\Api\AdminRedisController;
use App\Http\Controllers\Api\AdminRevenuePlanController;
use App\Http\Controllers\Api\AdminRuntimeController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminServiceHealthCheckController;
use App\Http\Controllers\Api\AdminStoreDealController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountNotificationController;
use App\Http\Controllers\Api\AuthCaptchaController;
use App\Http\Controllers\Api\ExtensionStatusController;
use App\Http\Controllers\Api\ServerMountController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\UserServerController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RA-panel API Routes (v1)
|--------------------------------------------------------------------------
*/

// Public Login Alias (Simplified)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/v1/auth/providers', [AuthProviderController::class, 'index']);
Route::get('/v1/auth/captcha', [AuthCaptchaController::class, 'show']);
Route::get('/v1/extensions/status', [ExtensionStatusController::class, 'index']);

Route::prefix('v1')->group(function () {
    
    // Public Authentication
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/login/2fa', [AuthController::class, 'completeTwoFactorLogin']);

    // Authenticated Routes
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Account Management
        Route::put('/account/theme', [AccountController::class, 'updateTheme']);
        Route::put('/account/details', [AccountController::class, 'updateDetails']);
        Route::put('/account/password', [AccountController::class, 'changePassword']);
        Route::get('/account/activity', [AccountController::class, 'getActivity']);
        Route::delete('/account/activity', [AccountController::class, 'clearActivity']);
        Route::get('/account/notifications', [AccountNotificationController::class, 'index']);
        Route::get('/account/notifications/recent', [AccountNotificationController::class, 'recent']);
        Route::post('/account/notifications/read-all', [AccountNotificationController::class, 'markAllRead']);
        Route::get('/account/notifications/unread-count', [AccountNotificationController::class, 'unreadCount']);
        Route::post('/account/notifications/browser-subscriptions', [AccountNotificationController::class, 'storeBrowserSubscription']);
        Route::delete('/account/notifications/browser-subscriptions', [AccountNotificationController::class, 'deleteBrowserSubscription']);
        Route::post('/account/notifications/{notification}/read', [AccountNotificationController::class, 'markRead']);
        Route::get('/account/extensions/status', [ExtensionStatusController::class, 'index']);
        Route::get('/account/rewards', [AccountController::class, 'rewards']);
        Route::post('/account/rewards/claim', [AccountController::class, 'claimReward']);
        Route::get('/account/afk', [AccountController::class, 'afk']);
        Route::post('/account/afk/ping', [AccountController::class, 'afkPing']);
        Route::get('/store', [StoreController::class, 'overview']);
        Route::get('/store/deals', [StoreController::class, 'deals']);
        Route::get('/store/redeem', [StoreController::class, 'redeemStatus']);
        Route::post('/store/revenue/subscribe', [StoreController::class, 'subscribeRevenuePlan']);
        Route::post('/store/deals/purchase', [StoreController::class, 'purchaseDeal']);
        Route::post('/store/redeem', [StoreController::class, 'redeemCode']);
        Route::get('/store/forecast', [StoreController::class, 'forecast']);
        Route::get('/account/linked-accounts', [AccountController::class, 'linkedAccounts']);
        Route::post('/account/linked-accounts/{provider}/redirect', [AccountController::class, 'createLinkedAccountRedirect']);
        Route::delete('/account/linked-accounts/{provider}', [AccountController::class, 'unlinkLinkedAccount']);
        Route::get('/account/2fa/setup', [AccountController::class, 'setupTwoFactor']);
        Route::post('/account/2fa/enable', [AccountController::class, 'enableTwoFactor']);
        Route::post('/account/2fa/disable', [AccountController::class, 'disableTwoFactor']);
        Route::get('/servers', [UserServerController::class, 'index']);
        Route::get('/servers/{containerId}', [UserServerController::class, 'show']);
        Route::get('/servers/{containerId}/resources', [UserServerController::class, 'resources']);
        Route::post('/servers/{containerId}/power', [UserServerController::class, 'power']);
        Route::post('/servers/{containerId}/console', [UserServerController::class, 'sendConsoleCommand']);
        Route::get('/servers/{containerId}/console/ws-token', [UserServerController::class, 'websocketBootstrap']);

        // Admin Routes
        Route::middleware([\App\Http\Middleware\EnsureUserIsAdmin::class, 'admin.rate-plan'])->prefix('admin')->group(function () {
            Route::get('auth-providers', [AdminAuthProviderController::class, 'index']);
            Route::put('auth-providers', [AdminAuthProviderController::class, 'update']);
            Route::get('settings', [AdminSettingsController::class, 'index']);
            Route::put('settings', [AdminSettingsController::class, 'update']);
            Route::get('revenue-plans', [AdminRevenuePlanController::class, 'index']);
            Route::post('revenue-plans', [AdminRevenuePlanController::class, 'store']);
            Route::put('revenue-plans/{planId}', [AdminRevenuePlanController::class, 'update']);
            Route::delete('revenue-plans/{planId}', [AdminRevenuePlanController::class, 'destroy']);
            Route::get('store/deals', [AdminStoreDealController::class, 'index']);
            Route::post('store/deals', [AdminStoreDealController::class, 'store']);
            Route::put('store/deals/{dealId}', [AdminStoreDealController::class, 'update']);
            Route::delete('store/deals/{dealId}', [AdminStoreDealController::class, 'destroy']);
            Route::get('store/redeem-codes', [AdminRedeemCodeController::class, 'index']);
            Route::post('store/redeem-codes', [AdminRedeemCodeController::class, 'store']);
            Route::put('store/redeem-codes/{codeId}', [AdminRedeemCodeController::class, 'update']);
            Route::delete('store/redeem-codes/{codeId}', [AdminRedeemCodeController::class, 'destroy']);
            Route::get('forecasting', [AdminForecastingController::class, 'index']);
            Route::get('captcha', [AdminCaptchaController::class, 'index']);
            Route::put('captcha', [AdminCaptchaController::class, 'update']);
            Route::get('redis', [AdminRedisController::class, 'index']);
            Route::put('redis', [AdminRedisController::class, 'update']);
            Route::post('redis/test', [AdminRedisController::class, 'test']);
            Route::get('mounts', [AdminMountController::class, 'index']);
            Route::post('mounts', [AdminMountController::class, 'store']);
            Route::delete('mounts/{mount}', [AdminMountController::class, 'destroy']);
            Route::get('policy-events', [AdminRuntimeController::class, 'policyEvents']);
            Route::get('remediation-actions', [AdminRuntimeController::class, 'remediationActions']);
            Route::get('abuse-scores', [AdminRuntimeController::class, 'abuseScores']);
            Route::get('service-health', [AdminRuntimeController::class, 'serviceHealth']);
            Route::get('anti-miner', [AdminRuntimeController::class, 'antiMiner']);
            Route::post('runtime/telemetry', [AdminRuntimeController::class, 'ingestTelemetry']);
            Route::post('policy-events/{policyEvent}/playbooks', [AdminRuntimeController::class, 'runPlaybook']);
            Route::post('users/{user}/unlink/{provider}', [UserController::class, 'unlinkProvider']);
            Route::post('users/{user}/ai-quota', [UserController::class, 'updateAiQuota']);
            Route::post('users/{user}/ai-quota/reset', [UserController::class, 'resetAiQuota']);
            Route::post('users/{user}/disable-2fa', [UserController::class, 'disableTwoFactor']);
            Route::apiResource('users', UserController::class);
            Route::apiResource('locations', \App\Http\Controllers\Api\LocationController::class);
            Route::apiResource('nodes', \App\Http\Controllers\Api\NodeController::class);
            Route::get('nodes/{node}/configuration', [\App\Http\Controllers\Api\NodeController::class, 'configuration']);
            Route::post('nodes/{node}/regenerate-token', [\App\Http\Controllers\Api\NodeController::class, 'regenerateToken']);
            Route::post('nodes/{node}/diagnostics/run', [\App\Http\Controllers\Api\NodeController::class, 'runDiagnostics']);
            
            // Node Allocations
            Route::get('nodes/{node}/allocations', [\App\Http\Controllers\Api\NodeController::class, 'listAllocations']);
            Route::post('nodes/{node}/allocations', [\App\Http\Controllers\Api\NodeController::class, 'createAllocations']);
            Route::delete('nodes/{node}/allocations/{allocation}', [\App\Http\Controllers\Api\NodeController::class, 'deleteAllocation']);

            Route::apiResource('servers', \App\Http\Controllers\Api\ServerController::class);
            Route::post('servers/{server}/resources', [\App\Http\Controllers\Api\ServerController::class, 'updateResources']);
            Route::get('servers/{server}/network', [\App\Http\Controllers\Api\ServerAllocationController::class, 'index']);
            Route::post('servers/{server}/allocations', [\App\Http\Controllers\Api\ServerAllocationController::class, 'store']);
            Route::post('servers/{server}/allocations/{allocation}/primary', [\App\Http\Controllers\Api\ServerAllocationController::class, 'setPrimary']);
            Route::patch('servers/{server}/allocations/{allocation}', [\App\Http\Controllers\Api\ServerAllocationController::class, 'update']);
            Route::delete('servers/{server}/allocations/{allocation}', [\App\Http\Controllers\Api\ServerAllocationController::class, 'destroy']);
            Route::get('servers/{server}/mounts', [ServerMountController::class, 'index']);
            Route::post('servers/{server}/mounts', [ServerMountController::class, 'store']);
            Route::delete('servers/{server}/mounts/{mount}', [ServerMountController::class, 'destroy']);
            Route::get('servers/{server}/databases', [\App\Http\Controllers\Api\ServerDatabaseController::class, 'index']);
            Route::post('servers/{server}/databases', [\App\Http\Controllers\Api\ServerDatabaseController::class, 'store']);
            Route::post('servers/{server}/databases/{database}/reset-password', [\App\Http\Controllers\Api\ServerDatabaseController::class, 'resetPassword']);
            Route::delete('servers/{server}/databases/{database}', [\App\Http\Controllers\Api\ServerDatabaseController::class, 'destroy']);

            // Databases
            Route::post('databases/test', [\App\Http\Controllers\Api\DatabaseHostController::class, 'testConnection']);
            Route::apiResource('databases', \App\Http\Controllers\Api\DatabaseHostController::class);
            Route::get('packages', [\App\Http\Controllers\Api\PackageController::class, 'index']);
            Route::get('packages/{package}', [\App\Http\Controllers\Api\PackageController::class, 'show']);
            Route::post('packages', [\App\Http\Controllers\Api\PackageController::class, 'store']);
            Route::put('packages/{package}', [\App\Http\Controllers\Api\PackageController::class, 'update']);
            Route::delete('packages/{package}', [\App\Http\Controllers\Api\PackageController::class, 'destroy']);

            Route::get('images', [\App\Http\Controllers\Api\ImageController::class, 'index']);
            Route::get('images/{image}', [\App\Http\Controllers\Api\ImageController::class, 'show']);
            Route::post('packages/{package}/images', [\App\Http\Controllers\Api\ImageController::class, 'store']);
            Route::patch('images/{image}', [\App\Http\Controllers\Api\ImageController::class, 'update']);
            Route::patch('images/{image}/scripts', [\App\Http\Controllers\Api\ImageController::class, 'updateScripts']);
            Route::put('images/{image}/import', [\App\Http\Controllers\Api\ImageController::class, 'replaceImport']);
            Route::get('images/{image}/export', [\App\Http\Controllers\Api\ImageController::class, 'export']);
            Route::delete('images/{image}', [\App\Http\Controllers\Api\ImageController::class, 'destroy']);
            Route::post('images/import', [\App\Http\Controllers\Api\ImageController::class, 'import']);
            Route::get('images/{image}/variables', [\App\Http\Controllers\Api\ImageVariableController::class, 'index']);
            Route::post('images/{image}/variables', [\App\Http\Controllers\Api\ImageVariableController::class, 'store']);
            Route::patch('images/{image}/variables/{variable}', [\App\Http\Controllers\Api\ImageVariableController::class, 'update']);
            Route::delete('images/{image}/variables/{variable}', [\App\Http\Controllers\Api\ImageVariableController::class, 'destroy']);
            Route::get('notifications', [AdminNotificationController::class, 'index']);
            Route::put('notifications/settings', [AdminNotificationController::class, 'updateSettings']);
            Route::post('notifications', [AdminNotificationController::class, 'store']);
            Route::get('notifications/logs', [AdminNotificationController::class, 'logs']);
            Route::post('notifications/logs/retry-last-failed', [AdminNotificationController::class, 'retryLastFailed']);
            Route::post('notifications/logs/{log}/retry', [AdminNotificationController::class, 'retry']);
            Route::get('notifications-test/config', [AdminNotificationTestController::class, 'config']);
            Route::post('notifications-test/send', [AdminNotificationTestController::class, 'send']);
            Route::get('incidents', [AdminIncidentController::class, 'index']);
            Route::post('incidents/{policyEvent}/resolve', [AdminIncidentController::class, 'resolve']);
            Route::post('incidents/{policyEvent}/reopen', [AdminIncidentController::class, 'reopen']);
            Route::post('incidents/clear', [AdminIncidentController::class, 'clear']);
            Route::post('incidents/clear-resolved', [AdminIncidentController::class, 'clearResolved']);
            Route::get('incidents/export.json', [AdminIncidentController::class, 'exportJson']);
            Route::get('incidents/export.html', [AdminIncidentController::class, 'exportHtml']);
            Route::get('connector-lab', [AdminConnectorLabController::class, 'index']);
            Route::get('connector-lab/{node}', [AdminConnectorLabController::class, 'show']);
            Route::get('service-health-checks', [AdminServiceHealthCheckController::class, 'index']);
            Route::get('service-health-checks/latest', [AdminServiceHealthCheckController::class, 'latest']);
            Route::post('service-health-checks/run', [AdminServiceHealthCheckController::class, 'run']);
            Route::get('extensions', [AdminExtensionController::class, 'index']);
            Route::put('extensions/announcer', [AdminExtensionController::class, 'updateAnnouncer']);
            Route::put('extensions/webhooks', [AdminExtensionController::class, 'updateWebhooks']);
            Route::post('extensions/webhooks/test', [AdminExtensionController::class, 'testWebhooks']);
            Route::put('extensions/incidents/settings', [AdminExtensionController::class, 'updateIncidentSettings']);
            Route::post('extensions/incidents', [AdminExtensionController::class, 'storeIncident']);
            Route::post('extensions/incidents/{incident}/toggle', [AdminExtensionController::class, 'toggleIncident']);
            Route::delete('extensions/incidents/{incident}', [AdminExtensionController::class, 'destroyIncident']);
            Route::put('extensions/maintenance/settings', [AdminExtensionController::class, 'updateMaintenanceSettings']);
            Route::post('extensions/maintenance', [AdminExtensionController::class, 'storeMaintenance']);
            Route::post('extensions/maintenance/{maintenance}/toggle-complete', [AdminExtensionController::class, 'toggleMaintenanceComplete']);
            Route::delete('extensions/maintenance/{maintenance}', [AdminExtensionController::class, 'destroyMaintenance']);
            Route::put('extensions/security/settings', [AdminExtensionController::class, 'updateSecuritySettings']);
            Route::post('extensions/security', [AdminExtensionController::class, 'storeSecurity']);
            Route::post('extensions/security/{alert}/toggle', [AdminExtensionController::class, 'toggleSecurity']);
            Route::delete('extensions/security/{alert}', [AdminExtensionController::class, 'destroySecurity']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin Only Routes
        |--------------------------------------------------------------------------
        */
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/status', function () {
                return response()->json([
                    'status' => 'system online',
                    'role' => 'administrator'
                ]);
            });
            
            // Future Admin Management Routes (Servers, Nodes, etc.)
        });
    });
});
