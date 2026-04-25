<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['name', 'short_name', 'description', 'image_url'];

    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            $shortName = trim((string) ($location->short_name ?? ''));
            $name = trim((string) ($location->name ?? ''));

            if ($shortName === '' && $name !== '') {
                $shortName = $name;
            }

            if ($name === '' && $shortName !== '') {
                $name = $shortName;
            }

            $location->short_name = $shortName !== '' ? $shortName : null;
            $location->name = $name !== '' ? $name : $shortName;
        });
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function databaseHosts(): HasMany
    {
        return $this->hasMany(DatabaseHost::class);
    }

    public function getShortNameAttribute($value): ?string
    {
        return $value ?: $this->attributes['name'] ?? null;
    }

    public function getDatabaseHostsCountAttribute(): int
    {
        if (array_key_exists('database_hosts_count', $this->attributes)) {
            return (int) $this->attributes['database_hosts_count'];
        }

        return (int) $this->databaseHosts()->count();
    }

    public function getConnectorsCountAttribute(): int
    {
        if (array_key_exists('connectors_count', $this->attributes)) {
            return (int) $this->attributes['connectors_count'];
        }

        if (array_key_exists('nodes_count', $this->attributes)) {
            return (int) $this->attributes['nodes_count'];
        }

        return (int) $this->nodes()->count();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['short_name'] = $this->short_name;
        $data['shortName'] = $this->short_name;
        $data['image_url'] = $this->image_url;
        $data['imageUrl'] = $this->image_url;
        $data['database_hosts_count'] = $this->database_hosts_count;
        $data['connectors_count'] = $this->connectors_count;

        return $data;
    }
}
