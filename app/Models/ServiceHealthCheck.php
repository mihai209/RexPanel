<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceHealthCheck extends Model
{
    protected $fillable = [
        'node_id',
        'server_id',
        'status',
        'response_time_ms',
        'checked_at',
        'metadata',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'server_id' => 'integer',
        'response_time_ms' => 'integer',
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
