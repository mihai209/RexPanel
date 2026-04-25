<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_id',
        'name',
        'description',
        'env_variable',
        'default_value',
        'user_viewable',
        'user_editable',
        'rules',
        'field_type',
        'sort_order',
    ];

    protected $casts = [
        'user_viewable' => 'boolean',
        'user_editable' => 'boolean',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
