<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemediationAction extends Model
{
    protected $fillable = [
        'policy_event_id',
        'subject_type',
        'subject_id',
        'action_type',
        'status',
        'cooldown_until',
        'metadata',
    ];

    protected $casts = [
        'policy_event_id' => 'integer',
        'subject_id' => 'integer',
        'cooldown_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function policyEvent(): BelongsTo
    {
        return $this->belongsTo(PolicyEvent::class);
    }
}
