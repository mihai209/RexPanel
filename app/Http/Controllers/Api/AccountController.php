<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LinkedAccount;
use App\Services\OAuthProviderService;
use App\Services\RewardsRuntimeService;
use App\Services\SystemSettingsService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function __construct(
        private OAuthProviderService $providers,
        private TwoFactorService $twoFactor,
        private SystemSettingsService $settings,
        private RewardsRuntimeService $rewards,
    )
    {
    }

    /**
     * Update account details (username, email) — requires current password.
     */
    public function updateDetails(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'email'    => ['required', 'email', 'unique:users,email,' . $user->id],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password was incorrect.',
            ], 422);
        }

        $user->update([
            'email'    => $request->email,
            'username' => $request->username,
        ]);

        ActivityLog::log($user->id, 'Account details updated', $request->ip(), 'account.details_updated');

        return response()->json([
            'message' => 'Details updated successfully.',
            'user'    => $this->serializeAccountUser($user->fresh()),
        ]);
    }

    /**
     * Update user theme preference.
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $request->validate([
            'theme' => ['required', 'string'],
        ]);

        $request->user()->update([
            'theme' => $request->theme,
        ]);

        return response()->json([
            'message' => 'Theme updated successfully.',
            'user'    => $request->user(),
        ]);
    }

    /**
     * Change password — requires current password, then revokes all tokens.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::log($user->id, 'Password changed', $request->ip(), 'account.password_changed');

        // Revoke all tokens → forces re-login
        $user->tokens()->delete();

        return response()->json(['message' => 'Password changed successfully. Please log in again.']);
    }

    /**
     * Get paginated activity log for the authenticated user.
     */
    public function getActivity(Request $request): JsonResponse
    {
        $logs = ActivityLog::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($logs);
    }

    /**
     * Delete all activity logs for the authenticated user.
     */
    public function clearActivity(Request $request): JsonResponse
    {
        ActivityLog::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Activity log cleared.']);
    }

    public function linkedAccounts(Request $request): JsonResponse
    {
        $request->user()->load('linkedAccounts');
        $linked = $request->user()->linkedAccounts->keyBy('provider');
        $providers = collect($this->providers->listPublicProviders()['providers'])
            ->map(function (array $provider) use ($linked) {
                $link = $linked->get($provider['key']);

                return [
                    ...$provider,
                    'linked' => (bool) $link,
                    'can_link' => ! $link && $provider['enabled'] && $provider['configured'],
                    'can_unlink' => (bool) $link,
                    'link' => $link ? [
                        'provider' => $link->provider,
                        'provider_email' => $link->provider_email,
                        'provider_username' => $link->provider_username,
                    ] : null,
                ];
            })
            ->sortByDesc(fn (array $provider) => $provider['linked'])
            ->values()
            ->all();

        return response()->json(['providers' => $providers]);
    }

    public function createLinkedAccountRedirect(string $provider, Request $request): JsonResponse
    {
        $this->providers->ensureProviderIsSupported($provider);

        if (! $this->providers->providerEnabled($provider) || ! $this->providers->providerConfigured($provider)) {
            return response()->json([
                'message' => 'This sign-in provider is not available right now.',
            ], 422);
        }

        $token = Str::random(64);
        Cache::put("oauth-link:{$token}", $request->user()->id, now()->addMinutes(10));

        return response()->json([
            'redirect_url' => route('oauth.redirect', ['provider' => $provider, 'link_token' => $token]),
        ]);
    }

    public function unlinkLinkedAccount(string $provider, Request $request): JsonResponse
    {
        $this->providers->ensureProviderIsSupported($provider);

        $user = $request->user();
        $link = LinkedAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (! $link) {
            return response()->json([
                'message' => 'This provider is not linked to your account.',
            ], 404);
        }

        if ($user->linkedAccounts()->count() <= 1) {
            return response()->json([
                'message' => 'You must keep at least one linked account until password reset flows are implemented.',
            ], 422);
        }

        $link->delete();
        ActivityLog::log($user->id, sprintf('Unlinked %s account', $this->providers->prettyName($provider)), $request->ip(), 'account.provider_unlinked', [
            'provider' => $provider,
        ]);

        return response()->json([
            'message' => 'Linked account removed.',
        ]);
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $setup = $this->twoFactor->createSetup($user);

        return response()->json([
            'secret' => $setup['secret'],
            'otpauth_url' => $setup['otpauth_url'],
        ]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $secret = $this->twoFactor->getSetupSecret($user);

        if (! $secret) {
            return response()->json([
                'message' => 'Two-factor setup expired. Start the setup again.',
            ], 422);
        }

        if (! $this->twoFactor->verifyCode($secret, $request->input('code'))) {
            return response()->json([
                'message' => 'Invalid 6-digit authentication code.',
            ], 422);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ])->save();

        $this->twoFactor->clearSetup($user);
        ActivityLog::log($user->id, 'Two-factor authentication enabled', $request->ip(), 'account.2fa_enabled');

        return response()->json([
            'message' => 'Two-factor authentication enabled.',
            'user' => $this->serializeAccountUser($user->fresh()),
        ]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ])->save();

        $this->twoFactor->clearSetup($user);
        ActivityLog::log($user->id, 'Two-factor authentication disabled', $request->ip(), 'account.2fa_disabled');

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
            'user' => $this->serializeAccountUser($user->fresh()),
        ]);
    }

    public function rewards(Request $request): JsonResponse
    {
        return response()->json($this->rewards->rewardsPayload($request->user()->fresh()));
    }

    public function claimReward(Request $request): JsonResponse
    {
        $runtime = $this->settings->rewardsRuntimeValues();

        if (! $runtime['features']['claimRewardsEnabled']) {
            return response()->json([
                'message' => 'Claim rewards are disabled by an administrator.',
            ], 403);
        }

        $validated = $request->validate([
            'period' => ['nullable', 'string'],
        ]);
        $response = $this->rewards->claimReward($request->user()->fresh(), (string) ($validated['period'] ?? $runtime['claim']['defaultPeriod']), $request->ip());

        return response()->json($response['body'], $response['status']);
    }

    public function afk(Request $request): JsonResponse
    {
        $runtime = $this->settings->rewardsRuntimeValues();

        if (! $runtime['features']['afkRewardsEnabled']) {
            return response()->json([
                'message' => 'AFK rewards are disabled by an administrator.',
            ], 403);
        }

        return response()->json($this->rewards->afkPayload($request->user()->fresh()));
    }

    public function afkPing(Request $request): JsonResponse
    {
        $runtime = $this->settings->rewardsRuntimeValues();

        if (! $runtime['features']['afkRewardsEnabled']) {
            return response()->json([
                'message' => 'AFK rewards are disabled by an administrator.',
            ], 403);
        }

        return response()->json($this->rewards->pingAfk($request->user()->fresh(), $request->ip()));
    }

    private function serializeAccountUser($user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'isAdmin' => (bool) $user->is_admin,
            'first_name' => $user->first_name,
            'firstName' => $user->first_name,
            'last_name' => $user->last_name,
            'lastName' => $user->last_name,
            'theme' => $user->theme,
            'avatar_url' => $user->avatar_url,
            'avatarUrl' => $user->avatar_url,
            'is_suspended' => (bool) $user->is_suspended,
            'isSuspended' => (bool) $user->is_suspended,
            'coins' => (int) ($user->coins ?? 0),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'twoFactorEnabled' => (bool) $user->two_factor_enabled,
        ];
    }

}
