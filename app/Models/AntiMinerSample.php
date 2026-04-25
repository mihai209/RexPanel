<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntiMinerSample extends Model
{
    protected $fillable = [
        'node_id',
        'server_id',
        'user_id',
        'cpu_percent',
        'sampled_at',
        'resulting_score_delta',
        'metadata',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'server_id' => 'integer',
        'user_id' => 'integer',
        'cpu_percent' => 'integer',
        'sampled_at' => 'datetime',
        'resulting_score_delta' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
