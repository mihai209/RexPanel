<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseHost extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'database',
        'location_id',
        'max_databases',
        'type',
    ];

    protected $hidden = [
        'password',
    ];

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['locationId'] = (int) $this->location_id;

        if ($this->relationLoaded('location') && $this->location) {
            $data['location'] = $this->location->toArray();
        }

        return $data;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }
}
