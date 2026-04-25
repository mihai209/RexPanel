<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    public const STATE_ACTIVE = 'active';
    public const STATE_STORED = 'stored';
    public const STATE_EXTERNAL = 'external';

    public const SECTION_ORDER = [
        'branding',
        'panel',
        'feature_toggles',
        'crash_policy',
        'connector_runtime',
        'connector_security',
        'rewards_economy',
        'commerce',
        'auth_providers',
    ];

    public const SECTION_META = [
        'branding' => [
            'title' => 'Branding',
            'description' => 'Panel identity shown in navigation, browser chrome, and connector install branding.',
        ],
        'panel' => [
            'title' => 'Panel & AI',
            'description' => 'Settings that already affect live RA-panel behavior today.',
        ],
        'feature_toggles' => [
            'title' => 'Feature Toggles',
            'description' => 'CPanel-compatible feature flags. Only flags with a live RA-panel runtime or connector output are marked active.',
        ],
        'crash_policy' => [
            'title' => 'Crash Policy',
            'description' => 'Crash-handling defaults forwarded through current connector-facing configuration output.',
        ],
        'connector_runtime' => [
            'title' => 'Connector Runtime',
            'description' => 'Connector runtime controls mirrored into node configuration JSON and provisioning payloads.',
        ],
        'connector_security' => [
            'title' => 'Connector API Security',
            'description' => 'Connector API host, SSL, and proxy settings emitted through the current node configuration surface.',
        ],
        'rewards_economy' => [
            'title' => 'Rewards & Economy',
            'description' => 'CPanel-aligned economy, AFK, claim rewards, policy, abuse, anti-miner, remediation, and health controls backed by live RA-panel runtime services.',
        ],
        'commerce' => [
            'title' => 'Commerce',
            'description' => 'Revenue plans, store deals, redeem codes, forecasting, and provisioning pricing controls backed by live RA-panel commerce services.',
        ],
        'auth_providers' => [
            'title' => 'Auth Providers',
            'description' => 'OAuth providers remain managed on the dedicated admin page.',
            'state' => self::STATE_EXTERNAL,
        ],
    ];

    public const CATALOG = [
        'brandName' => [
            'section' => 'branding',
            'type' => 'string',
            'default' => 'RA-panel',
            'state' => self::STATE_ACTIVE,
            'label' => 'Brand Name',
            'help' => 'Shown in navigation, browser title, and connector install branding.',
            'minLength' => 2,
            'maxLength' => 40,
        ],
        'faviconUrl' => [
            'section' => 'branding',
            'type' => 'string',
            'default' => '/favicon.ico',
            'state' => self::STATE_ACTIVE,
            'label' => 'Favicon URL',
            'help' => 'Used by the SPA shell favicon tag. Accepts an absolute URL or a panel-relative path.',
            'maxLength' => 2048,
        ],
        'aiDailyQuota' => [
            'section' => 'panel',
            'type' => 'integer',
            'default' => '100',
            'state' => self::STATE_ACTIVE,
            'label' => 'AI Daily Quota',
            'help' => 'Default per-user AI quota used by the current admin users flow.',
            'min' => 0,
            'max' => 10000,
        ],
        'featurePolicyEngineEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Policy Engine',
            'help' => 'Enables runtime policy event creation and admin inspection APIs.',
        ],
        'featureAutoRemediationEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Auto-Remediation',
            'help' => 'Enables remediation cooldowns and automatic runtime actions for policy incidents.',
        ],
        'featureStrictAuditEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Strict Audit Logging',
            'help' => 'Requires structured event logging for policy and remediation actions.',
        ],
        'featurePlaybooksAutomationEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Playbooks Automation',
            'help' => 'Enables predefined remediation playbooks through the admin runtime API.',
        ],
        'featureAntiMinerEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner Guard',
            'help' => 'Evaluates connector CPU telemetry, stores anti-miner samples, and raises policy incidents.',
        ],
        'featureAbuseScoreEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Abuse Score Engine',
            'help' => 'Keeps rolling abuse-score windows in persistent storage and raises threshold events.',
        ],
        'featureServiceHealthChecksEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Service Health Checks',
            'help' => 'Stores health-check history and exposes current service-health APIs plus connector runtime output.',
        ],
        'featureAfkRewardsEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'AFK Timer',
            'help' => 'Enables authenticated AFK reward pings and the account rewards surface.',
        ],
        'featureClaimRewardsEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Claim Rewards',
            'help' => 'Enables authenticated reward claims and streak tracking on account surfaces.',
        ],
        'featureWebUploadEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'File Manager Upload',
            'help' => 'Forwarded through connector-facing payloads so upload support can be disabled panel-side.',
        ],
        'featureSftpEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'SFTP Access',
            'help' => 'Forwarded through node configuration and provisioning payloads so SFTP can be disabled panel-side.',
        ],
        'featureRemoteDownloadEnabled' => [
            'section' => 'feature_toggles',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Remote Downloads',
            'help' => 'Forwarded through connector-facing payloads so remote fetch support can be disabled panel-side.',
        ],
        'featureWebUploadMaxMb' => [
            'section' => 'feature_toggles',
            'type' => 'integer',
            'default' => '50',
            'state' => self::STATE_ACTIVE,
            'label' => 'Web Upload Max (MB)',
            'help' => 'Forwarded through connector-facing payloads as the panel upload ceiling.',
            'min' => 1,
            'max' => 2048,
        ],
        'crashDetectionEnabled' => [
            'section' => 'crash_policy',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Crash Detection',
            'help' => 'Included in connector-facing configuration output.',
        ],
        'crashDetectCleanExitAsCrash' => [
            'section' => 'crash_policy',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Treat Clean Exit as Crash',
            'help' => 'Included in connector-facing configuration output.',
        ],
        'crashDetectionCooldownSeconds' => [
            'section' => 'crash_policy',
            'type' => 'integer',
            'default' => '60',
            'state' => self::STATE_ACTIVE,
            'label' => 'Crash Cooldown (s)',
            'help' => 'Included in connector-facing configuration output.',
            'min' => 0,
            'max' => 3600,
        ],
        'connectorConsoleThrottleEnabled' => [
            'section' => 'connector_runtime',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Console Throttling',
            'help' => 'Included in node configuration JSON and install payloads.',
        ],
        'connectorConsoleThrottleLines' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '2000',
            'state' => self::STATE_ACTIVE,
            'label' => 'Throttle Lines',
            'help' => 'Included in node configuration JSON and install payloads.',
            'min' => 10,
            'max' => 100000,
        ],
        'connectorConsoleThrottleIntervalMs' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '100',
            'state' => self::STATE_ACTIVE,
            'label' => 'Throttle Interval (ms)',
            'help' => 'Included in node configuration JSON and install payloads.',
            'min' => 10,
            'max' => 10000,
        ],
        'connectorDiskCheckTtlSeconds' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '10',
            'state' => self::STATE_ACTIVE,
            'label' => 'Disk Usage Cache (s)',
            'help' => 'Included in node configuration JSON and install payloads.',
            'min' => 0,
            'max' => 86400,
        ],
        'connectorTransferDownloadLimit' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Transfer Download Limit (MiB/s)',
            'help' => 'Included in node configuration JSON and install payloads. `0` means unlimited.',
            'min' => 0,
            'max' => 100000,
        ],
        'connectorSftpReadOnly' => [
            'section' => 'connector_runtime',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'SFTP Read-Only',
            'help' => 'Included in node configuration JSON and install payloads.',
        ],
        'connectorRootlessEnabled' => [
            'section' => 'connector_runtime',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Rootless Containers',
            'help' => 'Included in node configuration JSON and install payloads.',
        ],
        'connectorRootlessContainerUid' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Rootless UID',
            'help' => 'Included in node configuration JSON and install payloads.',
            'min' => 0,
            'max' => 65535,
        ],
        'connectorRootlessContainerGid' => [
            'section' => 'connector_runtime',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Rootless GID',
            'help' => 'Included in node configuration JSON and install payloads.',
            'min' => 0,
            'max' => 65535,
        ],
        'connectorApiHost' => [
            'section' => 'connector_security',
            'type' => 'string',
            'default' => '0.0.0.0',
            'state' => self::STATE_ACTIVE,
            'label' => 'API Host',
            'help' => 'Included in node configuration JSON.',
            'maxLength' => 255,
        ],
        'connectorApiSslEnabled' => [
            'section' => 'connector_security',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'API SSL',
            'help' => 'Included in node configuration JSON.',
        ],
        'connectorApiSslCertPath' => [
            'section' => 'connector_security',
            'type' => 'string',
            'default' => '',
            'state' => self::STATE_ACTIVE,
            'label' => 'SSL Cert Path',
            'help' => 'Included in node configuration JSON.',
            'maxLength' => 2048,
        ],
        'connectorApiSslKeyPath' => [
            'section' => 'connector_security',
            'type' => 'string',
            'default' => '',
            'state' => self::STATE_ACTIVE,
            'label' => 'SSL Key Path',
            'help' => 'Included in node configuration JSON.',
            'maxLength' => 2048,
        ],
        'connectorApiTrustedProxies' => [
            'section' => 'connector_security',
            'type' => 'string',
            'default' => '',
            'state' => self::STATE_ACTIVE,
            'label' => 'Trusted Proxies',
            'help' => 'One IP or CIDR per line. Included in node configuration JSON.',
            'maxLength' => 4096,
        ],
        'economyUnit' => [
            'section' => 'rewards_economy',
            'type' => 'string',
            'default' => 'Coins',
            'state' => self::STATE_ACTIVE,
            'label' => 'Economy Unit',
            'help' => 'Used by the account rewards runtime and wallet-facing API responses.',
            'maxLength' => 16,
        ],
        'autoRemediationCooldownSeconds' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '300',
            'state' => self::STATE_ACTIVE,
            'label' => 'Auto-Remediation Cooldown (s)',
            'help' => 'Enforced before the same remediation action can run again for the same subject.',
            'min' => 10,
            'max' => 86400,
        ],
        'antiMinerSuspendScore' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '10',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner Suspend Score',
            'help' => 'Score threshold that triggers anti-miner remediation.',
            'min' => 5,
            'max' => 100,
        ],
        'antiMinerHighCpuPercent' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '95',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner High CPU Percent',
            'help' => 'CPU threshold used when evaluating connector server samples.',
            'min' => 70,
            'max' => 100,
        ],
        'antiMinerHighCpuSamples' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '8',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner High CPU Samples',
            'help' => 'Number of high-CPU samples required before an anti-miner event is created.',
            'min' => 3,
            'max' => 120,
        ],
        'antiMinerDecayMinutes' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '20',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner Score Decay (min)',
            'help' => 'Rolling time window used when counting anti-miner samples.',
            'min' => 1,
            'max' => 720,
        ],
        'antiMinerCooldownSeconds' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '600',
            'state' => self::STATE_ACTIVE,
            'label' => 'Anti-Miner Cooldown (s)',
            'help' => 'Cooldown applied by anti-miner remediation and repeated detections.',
            'min' => 30,
            'max' => 86400,
        ],
        'abuseScoreWindowHours' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '72',
            'state' => self::STATE_ACTIVE,
            'label' => 'Abuse Window (hours)',
            'help' => 'Window length used for persisted abuse-score tracking.',
            'min' => 1,
            'max' => 720,
        ],
        'abuseScoreAlertThreshold' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '80',
            'state' => self::STATE_ACTIVE,
            'label' => 'Abuse Alert Threshold',
            'help' => 'Threshold that creates an abuse-score policy event.',
            'min' => 1,
            'max' => 1000,
        ],
        'serviceHealthCheckIntervalSeconds' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '300',
            'state' => self::STATE_ACTIVE,
            'label' => 'Health Check Interval (s)',
            'help' => 'Included in connector-facing runtime payloads for health-check aware consumers.',
            'min' => 30,
            'max' => 86400,
        ],
        'afkTimerCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '2',
            'state' => self::STATE_ACTIVE,
            'label' => 'AFK Timer Coins',
            'help' => 'Awarded by the authenticated AFK timer runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkTimerCooldownSeconds' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '60',
            'state' => self::STATE_ACTIVE,
            'label' => 'AFK Timer (Seconds)',
            'help' => 'Used by the authenticated AFK timer runtime.',
            'min' => 5,
            'max' => 86400,
        ],
        'afkRewardActivePeriod' => [
            'section' => 'rewards_economy',
            'type' => 'select',
            'default' => 'minute',
            'state' => self::STATE_ACTIVE,
            'label' => 'Claim Rewards Default Period',
            'help' => 'Default claim period used by the account rewards runtime.',
            'options' => [
                ['value' => 'minute', 'label' => 'Minute'],
                ['value' => 'hour', 'label' => 'Hour'],
                ['value' => 'day', 'label' => 'Day'],
                ['value' => 'week', 'label' => 'Week'],
                ['value' => 'month', 'label' => 'Month'],
                ['value' => 'year', 'label' => 'Year'],
            ],
        ],
        'afkRewardMinuteCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '2',
            'state' => self::STATE_ACTIVE,
            'label' => 'Minute Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkRewardHourCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '20',
            'state' => self::STATE_ACTIVE,
            'label' => 'Hour Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkRewardDayCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '120',
            'state' => self::STATE_ACTIVE,
            'label' => 'Day Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkRewardWeekCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '700',
            'state' => self::STATE_ACTIVE,
            'label' => 'Week Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkRewardMonthCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '3000',
            'state' => self::STATE_ACTIVE,
            'label' => 'Month Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'afkRewardYearCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '36000',
            'state' => self::STATE_ACTIVE,
            'label' => 'Year Claim Reward',
            'help' => 'Used by the authenticated claim rewards runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'claimDailyStreakBonusCoins' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '5',
            'state' => self::STATE_ACTIVE,
            'label' => 'Daily Streak Bonus / Day',
            'help' => 'Applied by the authenticated claim rewards streak runtime.',
            'min' => 0,
            'max' => 1000000,
        ],
        'claimDailyStreakMax' => [
            'section' => 'rewards_economy',
            'type' => 'integer',
            'default' => '30',
            'state' => self::STATE_ACTIVE,
            'label' => 'Daily Streak Max',
            'help' => 'Applied by the authenticated claim rewards streak runtime.',
            'min' => 1,
            'max' => 365,
        ],
        'featureUserStoreEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable User Store',
            'help' => 'Exposes the authenticated store overview and purchase flows.',
        ],
        'featureInventoryModeEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Inventory Mode',
            'help' => 'Allows durable per-user inventory resources to be granted and consumed for provisioning fallback.',
        ],
        'featureStoreDealsEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Store Deals',
            'help' => 'Allows admins to publish deals and users to purchase them with wallet balance.',
        ],
        'featureRedeemCodesEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Redeem Codes',
            'help' => 'Allows admins to manage redeem codes and users to claim them.',
        ],
        'featureRevenueModeEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Revenue Mode',
            'help' => 'Allows revenue plan subscription state to override inventory fallback for provisioning limits.',
        ],
        'featureQuotaForecastingEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'true',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Quota Forecasting',
            'help' => 'Exposes forecast report APIs for admin and user store surfaces.',
        ],
        'featureBillingInvoicesEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Billing Invoices',
            'help' => 'Shows invoice preview data on commerce surfaces and enables invoice-aware runtime payloads.',
        ],
        'featureBillingInvoiceWebhookEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Billing Invoice Webhook',
            'help' => 'Dispatches invoice-oriented commerce events through the existing webhook runtime.',
        ],
        'featureScheduledScalingEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Scheduled Scaling',
            'help' => 'Enables scheduled-scaling payloads in the current commerce provisioning runtime.',
        ],
        'featureAdminApiRatePlansEnabled' => [
            'section' => 'commerce',
            'type' => 'boolean',
            'default' => 'false',
            'state' => self::STATE_ACTIVE,
            'label' => 'Enable Admin API Rate Plans',
            'help' => 'Enforces a stored admin API rate plan through dedicated middleware on admin routes.',
        ],
        'commerceCpuPricePerUnit' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '10',
            'state' => self::STATE_ACTIVE,
            'label' => 'CPU Price / Unit',
            'help' => 'Reference coin price used by forecasting for one CPU unit.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceMemoryPricePerMb' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '1',
            'state' => self::STATE_ACTIVE,
            'label' => 'Memory Price / MB',
            'help' => 'Reference coin price used by forecasting for one megabyte of memory.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceDiskPricePerMb' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '1',
            'state' => self::STATE_ACTIVE,
            'label' => 'Disk Price / MB',
            'help' => 'Reference coin price used by forecasting for one megabyte of disk.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceDatabasePricePerUnit' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '250',
            'state' => self::STATE_ACTIVE,
            'label' => 'Database Price / Unit',
            'help' => 'Reference coin price used by forecasting for one database slot.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceAllocationPricePerUnit' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '250',
            'state' => self::STATE_ACTIVE,
            'label' => 'Allocation Price / Unit',
            'help' => 'Reference coin price used by forecasting for one extra allocation slot.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceBackupPricePerUnit' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '150',
            'state' => self::STATE_ACTIVE,
            'label' => 'Backup Price / Unit',
            'help' => 'Reference coin price used by forecasting for one backup slot.',
            'min' => 0,
            'max' => 1000000,
        ],
        'commerceRenewDays' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '30',
            'state' => self::STATE_ACTIVE,
            'label' => 'Renew Window (Days)',
            'help' => 'Default renewal window used for forecast report messaging.',
            'min' => 1,
            'max' => 365,
        ],
        'commerceDeleteGraceDays' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '7',
            'state' => self::STATE_ACTIVE,
            'label' => 'Delete Grace (Days)',
            'help' => 'Default grace period after resource expiry before deletion.',
            'min' => 0,
            'max' => 365,
        ],
        'commerceRevenueDefaultTrialDays' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Revenue Trial Days',
            'help' => 'Default trial period applied when a revenue plan does not specify one.',
            'min' => 0,
            'max' => 365,
        ],
        'commerceRevenueGraceDays' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '3',
            'state' => self::STATE_ACTIVE,
            'label' => 'Revenue Grace Days',
            'help' => 'Grace period applied after a revenue plan expires.',
            'min' => 0,
            'max' => 365,
        ],
        'costBasePerServerMonthly' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Base Cost / Server / Month',
            'help' => 'Feeds recurring burn and renewal forecast math.',
            'min' => 0,
            'max' => 1000000,
        ],
        'costPerGbRamMonthly' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Cost / GB RAM / Month',
            'help' => 'Feeds recurring burn and renewal forecast math.',
            'min' => 0,
            'max' => 1000000,
        ],
        'costPerCpuCoreMonthly' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Cost / CPU Core / Month',
            'help' => 'Feeds recurring burn and renewal forecast math.',
            'min' => 0,
            'max' => 1000000,
        ],
        'costPerGbDiskMonthly' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Cost / GB Disk / Month',
            'help' => 'Feeds recurring burn and renewal forecast math.',
            'min' => 0,
            'max' => 1000000,
        ],
        'storeSwapPerGbCoins' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Store Swap / GB Coins',
            'help' => 'Used in user-create and forecast pricing for swap consumables.',
            'min' => 0,
            'max' => 1000000,
        ],
        'storeImageCoins' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Store Image Coins',
            'help' => 'Used in user-create and forecast pricing for image consumables.',
            'min' => 0,
            'max' => 1000000,
        ],
        'storePackageCoins' => [
            'section' => 'commerce',
            'type' => 'integer',
            'default' => '0',
            'state' => self::STATE_ACTIVE,
            'label' => 'Store Package Coins',
            'help' => 'Used in user-create and forecast pricing for package consumables.',
            'min' => 0,
            'max' => 1000000,
        ],
    ];

    public function validationRules(): array
    {
        $rules = [];

        foreach (self::CATALOG as $key => $definition) {
            $rules[$key] = $this->rulesForDefinition($definition);
        }

        $rules['faviconUrl'][] = function (string $attribute, mixed $value, \Closure $fail): void {
            $normalized = trim((string) $value);

            if ($normalized === '') {
                return;
            }

            if (str_starts_with($normalized, '/')) {
                return;
            }

            if (filter_var($normalized, FILTER_VALIDATE_URL)) {
                return;
            }

            $fail('The favicon URL must be an absolute URL or a panel-relative path.');
        };

        return $rules;
    }

    public function groupedSettingsPayload(): array
    {
        $values = $this->allValues();
        $sections = [];

        foreach (self::SECTION_ORDER as $sectionKey) {
            $meta = self::SECTION_META[$sectionKey];
            $sections[$sectionKey] = array_merge($meta, [
                'key' => $sectionKey,
                'fields' => [],
            ]);
        }

        foreach (self::CATALOG as $key => $definition) {
            $sectionKey = $definition['section'];
            $sections[$sectionKey]['fields'][$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'help' => $definition['help'],
                'type' => $definition['type'],
                'state' => $definition['state'],
                'value' => $values[$key],
                'default' => $this->castOutValue($definition['default'], $definition),
                'min' => $definition['min'] ?? null,
                'max' => $definition['max'] ?? null,
                'options' => $definition['options'] ?? null,
            ];
        }

        $sections['auth_providers']['cta'] = [
            'label' => 'Manage Auth Providers',
            'path' => '/admin/auth-providers',
            'help' => 'OAuth provider credentials and standard login settings stay on the dedicated page.',
        ];

        foreach ($sections as $sectionKey => &$section) {
            $fieldStates = array_values(array_filter(array_map(
                static fn (array $field): string => $field['state'],
                $section['fields']
            )));
            $section['stateCounts'] = [
                self::STATE_ACTIVE => count(array_filter($fieldStates, static fn (string $state): bool => $state === self::STATE_ACTIVE)),
                self::STATE_STORED => count(array_filter($fieldStates, static fn (string $state): bool => $state === self::STATE_STORED)),
                self::STATE_EXTERNAL => count(array_filter($fieldStates, static fn (string $state): bool => $state === self::STATE_EXTERNAL)),
            ];
            $section['state'] = $section['state'] ?? $this->resolveSectionState($fieldStates);
        }
        unset($section);

        return [
            'settings' => $values,
            'tabs' => array_map(
                static fn (string $sectionKey): array => [
                    'key' => $sectionKey,
                    'title' => $sections[$sectionKey]['title'],
                    'state' => $sections[$sectionKey]['state'],
                    'stateCounts' => $sections[$sectionKey]['stateCounts'],
                ],
                self::SECTION_ORDER
            ),
            'sections' => $sections,
            'states' => [
                self::STATE_ACTIVE => 'Active in RA-panel now',
                self::STATE_STORED => 'Stored only',
                self::STATE_EXTERNAL => 'Managed elsewhere',
            ],
        ];
    }

    public function update(array $payload): array
    {
        $current = $this->allValues();

        foreach (self::CATALOG as $key => $definition) {
            $raw = array_key_exists($key, $payload)
                ? $payload[$key]
                : ($definition['type'] === 'boolean' ? false : ($current[$key] ?? $this->castOutValue($definition['default'], $definition)));

            $this->putValue($key, $this->normalizeForStorage($raw, $definition));
        }

        return $this->groupedSettingsPayload();
    }

    public function allValues(): array
    {
        $stored = $this->storedMap();
        $values = [];

        foreach (self::CATALOG as $key => $definition) {
            $values[$key] = $this->castOutValue(
                $stored[$key] ?? $definition['default'],
                $definition
            );
        }

        return $values;
    }

    public function branding(): array
    {
        $values = $this->allValues();

        return [
            'brandName' => $values['brandName'],
            'faviconUrl' => $values['faviconUrl'],
        ];
    }

    public function getValue(string $key, mixed $fallback = null): mixed
    {
        $values = $this->allValues();

        return $values[$key] ?? $fallback;
    }

    public function connectorConfigValues(): array
    {
        $values = $this->allValues();
        $sftpEnabled = (bool) $values['featureSftpEnabled'];

        return [
            'features' => [
                'sftpEnabled' => $sftpEnabled,
                'webUploadEnabled' => (bool) $values['featureWebUploadEnabled'],
                'webUploadMaxMb' => (int) $values['featureWebUploadMaxMb'],
                'remoteDownloadEnabled' => (bool) $values['featureRemoteDownloadEnabled'],
            ],
            'crashPolicy' => [
                'enabled' => (bool) $values['crashDetectionEnabled'],
                'detectCleanExitAsCrash' => (bool) $values['crashDetectCleanExitAsCrash'],
                'cooldownSeconds' => (int) $values['crashDetectionCooldownSeconds'],
            ],
            'api' => [
                'host' => (string) $values['connectorApiHost'],
                'ssl' => [
                    'enabled' => (bool) $values['connectorApiSslEnabled'],
                    'cert' => (string) $values['connectorApiSslCertPath'],
                    'key' => (string) $values['connectorApiSslKeyPath'],
                ],
                'trustedProxies' => $this->parseLines((string) $values['connectorApiTrustedProxies']),
            ],
            'sftp' => [
                'enabled' => $sftpEnabled,
                'readOnly' => (bool) $values['connectorSftpReadOnly'],
            ],
            'docker' => [
                'rootless' => [
                    'enabled' => (bool) $values['connectorRootlessEnabled'],
                    'container_uid' => (int) $values['connectorRootlessContainerUid'],
                    'container_gid' => (int) $values['connectorRootlessContainerGid'],
                ],
            ],
            'system' => [
                'diskCheckTtlSeconds' => (int) $values['connectorDiskCheckTtlSeconds'],
                'healthChecks' => [
                    'enabled' => (bool) $values['featureServiceHealthChecksEnabled'],
                    'intervalSeconds' => (int) $values['serviceHealthCheckIntervalSeconds'],
                ],
            ],
            'monitoring' => $this->policyRuntimeValues(),
            'transfers' => [
                'downloadLimit' => (int) $values['connectorTransferDownloadLimit'],
            ],
            'throttles' => [
                'enabled' => (bool) $values['connectorConsoleThrottleEnabled'],
                'lines' => (int) $values['connectorConsoleThrottleLines'],
                'lineResetInterval' => (int) $values['connectorConsoleThrottleIntervalMs'],
            ],
        ];
    }

    public function rewardsRuntimeValues(): array
    {
        $values = $this->allValues();

        return [
            'economyUnit' => (string) $values['economyUnit'],
            'features' => [
                'claimRewardsEnabled' => (bool) $values['featureClaimRewardsEnabled'],
                'afkRewardsEnabled' => (bool) $values['featureAfkRewardsEnabled'],
            ],
            'afkTimer' => [
                'rewardCoins' => (int) $values['afkTimerCoins'],
                'cooldownSeconds' => (int) $values['afkTimerCooldownSeconds'],
            ],
            'claim' => [
                'defaultPeriod' => (string) $values['afkRewardActivePeriod'],
                'rewards' => [
                    'minute' => (int) $values['afkRewardMinuteCoins'],
                    'hour' => (int) $values['afkRewardHourCoins'],
                    'day' => (int) $values['afkRewardDayCoins'],
                    'week' => (int) $values['afkRewardWeekCoins'],
                    'month' => (int) $values['afkRewardMonthCoins'],
                    'year' => (int) $values['afkRewardYearCoins'],
                ],
                'dailyStreakBonusCoins' => (int) $values['claimDailyStreakBonusCoins'],
                'dailyStreakMax' => (int) $values['claimDailyStreakMax'],
            ],
        ];
    }

    public function commerceRuntimeValues(): array
    {
        $values = $this->allValues();

        return [
            'features' => [
                'userStoreEnabled' => (bool) $values['featureUserStoreEnabled'],
                'inventoryModeEnabled' => (bool) $values['featureInventoryModeEnabled'],
                'storeDealsEnabled' => (bool) $values['featureStoreDealsEnabled'],
                'redeemCodesEnabled' => (bool) $values['featureRedeemCodesEnabled'],
                'revenueModeEnabled' => (bool) $values['featureRevenueModeEnabled'],
                'quotaForecastingEnabled' => (bool) $values['featureQuotaForecastingEnabled'],
                'billingInvoicesEnabled' => (bool) $values['featureBillingInvoicesEnabled'],
                'billingInvoiceWebhookEnabled' => (bool) $values['featureBillingInvoiceWebhookEnabled'],
                'scheduledScalingEnabled' => (bool) $values['featureScheduledScalingEnabled'],
                'adminApiRatePlansEnabled' => (bool) $values['featureAdminApiRatePlansEnabled'],
            ],
            'pricing' => [
                'legacy' => [
                    'cpu' => (int) $values['commerceCpuPricePerUnit'],
                    'memory' => (int) $values['commerceMemoryPricePerMb'],
                    'disk' => (int) $values['commerceDiskPricePerMb'],
                    'databases' => (int) $values['commerceDatabasePricePerUnit'],
                    'allocations' => (int) $values['commerceAllocationPricePerUnit'],
                    'backups' => (int) $values['commerceBackupPricePerUnit'],
                ],
                'unitCosts' => [
                    'ramGb' => (float) $values['commerceMemoryPricePerMb'] * 1024,
                    'cpuCore' => (float) $values['commerceCpuPricePerUnit'] * 100,
                    'diskGb' => (float) $values['commerceDiskPricePerMb'] * 1024,
                    'swapGb' => (float) $values['storeSwapPerGbCoins'],
                    'allocation' => (float) $values['commerceAllocationPricePerUnit'],
                    'image' => (float) $values['storeImageCoins'],
                    'database' => (float) $values['commerceDatabasePricePerUnit'],
                    'package' => (float) $values['storePackageCoins'],
                ],
                'recurring' => [
                    'baseMonthly' => (float) $values['costBasePerServerMonthly'],
                    'perGbRamMonthly' => (float) $values['costPerGbRamMonthly'],
                    'perCpuCoreMonthly' => (float) $values['costPerCpuCoreMonthly'],
                    'perGbDiskMonthly' => (float) $values['costPerGbDiskMonthly'],
                ],
            ],
            'renewDays' => (int) $values['commerceRenewDays'],
            'deleteGraceDays' => (int) $values['commerceDeleteGraceDays'],
            'revenueDefaultTrialDays' => (int) $values['commerceRevenueDefaultTrialDays'],
            'revenueGraceDays' => (int) $values['commerceRevenueGraceDays'],
            'economyUnit' => (string) $values['economyUnit'],
            'scheduledScaling' => [
                'enabled' => (bool) $values['featureScheduledScalingEnabled'],
            ],
            'adminApiRatePlans' => [
                'enabled' => (bool) $values['featureAdminApiRatePlansEnabled'],
            ],
        ];
    }

    public function policyRuntimeValues(): array
    {
        $values = $this->allValues();

        return [
            'features' => [
                'policyEngineEnabled' => (bool) $values['featurePolicyEngineEnabled'],
                'autoRemediationEnabled' => (bool) $values['featureAutoRemediationEnabled'],
                'strictAuditEnabled' => (bool) $values['featureStrictAuditEnabled'],
                'playbooksAutomationEnabled' => (bool) $values['featurePlaybooksAutomationEnabled'],
                'antiMinerEnabled' => (bool) $values['featureAntiMinerEnabled'],
                'abuseScoreEnabled' => (bool) $values['featureAbuseScoreEnabled'],
                'serviceHealthChecksEnabled' => (bool) $values['featureServiceHealthChecksEnabled'],
            ],
            'remediation' => [
                'cooldownSeconds' => (int) $values['autoRemediationCooldownSeconds'],
            ],
            'antiMiner' => [
                'suspendScore' => (int) $values['antiMinerSuspendScore'],
                'highCpuPercent' => (int) $values['antiMinerHighCpuPercent'],
                'highCpuSamples' => (int) $values['antiMinerHighCpuSamples'],
                'decayMinutes' => (int) $values['antiMinerDecayMinutes'],
                'cooldownSeconds' => (int) $values['antiMinerCooldownSeconds'],
            ],
            'abuseScore' => [
                'windowHours' => (int) $values['abuseScoreWindowHours'],
                'alertThreshold' => (int) $values['abuseScoreAlertThreshold'],
            ],
            'healthChecks' => [
                'enabled' => (bool) $values['featureServiceHealthChecksEnabled'],
                'intervalSeconds' => (int) $values['serviceHealthCheckIntervalSeconds'],
            ],
        ];
    }

    private function storedMap(): array
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()
                ->get(['key', 'value'])
                ->pluck('value', 'key')
                ->map(fn ($value) => $value === null ? '' : trim((string) $value))
                ->all();
        } catch (QueryException) {
            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function putValue(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    private function normalizeForStorage(mixed $value, array $definition): string
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer' => (string) $this->normalizeIntegerValue($value, $definition),
            'select' => $this->normalizeSelectValue($value, $definition),
            default => $this->normalizeStringValue($value, $definition),
        };
    }

    private function castOutValue(mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => $this->normalizeIntegerValue($value, $definition),
            'select' => $this->normalizeSelectValue($value, $definition),
            default => $this->normalizeStringLikeValue($value, $definition),
        };
    }

    private function rulesForDefinition(array $definition): array
    {
        return match ($definition['type']) {
            'boolean' => ['sometimes'],
            'integer' => ['sometimes', 'integer'],
            'select' => ['sometimes', 'string'],
            default => ['sometimes', 'nullable', 'string'],
        };
    }

    private function normalizeIntegerValue(mixed $value, array $definition): int
    {
        $default = (int) ($definition['default'] ?? 0);
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        $normalized = $parsed === false ? $default : (int) $parsed;

        return max(
            (int) ($definition['min'] ?? PHP_INT_MIN),
            min((int) ($definition['max'] ?? PHP_INT_MAX), $normalized)
        );
    }

    private function normalizeStringValue(mixed $value, array $definition): string
    {
        return $this->normalizeStringLikeValue($value, $definition);
    }

    private function normalizeStringLikeValue(mixed $value, array $definition): string
    {
        $normalized = trim((string) ($value ?? $definition['default'] ?? ''));
        $maxLength = $definition['maxLength'] ?? null;

        if (is_int($maxLength) && $maxLength > 0) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        if (($definition['minLength'] ?? null) && mb_strlen($normalized) < (int) $definition['minLength']) {
            return (string) ($definition['default'] ?? '');
        }

        return $normalized;
    }

    private function normalizeSelectValue(mixed $value, array $definition): string
    {
        $normalized = trim((string) ($value ?? $definition['default'] ?? ''));
        $allowed = array_map(
            static fn (array $option): string => (string) $option['value'],
            $definition['options'] ?? []
        );

        return in_array($normalized, $allowed, true)
            ? $normalized
            : (string) ($definition['default'] ?? '');
    }

    private function resolveSectionState(array $fieldStates): string
    {
        if ($fieldStates === []) {
            return self::STATE_EXTERNAL;
        }

        if (in_array(self::STATE_ACTIVE, $fieldStates, true)) {
            return self::STATE_ACTIVE;
        }

        if (in_array(self::STATE_STORED, $fieldStates, true)) {
            return self::STATE_STORED;
        }

        return self::STATE_EXTERNAL;
    }

    private function parseLines(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/', $value) ?: []
        )));
    }
}
