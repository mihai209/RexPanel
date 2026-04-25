<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbuseScoreWindow extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'score',
        'window_started_at',
        'window_ends_at',
        'last_triggered_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'score' => 'integer',
        'window_started_at' => 'datetime',
        'window_ends_at' => 'datetime',
        'last_triggered_at' => 'datetime',
    ];
}
