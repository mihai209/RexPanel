<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMount extends Model
{
    protected $fillable = [
        'server_id',
        'mount_id',
        'read_only',
    ];

    protected $casts = [
        'read_only' => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function mount(): BelongsTo
    {
        return $this->belongsTo(Mount::class);
    }
}
