<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TOTP_PERIOD = 30;
    private const TOTP_DIGITS = 6;
    private const SETUP_TTL_MINUTES = 10;
    private const LOGIN_CHALLENGE_TTL_MINUTES = 10;

    public function createSetup(User $user): array
    {
        $secret = $this->generateSecret();

        Cache::put(
            $this->setupCacheKey($user->id),
            ['secret' => $secret],
            now()->addMinutes(self::SETUP_TTL_MINUTES)
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $this->otpAuthUrl($user, $secret),
        ];
    }

    public function getSetupSecret(User $user): ?string
    {
        $payload = Cache::get($this->setupCacheKey($user->id));

        return is_array($payload) ? ($payload['secret'] ?? null) : null;
    }

    public function clearSetup(User $user): void
    {
        Cache::forget($this->setupCacheKey($user->id));
    }

    public function issueLoginChallenge(User $user, array $context = []): string
    {
        $token = Str::random(64);

        Cache::put(
            $this->loginChallengeCacheKey($token),
            array_merge([
                'user_id' => $user->id,
                'token_name' => 'webapp',
                'provider' => null,
            ], $context),
            now()->addMinutes(self::LOGIN_CHALLENGE_TTL_MINUTES)
        );

        return $token;
    }

    public function getLoginChallenge(string $token): ?array
    {
        $payload = Cache::get($this->loginChallengeCacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public function clearLoginChallenge(string $token): void
    {
        Cache::forget($this->loginChallengeCacheKey($token));
    }

    public function generateSecret(int $length = 32): string
    {
        $secret = '';

        while (strlen($secret) < $length) {
            $secret .= self::BASE32_ALPHABET[random_int(0, strlen(self::BASE32_ALPHABET) - 1)];
        }

        return substr($secret, 0, $length);
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'RA-panel');
        $label = rawurlencode(sprintf('%s:%s', $issuer, $user->email ?: $user->username));

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::TOTP_DIGITS,
            self::TOTP_PERIOD
        );
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\D+/', '', $code);

        if (! is_string($normalized) || strlen($normalized) !== self::TOTP_DIGITS) {
            return false;
        }

        $timeSlice = (int) floor(time() / self::TOTP_PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAtSlice($secret, $timeSlice + $offset), $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret): string
    {
        return $this->codeAtSlice($secret, (int) floor(time() / self::TOTP_PERIOD));
    }

    private function codeAtSlice(string $secret, int $timeSlice): string
    {
        $binarySecret = $this->base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $chunk = substr($hash, $offset, 4);
        $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
        $code = $value % (10 ** self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $cleaned = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($cleaned) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }

    private function setupCacheKey(int $userId): string
    {
        return "2fa:setup:{$userId}";
    }

    private function loginChallengeCacheKey(string $token): string
    {
        return "2fa:login:{$token}";
    }
}
