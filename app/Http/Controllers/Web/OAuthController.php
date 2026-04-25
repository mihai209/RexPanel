<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\OAuthProviderService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function __construct(
        private OAuthProviderService $providers,
        private TwoFactorService $twoFactor,
    )
    {
    }

    public function redirect(string $provider, Request $request): RedirectResponse
    {
        $this->providers->ensureProviderIsSupported($provider);

        if (! $this->providers->providerEnabled($provider) || ! $this->providers->providerConfigured($provider)) {
            return $this->frontendRedirect('/', [
                'oauth_error' => $this->providers->prettyName($provider) . ' login is not enabled.',
            ]);
        }

        if ($linkToken = $request->query('link_token')) {
            $userId = Cache::pull("oauth-link:{$linkToken}");

            if (! $userId || ! User::query()->whereKey($userId)->exists()) {
                return $this->frontendRedirect('/account', [
                    'oauth_error' => 'The account-link request expired. Please try again.',
                ]);
            }

            $request->session()->put('oauth_link_user_id', $userId);
        }

        $this->providers->applyProviderConfig($provider);

        return $this->driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->providers->ensureProviderIsSupported($provider);

        if (! $this->providers->providerEnabled($provider) || ! $this->providers->providerConfigured($provider)) {
            return $this->frontendRedirect('/', [
                'oauth_error' => $this->providers->prettyName($provider) . ' login is not enabled.',
            ]);
        }

        $this->providers->applyProviderConfig($provider);

        try {
            $oauthUser = $this->driver($provider)->user();
        } catch (\Throwable $exception) {
            return $this->frontendRedirect('/', [
                'oauth_error' => 'Authentication failed. Please try again.',
            ]);
        }

        $linkUserId = $request->session()->pull('oauth_link_user_id');

        if ($linkUserId) {
            return $this->handleLinkCallback($provider, $oauthUser, $linkUserId, $request);
        }

        return $this->handleLoginCallback($provider, $oauthUser, $request);
    }

    private function handleLinkCallback(string $provider, object $oauthUser, int $linkUserId, Request $request): RedirectResponse
    {
        $user = User::query()->find($linkUserId);

        if (! $user) {
            return $this->frontendRedirect('/account', [
                'oauth_error' => 'The account you wanted to link could not be found.',
            ]);
        }

        $providerId = (string) $oauthUser->getId();
        $existing = LinkedAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return $this->frontendRedirect('/account', [
                'oauth_error' => 'This social account is already linked to another user.',
            ]);
        }

        LinkedAccount::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'provider_id' => $providerId,
                'provider_email' => $oauthUser->getEmail(),
                'provider_username' => $oauthUser->getNickname() ?: $oauthUser->getName(),
            ]
        );

        $this->syncAvatar($user, $provider, $oauthUser->getAvatar());

        ActivityLog::log($user->id, sprintf('Linked %s account', $this->providers->prettyName($provider)), $request->ip(), 'account.provider_linked', [
            'provider' => $provider,
        ]);

        return $this->frontendRedirect('/account', [
            'oauth_status' => 'linked',
            'provider' => $provider,
        ]);
    }

    private function handleLoginCallback(string $provider, object $oauthUser, Request $request): RedirectResponse
    {
        $providerId = (string) $oauthUser->getId();
        $email = $oauthUser->getEmail() ? strtolower(trim((string) $oauthUser->getEmail())) : null;

        $linkedAccount = LinkedAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        $user = $linkedAccount?->user;
        $autoLinked = false;
        $created = false;

        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();
            $autoLinked = (bool) $user;
        }

        if (! $user && ! $this->providers->registerEnabled($provider)) {
            return $this->frontendRedirect('/', [
                'oauth_error' => $this->providers->prettyName($provider) . ' login is restricted to already linked accounts.',
            ]);
        }

        if (! $user) {
            $user = User::query()->create([
                'username' => $this->generateUniqueUsername($oauthUser->getNickname() ?: $oauthUser->getName() ?: $provider . '_user'),
                'email' => $email ?: "{$provider}_{$providerId}@oauth.local",
                'password' => Hash::make(Str::random(40)),
                'first_name' => $this->firstName($oauthUser->getName()),
                'last_name' => $this->lastName($oauthUser->getName()),
                'avatar_provider' => $provider,
                'avatar_url' => $oauthUser->getAvatar(),
            ]);
            $created = true;
        }

        if ($user->is_suspended) {
            return $this->frontendRedirect('/', [
                'oauth_error' => 'This account is suspended.',
            ]);
        }

        LinkedAccount::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'provider_id' => $providerId,
                'provider_email' => $email,
                'provider_username' => $oauthUser->getNickname() ?: $oauthUser->getName(),
            ]
        );

        $this->syncAvatar($user, $provider, $oauthUser->getAvatar());

        if ($user->two_factor_enabled && $user->two_factor_secret) {
            $challengeToken = $this->twoFactor->issueLoginChallenge($user, [
                'token_name' => "oauth:{$provider}",
                'provider' => $provider,
            ]);

            return $this->frontendRedirect('/', [
                'oauth_status' => '2fa_required',
                'provider' => $provider,
                'two_factor_token' => $challengeToken,
            ]);
        }

        Auth::guard('web')->login($user);

        ActivityLog::log($user->id, sprintf('Authenticated via %s', $this->providers->prettyName($provider)), $request->ip(), 'auth.oauth_login', [
            'provider' => $provider,
        ]);

        $user->tokens()->where('name', "oauth:{$provider}")->delete();
        $token = $user->createToken("oauth:{$provider}")->plainTextToken;

        return $this->frontendRedirect('/', [
            'oauth_token' => $token,
            'oauth_status' => $created ? 'registered' : ($autoLinked ? 'linked_existing' : 'logged_in'),
            'provider' => $provider,
        ]);
    }

    private function driver(string $provider): \Laravel\Socialite\Contracts\Provider
    {
        $driver = Socialite::driver($provider);

        return match ($provider) {
            'google' => $driver->scopes(['openid', 'profile', 'email']),
            'github' => $driver->scopes(['read:user', 'user:email']),
            'discord' => $driver->scopes(['identify', 'email']),
            'reddit' => $driver->scopes(['identity']),
            default => $driver,
        };
    }

    private function frontendRedirect(string $path, array $params): RedirectResponse
    {
        $fragment = http_build_query(array_filter($params, fn ($value) => $value !== null && $value !== ''));

        return redirect($path . ($fragment !== '' ? "#{$fragment}" : ''));
    }

    private function generateUniqueUsername(string $base): string
    {
        $sanitized = Str::of($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->substr(0, 20)
            ->value();

        $sanitized = $sanitized !== '' ? $sanitized : 'user';
        $candidate = $sanitized;
        $counter = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = Str::limit($sanitized, 16, '') . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function syncAvatar(User $user, string $provider, ?string $avatar): void
    {
        if ($avatar === null || $avatar === '') {
            return;
        }

        if ($user->custom_avatar_url) {
            return;
        }

        if ($user->avatar_provider === 'gravatar' || $user->avatar_provider === $provider || $user->avatar_provider === null) {
            $user->forceFill([
                'avatar_provider' => $provider,
                'avatar_url' => $avatar,
            ])->save();
        }
    }

    private function firstName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return str($name)->before(' ')->value();
    }

    private function lastName(?string $name): ?string
    {
        if (! $name || ! str($name)->contains(' ')) {
            return null;
        }

        return str($name)->after(' ')->value();
    }
}
