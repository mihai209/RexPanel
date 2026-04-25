<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'image_url',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function servers(): HasManyThrough
    {
        return $this->hasManyThrough(Server::class, Image::class, 'package_id', 'image_id', 'id', 'id');
    }
}
