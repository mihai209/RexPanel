<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['avatar_url'];

    protected $fillable = [
        'username',
        'email',
        'password',
        'is_admin',
        'is_suspended',
        'coins',
        'theme',
        'custom_avatar_url',
        'first_name',
        'last_name',
        'avatar_provider',
        'avatar_url',
        'two_factor_enabled',
        'two_factor_secret',
        'ai_daily_quota_override',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->custom_avatar_url
                ?? $this->attributes['avatar_url']
                ?? 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->email))) . '?s=160&d=identicon',
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'username' => 'string',
            'email' => 'string',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'coins' => 'integer',
            'two_factor_enabled' => 'boolean',
            'ai_daily_quota_override' => 'integer',
        ];
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function rewardState(): HasOne
    {
        return $this->hasOne(AccountRewardState::class);
    }

    public function afkState(): HasOne
    {
        return $this->hasOne(AccountAfkState::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function browserSubscriptions(): HasMany
    {
        return $this->hasMany(UserBrowserSubscription::class);
    }
}
