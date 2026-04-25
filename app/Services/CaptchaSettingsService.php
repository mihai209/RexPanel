<?php

namespace App\Services;

use App\Models\SystemSetting;

class CaptchaSettingsService
{
    private const STATUS_KEY = 'captchastatus';

    public function isEnabled(): bool
    {
        return strtolower($this->rawValue()) === 'on';
    }

    public function payload(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'status' => $this->rawValue(),
        ];
    }

    public function update(bool $enabled): array
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::STATUS_KEY],
            ['value' => $enabled ? 'on' : 'off']
        );

        return $this->payload();
    }

    private function rawValue(): string
    {
        return strtolower(trim((string) (SystemSetting::query()->find(self::STATUS_KEY)?->value ?? 'off')));
    }
}
