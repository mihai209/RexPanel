<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\CaptchaChallengeService;
use App\Services\CaptchaSettingsService;
use App\Services\NotificationService;
use App\Services\OAuthProviderService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private OAuthProviderService $providers,
        private TwoFactorService $twoFactor,
        private NotificationService $notifications,
        private CaptchaSettingsService $captchaSettings,
        private CaptchaChallengeService $captchaChallenges,
    )
    {
    }

    public function login(Request $request): JsonResponse
    {
        if (! $this->providers->standardAuthEnabled()) {
            return response()->json([
                'message' => 'Email and password login is currently disabled.',
                'error' => 'Standard login disabled',
            ], 403);
        }

        $request->validate([
            'password'    => 'required',
            'device_name' => 'nullable|string|max:255',
            'login'       => 'nullable|string|max:255',
            'username'    => 'nullable|string|max:255',
            'email'       => 'nullable|string|max:255',
            'captcha' => 'nullable|string|max:32',
            'captcha_token' => 'nullable|string|max:255',
        ]);

        if ($this->captchaSettings->isEnabled() && ! $this->captchaChallenges->validate(
            $request->input('captcha_token'),
            $request->input('captcha'),
        )) {
            return response()->json([
                'message' => 'Invalid captcha code.',
                'error' => 'Invalid captcha',
            ], 422);
        }

        $identifier = trim((string) (
            $request->input('login')
            ?? $request->input('username')
            ?? $request->input('email')
            ?? ''
        ));

        if ($identifier === '') {
            return response()->json([
                'message' => 'A username or email is required.',
                'error' => 'Missing credentials',
            ], 422);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
            ->orWhere('username', $identifier)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'error' => 'Invalid credentials',
            ], 422);
        }

        if ($user->is_suspended) {
            return response()->json([
                'message' => 'This account is suspended.',
                'error' => 'Account suspended',
                'code' => 'ACCOUNT_SUSPENDED',
            ], 423);
        }

        if ($user->two_factor_enabled && $user->two_factor_secret) {
            $challengeToken = $this->twoFactor->issueLoginChallenge($user, [
                'token_name' => $request->input('device_name', 'webapp'),
                'provider' => 'password',
            ]);

            return response()->json([
                'message' => 'Two-factor authentication is required to finish signing in.',
                'two_factor_required' => true,
                'twoFactorRequired' => true,
                'two_factor_token' => $challengeToken,
                'twoFactorToken' => $challengeToken,
            ]);
        }

        Auth::guard('web')->login($user);
        ActivityLog::log($user->id, 'Authenticated', $request->ip(), 'auth.login', [
            'provider' => 'password',
        ]);

        return response()->json($this->issueTokenResponse($user, $request->input('device_name', 'webapp')));
    }

    public function logout(Request $request)
    {
        ActivityLog::log($request->user()->id, 'Signed out', $request->ip(), 'auth.logout');

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->serializeUser($request->user()->fresh()));
    }

    public function completeTwoFactorLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'two_factor_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'captcha' => ['nullable', 'string', 'max:32'],
            'captcha_token' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->captchaSettings->isEnabled() && ! $this->captchaChallenges->validate(
            $data['captcha_token'] ?? null,
            $data['captcha'] ?? null,
        )) {
            return response()->json([
                'message' => 'Invalid captcha code.',
            ], 422);
        }

        $challenge = $this->twoFactor->getLoginChallenge($data['two_factor_token']);

        if (! $challenge) {
            return response()->json([
                'message' => 'The two-factor login request expired. Start again.',
            ], 422);
        }

        $user = User::query()->find($challenge['user_id'] ?? null);

        if (! $user) {
            $this->twoFactor->clearLoginChallenge($data['two_factor_token']);

            return response()->json([
                'message' => 'The two-factor login request is no longer valid.',
            ], 422);
        }

        if ($user->is_suspended) {
            $this->twoFactor->clearLoginChallenge($data['two_factor_token']);

            return response()->json([
                'message' => 'This account is suspended.',
                'code' => 'ACCOUNT_SUSPENDED',
            ], 423);
        }

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            $this->twoFactor->clearLoginChallenge($data['two_factor_token']);

            return response()->json([
                'message' => 'Two-factor authentication is no longer enabled for this account.',
            ], 422);
        }

        if (! $this->twoFactor->verifyCode($user->two_factor_secret, $data['code'])) {
            return response()->json([
                'message' => 'Invalid 6-digit authentication code.',
            ], 422);
        }

        $this->twoFactor->clearLoginChallenge($data['two_factor_token']);
        Auth::guard('web')->login($user);
        ActivityLog::log($user->id, 'Authenticated with two-factor challenge', $request->ip(), 'auth.login_2fa', [
            'provider' => 'password',
        ]);

        return response()->json($this->issueTokenResponse($user, $challenge['token_name'] ?? 'webapp'));
    }

    private function issueTokenResponse(User $user, string $tokenName): array
    {
        return [
            'token' => $user->createToken($tokenName)->plainTextToken,
            'user' => $this->serializeUser($user),
        ];
    }

    private function serializeUser(User $user): array
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
            'notification_unread_count' => $this->notifications->unreadCount($user),
            'notificationUnreadCount' => $this->notifications->unreadCount($user),
        ];
    }
}
