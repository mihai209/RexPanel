<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'package_id',
        'name',
        'description',
        'author',
        'update_url',
        'is_public',
        'docker_image',
        'docker_images',
        'features',
        'file_denylist',
        'startup',
        'config_files',
        'config_startup',
        'config_logs',
        'config_stop',
        'script_install',
        'script_entry',
        'script_container',
        'variables',
        'source_path',
        'source_hash',
        'imported_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'docker_images' => 'array',
        'features' => 'array',
        'file_denylist' => 'array',
        'variables' => 'array',
        'imported_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function imageVariables(): HasMany
    {
        return $this->hasMany(ImageVariable::class);
    }
}
