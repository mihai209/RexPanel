<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'policy_key',
        'severity',
        'score_delta',
        'reason',
        'title',
        'status',
        'resolved_at',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'user_id' => 'integer',
        'score_delta' => 'integer',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remediationActions(): HasMany
    {
        return $this->hasMany(RemediationAction::class);
    }
}
