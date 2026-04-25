<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'database_host_id',
        'database',
        'username',
        'remote_id',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function databaseHost(): BelongsTo
    {
        return $this->belongsTo(DatabaseHost::class);
    }
}
