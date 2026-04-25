<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'type',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to log an activity for a user.
     */
    public static function log(int $userId, string $action, ?string $ip = null, string $type = 'generic', ?array $metadata = null): self
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'type' => $type,
            'metadata' => $metadata,
            'ip_address' => $ip,
        ]);
    }
}
