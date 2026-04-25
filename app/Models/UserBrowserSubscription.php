<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBrowserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'keys',
        'user_agent',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'keys' => 'array',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
