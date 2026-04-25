<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Server extends Model
{
    protected $fillable = [
        'uuid',
        'route_id',
        'name',
        'description',
        'external_id',
        'node_id',
        'allocation_id',
        'user_id',
        'image_id',
        'cpu',
        'memory',
        'disk',
        'swap',
        'io',
        'threads',
        'oom_disabled',
        'database_limit',
        'allocation_limit',
        'backup_limit',
        'status',
        'docker_image',
        'startup',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
        'oom_disabled' => 'boolean',
        'database_limit' => 'integer',
        'allocation_limit' => 'integer',
        'backup_limit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }

            if (! $model->route_id) {
                do {
                    $candidate = Str::lower(Str::random(8));
                } while (static::query()->where('route_id', $candidate)->exists());

                $model->route_id = $candidate;
            }
        });
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(NodeAllocation::class, 'allocation_id');
    }

    public function primaryAllocation(): BelongsTo
    {
        return $this->belongsTo(NodeAllocation::class, 'allocation_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(NodeAllocation::class);
    }

    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    public function mounts(): BelongsToMany
    {
        return $this->belongsToMany(Mount::class, 'server_mounts')
            ->withPivot(['id', 'read_only'])
            ->withTimestamps();
    }

    public function runtimeState(): HasOne
    {
        return $this->hasOne(ServerRuntimeState::class);
    }
}
