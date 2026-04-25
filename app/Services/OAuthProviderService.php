<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;

class OAuthProviderService
{
    public const PROVIDERS = [
        'discord' => ['name' => 'Discord', 'icon_key' => 'discord', 'brand_variant' => 'discord'],
        'google' => ['name' => 'Google', 'icon_key' => 'google', 'brand_variant' => 'google'],
        'github' => ['name' => 'GitHub', 'icon_key' => 'github', 'brand_variant' => 'github'],
        'reddit' => ['name' => 'Reddit', 'icon_key' => 'reddit', 'brand_variant' => 'reddit'],
    ];

    public function listPublicProviders(): array
    {
        return [
            'standard_enabled' => $this->standardAuthEnabled(),
            'providers' => collect(self::PROVIDERS)
                ->map(fn (array $meta, string $provider) => [
                    'key' => $provider,
                    'name' => $meta['name'],
                    'icon_key' => $meta['icon_key'],
                    'brand_variant' => $meta['brand_variant'],
                    'enabled' => $this->providerEnabled($provider),
                    'configured' => $this->providerConfigured($provider),
                    'login_url' => route('oauth.redirect', ['provider' => $provider]),
                ])
                ->values()
                ->all(),
        ];
    }

    public function listAdminProviders(): array
    {
        return [
            'standard_enabled' => $this->standardAuthEnabled(),
            'providers' => collect(self::PROVIDERS)
                ->mapWithKeys(fn (array $meta, string $provider) => [
                    $provider => [
                        'name' => $meta['name'],
                        'icon_key' => $meta['icon_key'],
                        'brand_variant' => $meta['brand_variant'],
                        'enabled' => $this->providerEnabled($provider),
                        'register_enabled' => $this->registerEnabled($provider),
                        'configured' => $this->providerConfigured($provider),
                        'client_id_configured' => $this->getValue($this->settingKey($provider, 'client_id')) !== '',
                        'client_secret_configured' => $this->getValue($this->settingKey($provider, 'client_secret')) !== '',
                        'callback_url' => route('oauth.callback', ['provider' => $provider]),
                    ],
                ])
                ->all(),
        ];
    }

    public function updateAdminProviders(array $payload): array
    {
        $this->putValue('auth_standard_enabled', ! empty($payload['standard_enabled']) ? 'true' : 'false');

        foreach (self::PROVIDERS as $provider => $meta) {
            $config = $payload['providers'][$provider] ?? [];
            $this->putValue($this->settingKey($provider, 'enabled'), ! empty($config['enabled']) ? 'true' : 'false');
            $this->putValue($this->settingKey($provider, 'register_enabled'), ! empty($config['register_enabled']) ? 'true' : 'false');
            if (array_key_exists('client_id', $config) && trim((string) $config['client_id']) !== '') {
                $this->putValue($this->settingKey($provider, 'client_id'), $config['client_id']);
            }
            if (array_key_exists('client_secret', $config) && trim((string) $config['client_secret']) !== '') {
                $this->putValue($this->settingKey($provider, 'client_secret'), $config['client_secret']);
            }
        }

        return $this->listAdminProviders();
    }

    public function standardAuthEnabled(): bool
    {
        return $this->getBoolean('auth_standard_enabled', true);
    }

    public function providerEnabled(string $provider): bool
    {
        return $this->getBoolean($this->settingKey($provider, 'enabled'), false);
    }

    public function registerEnabled(string $provider): bool
    {
        return $this->getBoolean($this->settingKey($provider, 'register_enabled'), true);
    }

    public function providerConfigured(string $provider): bool
    {
        return $this->getValue($this->settingKey($provider, 'client_id')) !== ''
            && $this->getValue($this->settingKey($provider, 'client_secret')) !== '';
    }

    public function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);
    }

    public function applyProviderConfig(string $provider): void
    {
        $this->ensureProviderIsSupported($provider);

        Config::set("services.{$provider}", [
            'client_id' => $this->getValue($this->settingKey($provider, 'client_id')),
            'client_secret' => $this->getValue($this->settingKey($provider, 'client_secret')),
            'redirect' => route('oauth.callback', ['provider' => $provider]),
        ]);
    }

    public function prettyName(string $provider): string
    {
        return self::PROVIDERS[$provider]['name'] ?? ucfirst($provider);
    }

    private function settingKey(string $provider, string $suffix): string
    {
        return "auth_{$provider}_{$suffix}";
    }

    private function getBoolean(string $key, bool $default): bool
    {
        return filter_var($this->getValue($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function getValue(string $key, ?string $default = ''): string
    {
        $setting = SystemSetting::query()->find($key);
        if ($setting && $setting->value !== null) {
            return trim((string) $setting->value);
        }

        $envKey = strtoupper($key);
        $envValue = env($envKey);

        if ($envValue !== null) {
            return trim((string) $envValue);
        }

        return (string) $default;
    }

    private function putValue(string $key, ?string $value): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => trim((string) $value)]
        );
    }
}
