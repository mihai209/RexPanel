<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CaptchaChallengeService
{
    private const TTL_MINUTES = 10;
    private const CACHE_KEY_PREFIX = 'auth:captcha:';
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function issue(): array
    {
        $text = $this->generateText();
        $token = Str::random(48);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(
            $this->cacheKey($token),
            ['text' => strtolower($text)],
            $expiresAt
        );

        return [
            'token' => $token,
            'svg' => $this->renderSvg($text),
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    public function validate(?string $token, ?string $answer, bool $consumeOnSuccess = true): bool
    {
        $normalizedToken = trim((string) $token);
        $normalizedAnswer = strtolower(trim((string) $answer));

        if ($normalizedToken === '' || $normalizedAnswer === '') {
            return false;
        }

        $payload = Cache::get($this->cacheKey($normalizedToken));

        if (! is_array($payload) || ! isset($payload['text'])) {
            return false;
        }

        $valid = hash_equals((string) $payload['text'], $normalizedAnswer);

        if ($valid && $consumeOnSuccess) {
            Cache::forget($this->cacheKey($normalizedToken));
        }

        return $valid;
    }

    private function generateText(int $length = 6): string
    {
        $value = '';
        $maxIndex = strlen(self::ALPHABET) - 1;

        while (strlen($value) < $length) {
            $value .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $value;
    }

    private function renderSvg(string $text): string
    {
        $width = 180;
        $height = 52;
        $chars = str_split($text);
        $charX = 18;
        $pieces = [
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="captcha">', $width, $height, $width, $height),
            '<rect width="100%" height="100%" rx="10" fill="#151a22"/>',
        ];

        for ($line = 0; $line < 6; $line++) {
            $x1 = random_int(0, $width - 20);
            $y1 = random_int(4, $height - 4);
            $x2 = random_int(0, $width - 20);
            $y2 = random_int(4, $height - 4);
            $pieces[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1.2" opacity="0.35"/>',
                $x1,
                $y1,
                $x2,
                $y2,
                $line % 2 === 0 ? '#3dd9b4' : '#8bb2ff'
            );
        }

        foreach ($chars as $index => $char) {
            $x = $charX + ($index * 24) + random_int(-2, 2);
            $y = random_int(30, 40);
            $rotation = random_int(-20, 20);
            $fontSize = random_int(24, 29);
            $fill = $index % 2 === 0 ? '#f3f4f6' : '#7ce7bf';

            $pieces[] = sprintf(
                '<text x="%d" y="%d" font-family="monospace" font-size="%d" font-weight="700" fill="%s" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                $fontSize,
                $fill,
                $rotation,
                $x,
                $y,
                $char
            );
        }

        for ($dot = 0; $dot < 18; $dot++) {
            $pieces[] = sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="%s" opacity="0.28"/>',
                random_int(6, $width - 6),
                random_int(6, $height - 6),
                random_int(1, 2),
                $dot % 2 === 0 ? '#ffcf6e' : '#8bb2ff'
            );
        }

        $pieces[] = '</svg>';

        return implode('', $pieces);
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_KEY_PREFIX . $token;
    }
}
