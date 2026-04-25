<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryLog extends Model
{
    protected $fillable = [
        'channel',
        'status',
        'target',
        'template_key',
        'event_key',
        'request_payload',
        'response_payload',
        'error_text',
        'attempted_by_user_id',
        'retried_from_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by_user_id');
    }

    public function retriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retried_from_id');
    }
}
