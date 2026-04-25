<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mount extends Model
{
    protected $fillable = [
        'name',
        'description',
        'source_path',
        'target_path',
        'read_only',
        'node_id',
    ];

    protected $casts = [
        'read_only' => 'boolean',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_mounts')
            ->withPivot(['id', 'read_only'])
            ->withTimestamps();
    }

    public function serverMounts(): HasMany
    {
        return $this->hasMany(ServerMount::class);
    }
}
