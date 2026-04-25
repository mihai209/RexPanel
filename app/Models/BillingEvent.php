<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'source_type',
        'source_id',
        'coins_delta',
        'wallet_before',
        'wallet_after',
        'resource_delta',
        'details',
        'created_at',
    ];

    protected $casts = [
        'coins_delta' => 'integer',
        'wallet_before' => 'integer',
        'wallet_after' => 'integer',
        'resource_delta' => 'array',
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
