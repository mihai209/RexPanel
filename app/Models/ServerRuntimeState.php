<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerRuntimeState extends Model
{
    protected $fillable = [
        'server_id',
        'power_state',
        'install_state',
        'install_message',
        'resource_snapshot',
        'console_output',
        'install_output',
        'last_resource_at',
        'last_console_at',
        'last_install_output_at',
    ];

    protected $casts = [
        'resource_snapshot' => 'array',
        'last_resource_at' => 'datetime',
        'last_console_at' => 'datetime',
        'last_install_output_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
