<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Server;
class ServerProvisioningService
{
    private const PANEL_TYPE = 'rex';

    public function __construct(
        private SystemSettingsService $settings,
        private ConnectorQueueService $queue,
        private ServerMountService $mounts,
        private ServerStartupService $startup,
        private CommerceProvisioningService $commerce,
    )
    {
    }

    public function dispatchInstall(Server $server, bool $reinstall = false, ?bool $startAfterInstall = null): void
    {
        $server->loadMissing(['image.imageVariables', 'primaryAllocation', 'allocations', 'user']);

        $this->queue->publish([
            'node_id' => $server->node_id,
            'panel_type' => self::PANEL_TYPE,
            'connector_id' => $server->node_id,
            'payload' => $this->buildInstallPayload($server, $reinstall, $startAfterInstall),
        ]);
    }

    public function dispatchResourceLimits(Server $server): void
    {
        $this->queue->publish([
            'node_id' => $server->node_id,
            'panel_type' => self::PANEL_TYPE,
            'connector_id' => $server->node_id,
            'payload' => [
                'type' => 'apply_resource_limits',
                'serverId' => $server->id,
                'memory' => $server->memory,
                'cpu' => $server->cpu,
                'swapLimit' => $server->swap,
                'ioWeight' => $server->io,
                'requestId' => 'panel-' . uniqid(),
            ],
        ]);
    }

    public function dispatchDelete(Server $server): void
    {
        $this->queue->publish([
            'node_id' => $server->node_id,
            'panel_type' => self::PANEL_TYPE,
            'connector_id' => $server->node_id,
            'payload' => [
                'type' => 'delete_server',
                'serverId' => $server->id,
            ],
        ]);
    }

    public function dispatchPowerSignal(Server $server, string $signal): void
    {
        $this->queue->publish([
            'node_id' => $server->node_id,
            'panel_type' => self::PANEL_TYPE,
            'connector_id' => $server->node_id,
            'payload' => [
                'type' => 'power_signal',
                'serverId' => $server->id,
                'signal' => $signal,
                'requestId' => 'panel-' . uniqid(),
            ],
        ]);
    }

    public function dispatchConsoleCommand(Server $server, string $command): void
    {
        $this->queue->publish([
            'node_id' => $server->node_id,
            'panel_type' => self::PANEL_TYPE,
            'connector_id' => $server->node_id,
            'payload' => [
                'type' => 'send_console_command',
                'serverId' => $server->id,
                'command' => $command,
                'requestId' => 'panel-' . uniqid(),
            ],
        ]);
    }

    public function buildInstallPayload(Server $server, bool $reinstall = false, ?bool $startAfterInstall = null): array
    {
        $server->loadMissing(['image.imageVariables', 'primaryAllocation', 'allocations']);

        if (! $server->primaryAllocation) {
            throw new \RuntimeException('Server allocation is missing.');
        }

        if (! $server->image) {
            throw new \RuntimeException('Server image is missing.');
        }

        $image = $server->image;
        $allocation = $server->primaryAllocation;
        $configFiles = $this->decodeJsonField($image->config_files);
        $runtime = $this->settings->connectorConfigValues();
        $commerce = $server->user ? $this->commerce->effectiveLimits($server->user) : [
            'source' => 'none',
            'limits' => [],
        ];
        $startupPreview = $this->startup->buildPreview($server, $image, $allocation);

        return [
            'type' => 'install_server',
            'serverId' => $server->id,
            'reinstall' => $reinstall,
            'config' => [
                'image' => $server->docker_image ?: $image->docker_image,
                'memory' => (int) $server->memory,
                'cpu' => (int) $server->cpu,
                'disk' => (int) $server->disk,
                'swapLimit' => (int) $server->swap,
                'ioWeight' => (int) $server->io,
                'threads' => $server->threads,
                'pidsLimit' => 0,
                'oomKillDisable' => (bool) $server->oom_disabled,
                'oomScoreAdj' => 0,
                'env' => $startupPreview['environment'],
                'startup' => $startupPreview['resolved'],
                'startupMode' => 'environment',
                'eggConfig' => [
                    'files' => $configFiles,
                    'startup' => $this->decodeJsonField($image->config_startup),
                    'logs' => $this->decodeJsonField($image->config_logs),
                    'stop' => $image->config_stop,
                ],
                'eggScripts' => [
                    'installation' => [
                        'script' => $image->script_install,
                        'container' => $image->script_container,
                        'entrypoint' => $image->script_entry,
                    ],
                ],
                'installation' => [
                    'script' => $image->script_install,
                    'container' => $image->script_container,
                    'entrypoint' => $image->script_entry,
                ],
                'skipInstallationScript' => blank($image->script_install),
                'configFiles' => $configFiles,
                'brandName' => $this->settings->getValue('brandName', 'RA-panel'),
                'startAfterInstall' => $startAfterInstall ?? (strtolower((string) $server->status) === 'running'),
                'ports' => $server->allocations
                    ->sortBy([
                        fn ($left, $right) => ((int) $left->id === (int) $server->allocation_id ? -1 : 1)
                            <=> ((int) $right->id === (int) $server->allocation_id ? -1 : 1),
                    ])
                    ->values()
                    ->map(fn ($entry) => [
                        'container' => (int) (((int) $entry->id === (int) $server->allocation_id)
                            ? ($startupPreview['environment']['SERVER_PORT'] ?? $entry->port)
                            : $entry->port),
                        'host' => (int) $entry->port,
                        'ip' => $entry->ip,
                        'protocol' => 'tcp',
                        'is_primary' => (int) $entry->id === (int) $server->allocation_id,
                        'notes' => $entry->notes,
                    ])
                    ->all(),
                'mounts' => $this->mounts->installConfig($server),
                'featureFlags' => $runtime['features'],
                'featureLimits' => [
                    'databases' => $server->database_limit,
                    'allocations' => $server->allocation_limit,
                    'backups' => $server->backup_limit,
                ],
                'commerce' => $commerce,
                'crashPolicy' => $runtime['crashPolicy'],
                'connectorRuntime' => [
                    'throttles' => $runtime['throttles'],
                    'system' => $runtime['system'],
                    'monitoring' => $runtime['monitoring'] ?? [],
                    'transfers' => $runtime['transfers'],
                    'sftp' => $runtime['sftp'],
                    'api' => $runtime['api'],
                    'docker' => $runtime['docker'],
                ],
            ],
        ];
    }

    private function decodeJsonField(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
