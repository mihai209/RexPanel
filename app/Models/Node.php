<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Node extends Model
{
    protected $fillable = [
        'name',
        'location_id',
        'fqdn',
        'daemon_port',
        'daemon_token',
        'is_public',
        'maintenance_mode',
        'memory_limit',
        'memory_overallocate',
        'disk_limit',
        'disk_overallocate',
        'daemon_base',
        'daemon_sftp_port',
        'cpu_usage',
        'os',
        'arch',
        'cpu_total',
        'memory_total',
        'memory_used',
        'disk_total',
        'disk_used',
        'last_heartbeat',
        'connector_diagnostics',
        'diagnostics_updated_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'maintenance_mode' => 'boolean',
        'last_heartbeat' => 'datetime',
        'connector_diagnostics' => 'array',
        'diagnostics_updated_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function allocations()
    {
        return $this->hasMany(NodeAllocation::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function mounts()
    {
        return $this->hasMany(Mount::class);
    }

    public function runtimeState(): array
    {
        return [
            'cpu_usage' => (int) ($this->cpu_usage ?? 0),
            'cpu_total' => (int) ($this->cpu_total ?? 0),
            'memory_total' => (int) ($this->memory_total ?? 0),
            'memory_used' => (int) ($this->memory_used ?? 0),
            'disk_total' => (int) ($this->disk_total ?? 0),
            'disk_used' => (int) ($this->disk_used ?? 0),
            'last_heartbeat' => $this->last_heartbeat?->toIso8601String(),
        ];
    }
}
