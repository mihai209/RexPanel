<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRewardState extends Model
{
    protected $fillable = [
        'user_id',
        'selected_period',
        'daily_streak',
        'reward_accrual_disabled',
        'last_activity_at',
        'last_daily_claim_at',
        'minute_claimed_at',
        'hour_claimed_at',
        'day_claimed_at',
        'week_claimed_at',
        'month_claimed_at',
        'year_claimed_at',
    ];

    protected $casts = [
        'daily_streak' => 'integer',
        'reward_accrual_disabled' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_daily_claim_at' => 'datetime',
        'minute_claimed_at' => 'datetime',
        'hour_claimed_at' => 'datetime',
        'day_claimed_at' => 'datetime',
        'week_claimed_at' => 'datetime',
        'month_claimed_at' => 'datetime',
        'year_claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
