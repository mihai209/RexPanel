<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAfkState extends Model
{
    protected $fillable = [
        'user_id',
        'last_seen_at',
        'last_timer_reward_at',
        'next_payout_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_timer_reward_at' => 'datetime',
        'next_payout_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
