<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiQuotaRedisService;
use App\Services\OAuthProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const AI_DAILY_QUOTA_DEFAULT_LIMIT = 100;

    public function __construct(
        private OAuthProviderService $providers,
        private AiQuotaRedisService $quota,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $users = User::query()
            ->withCount('servers')
            ->with(['linkedAccounts' => fn ($query) => $query->orderBy('provider')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    if (ctype_digit($search)) {
                        $nested->where('id', (int) $search)
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) like ?", ["%{$search}%"]);

                        return;
                    }

                    $nested
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) like ?", ["%{$search}%"]);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($this->decoratePaginator($users));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $user = User::query()->create($this->preparePayload($data, true));
        $user->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);
        $defaultLimit = $this->defaultAiQuotaLimit();
        $usedToday = $this->quotaUsageForUser($user->id);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $this->serializeAdminUser($user, $usedToday, $defaultLimit),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);

        return response()->json($this->serializeAdminUser($user, $this->quotaUsageForUser($user->id), $this->defaultAiQuotaLimit()));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate($this->rules($user));

        if (
            $user->id === (int) $request->user()?->id
            && array_key_exists('is_suspended', $data)
            && $this->toBool($data['is_suspended'])
        ) {
            return response()->json([
                'message' => 'You cannot suspend your own account.',
            ], 422);
        }

        $wasSuspended = (bool) $user->is_suspended;
        $user->update($this->preparePayload($data));

        if (! $wasSuspended && $user->fresh()->is_suspended) {
            $user->tokens()->delete();
            $this->deleteUserSessions($user->id);
        }

        $user->refresh()->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);
        $defaultLimit = $this->defaultAiQuotaLimit();
        $usedToday = $this->quotaUsageForUser($user->id);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->serializeAdminUser($user, $usedToday, $defaultLimit),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === (int) $request->user()?->id) {
            return response()->json(['message' => 'You cannot delete yourself.'], 403);
        }

        $user->linkedAccounts()->delete();
        $this->forgetGoogleTokenSetting($user->id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function unlinkProvider(Request $request, User $user, string $provider): JsonResponse
    {
        $supported = array_keys(OAuthProviderService::PROVIDERS);

        if (! in_array($provider, $supported, true)) {
            return response()->json([
                'message' => 'Unsupported provider.',
            ], 422);
        }

        $link = $user->linkedAccounts()
            ->where('provider', $provider)
            ->first();

        if (! $link) {
            return response()->json([
                'message' => 'This provider is not linked to the selected user.',
            ], 404);
        }

        if ($user->linkedAccounts()->count() <= 1) {
            return response()->json([
                'message' => 'Cannot unlink the last linked account because it may remove the user\'s sign-in path.',
            ], 422);
        }

        $link->delete();

        if ($provider === 'google') {
            $this->forgetGoogleTokenSetting($user->id);
        }

        $user->refresh()->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);
        $defaultLimit = $this->defaultAiQuotaLimit();
        $usedToday = $this->quotaUsageForUser($user->id);

        return response()->json([
            'message' => sprintf('%s account unlinked.', $this->providers->prettyName($provider)),
            'user' => $this->serializeAdminUser($user, $usedToday, $defaultLimit),
        ]);
    }

    public function updateAiQuota(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'daily_quota' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $user->forceFill([
            'ai_daily_quota_override' => $data['daily_quota'] ?? null,
        ])->save();

        $user->refresh()->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);
        $defaultLimit = $this->defaultAiQuotaLimit();
        $usedToday = $this->quotaUsageForUser($user->id);

        return response()->json([
            'message' => array_key_exists('daily_quota', $data) && $data['daily_quota'] !== null
                ? 'AI quota updated.'
                : 'AI quota override cleared.',
            'user' => $this->serializeAdminUser($user, $usedToday, $defaultLimit),
        ]);
    }

    public function resetAiQuota(User $user): JsonResponse
    {
        $this->quota->resetUsage($user->id);

        $user->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);

        return response()->json([
            'message' => 'AI quota usage reset for today.',
            'user' => $this->serializeAdminUser($user, 0, $this->defaultAiQuotaLimit()),
        ]);
    }

    public function disableTwoFactor(User $user): JsonResponse
    {
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        $user->refresh()->loadCount('servers')->load(['linkedAccounts' => fn ($query) => $query->orderBy('provider')]);
        $usedToday = $this->quotaUsageForUser($user->id);

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
            'user' => $this->serializeAdminUser($user, $usedToday, $this->defaultAiQuotaLimit()),
        ]);
    }

    private function rules(?User $user = null): array
    {
        $providerOptions = array_merge(['gravatar', 'url', 'custom'], array_keys(OAuthProviderService::PROVIDERS));

        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'avatar_provider' => ['nullable', 'string', Rule::in($providerOptions)],
            'avatar_url' => ['nullable', 'string', 'url', 'max:2048'],
            'custom_avatar_url' => ['nullable', 'string', 'url', 'max:2048'],
            'is_admin' => ['sometimes', 'boolean'],
            'is_suspended' => ['sometimes', 'boolean'],
            'coins' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function preparePayload(array $data, bool $creating = false): array
    {
        foreach (['first_name', 'last_name', 'avatar_provider', 'avatar_url', 'custom_avatar_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeNullableString($data[$field]);
            }
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($creating) {
            $data['is_admin'] = $this->toBool($data['is_admin'] ?? false);
            $data['is_suspended'] = $this->toBool($data['is_suspended'] ?? false);
            $data['coins'] = isset($data['coins']) ? max(0, (int) $data['coins']) : 0;
            $data['avatar_provider'] = $data['avatar_provider'] ?? 'gravatar';
        } else {
            if (array_key_exists('is_admin', $data)) {
                $data['is_admin'] = $this->toBool($data['is_admin']);
            }
            if (array_key_exists('is_suspended', $data)) {
                $data['is_suspended'] = $this->toBool($data['is_suspended']);
            }
            if (array_key_exists('coins', $data)) {
                $data['coins'] = max(0, (int) $data['coins']);
            }
        }

        return $data;
    }

    private function decoratePaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $collection = $paginator->getCollection();
        $quotaUsage = $this->fetchQuotaUsageMap($collection->pluck('id'));
        $defaultLimit = $this->defaultAiQuotaLimit();

        $paginator->setCollection(
            $collection->map(fn (User $user) => $this->serializeAdminUser($user, $quotaUsage[$user->id] ?? 0, $defaultLimit))
        );

        return $paginator;
    }

    private function serializeAdminUser(User $user, int $aiQuotaUsedToday = 0, ?int $defaultLimit = null): array
    {
        $override = $user->ai_daily_quota_override;
        $resolvedDefaultLimit = $defaultLimit ?? $this->defaultAiQuotaLimit();
        $limit = is_int($override) ? $override : $resolvedDefaultLimit;
        $linkedAccounts = $user->relationLoaded('linkedAccounts')
            ? $user->linkedAccounts->map(fn (LinkedAccount $account) => [
                'id' => $account->id,
                'provider' => $account->provider,
                'provider_email' => $account->provider_email,
                'provider_username' => $account->provider_username,
            ])->values()->all()
            : [];

        $fullName = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'firstName' => $user->first_name,
            'last_name' => $user->last_name,
            'lastName' => $user->last_name,
            'full_name' => $fullName !== '' ? $fullName : null,
            'avatar_provider' => $user->avatar_provider ?? 'gravatar',
            'avatarProvider' => $user->avatar_provider ?? 'gravatar',
            'avatar_url' => $user->avatar_url,
            'avatarUrl' => $user->avatar_url,
            'custom_avatar_url' => $user->custom_avatar_url,
            'customAvatarUrl' => $user->custom_avatar_url,
            'is_admin' => (bool) $user->is_admin,
            'isAdmin' => (bool) $user->is_admin,
            'is_suspended' => (bool) $user->is_suspended,
            'isSuspended' => (bool) $user->is_suspended,
            'coins' => (int) ($user->coins ?? 0),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'twoFactorEnabled' => (bool) $user->two_factor_enabled,
            'ai_quota_override' => is_int($override) ? $override : null,
            'aiQuotaOverride' => is_int($override) ? $override : null,
            'ai_quota_limit' => $limit,
            'aiQuotaLimit' => $limit,
            'ai_quota_used_today' => $aiQuotaUsedToday,
            'aiQuotaUsedToday' => $aiQuotaUsedToday,
            'ai_quota_remaining' => max(0, $limit - $aiQuotaUsedToday),
            'aiQuotaRemaining' => max(0, $limit - $aiQuotaUsedToday),
            'servers_count' => (int) ($user->servers_count ?? 0),
            'serversCount' => (int) ($user->servers_count ?? 0),
            'linked_accounts' => $linkedAccounts,
            'linkedAccounts' => $linkedAccounts,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function fetchQuotaUsageMap(Collection $userIds): array
    {
        return $this->quota->usageMap($userIds);
    }

    private function quotaUsageForUser(int $userId): int
    {
        return $this->quota->usageForUser($userId);
    }

    private function defaultAiQuotaLimit(): int
    {
        $raw = SystemSetting::query()->find('aiDailyQuota')?->value;
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => self::AI_DAILY_QUOTA_DEFAULT_LIMIT]]);

        return min(max((int) $value, 0), 10000);
    }

    private function forgetGoogleTokenSetting(int $userId): void
    {
        SystemSetting::query()->where('key', $this->googleTokenSettingKey($userId))->delete();
    }

    private function googleTokenSettingKey(int $userId): string
    {
        return "oauth_google_tokens_user_{$userId}";
    }

    private function deleteUserSessions(int $userId): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $userId)->delete();
        } catch (\Throwable) {
            // Session driver may not use the database table.
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
