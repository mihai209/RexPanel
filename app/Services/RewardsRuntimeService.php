<?php

namespace App\Services;

use App\Models\AccountAfkState;
use App\Models\AccountRewardState;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RewardsRuntimeService
{
    public const PERIOD_SECONDS = [
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400,
        'week' => 604800,
        'month' => 2592000,
        'year' => 31536000,
    ];

    private const STREAK_RESET_INACTIVITY_SECONDS = 86400;

    public function __construct(private SystemSettingsService $settings)
    {
    }

    public function rewardsPayload(User $user): array
    {
        $runtime = $this->settings->rewardsRuntimeValues();
        $state = $this->hydrateRewardState($user, $runtime);
        $selectedPeriod = $state->selected_period;

        if (($runtime['claim']['rewards'][$selectedPeriod] ?? 0) <= 0) {
            $selectedPeriod = $this->pickFirstRewardablePeriod($runtime['claim']['rewards'], $runtime['claim']['defaultPeriod']);
            $state->forceFill(['selected_period' => $selectedPeriod])->save();
        }

        return [
            'wallet' => [
                'coins' => (int) ($user->fresh()->coins ?? 0),
                'economyUnit' => $runtime['economyUnit'],
            ],
            'features' => $runtime['features'],
            'claim' => [
                'selectedPeriod' => $selectedPeriod,
                'rewards' => $runtime['claim']['rewards'],
                'remainingByPeriod' => $this->claimRemainingByPeriod($state),
                'dailyStreak' => (int) $state->daily_streak,
                'dailyStreakBonusCoins' => $runtime['claim']['dailyStreakBonusCoins'],
                'dailyStreakMax' => $runtime['claim']['dailyStreakMax'],
                'streakResetSeconds' => $this->streakResetRemainingSeconds($state),
                'rewardAccrualDisabled' => (bool) $state->reward_accrual_disabled,
            ],
        ];
    }

    public function afkPayload(User $user): array
    {
        $runtime = $this->settings->rewardsRuntimeValues();
        $rewardState = $this->hydrateRewardState($user, $runtime);
        $afkState = $this->afkState($user);
        $remainingSeconds = $this->secondsUntil($afkState->next_payout_at);
        $cooldown = (int) $runtime['afkTimer']['cooldownSeconds'];

        return [
            'wallet' => [
                'coins' => (int) ($user->fresh()->coins ?? 0),
                'economyUnit' => $runtime['economyUnit'],
            ],
            'features' => $runtime['features'],
            'afkTimer' => [
                ...$runtime['afkTimer'],
                'remainingSeconds' => $remainingSeconds,
                'nextPayoutAt' => optional($afkState->next_payout_at)?->toIso8601String(),
                'lastSeenAt' => optional($afkState->last_seen_at)?->toIso8601String(),
                'lastTimerRewardAt' => optional($afkState->last_timer_reward_at)?->toIso8601String(),
                'heartbeatStatus' => $this->heartbeatStatus($afkState, $cooldown),
                'rewardAccrualDisabled' => (bool) $rewardState->reward_accrual_disabled,
            ],
        ];
    }

    public function claimReward(User $user, string $period, ?string $ip = null): array
    {
        $runtime = $this->settings->rewardsRuntimeValues();
        $period = $this->normalizeRewardPeriod($period, $runtime['claim']['defaultPeriod']);
        $rewardCoins = (int) ($runtime['claim']['rewards'][$period] ?? 0);

        if ($rewardCoins <= 0) {
            abort(response()->json([
                'message' => 'The selected reward period is disabled.',
            ], 422));
        }

        return DB::transaction(function () use ($user, $runtime, $period, $rewardCoins, $ip) {
            $state = $this->hydrateRewardState($user->fresh(), $runtime, true);

            if ($state->reward_accrual_disabled) {
                abort(response()->json([
                    'message' => 'Reward accrual is disabled for this account.',
                ], 403));
            }

            $claimColumn = $this->claimColumn($period);
            $remainingSeconds = $this->cooldownRemainingSeconds($state->{$claimColumn}, self::PERIOD_SECONDS[$period]);

            if ($remainingSeconds > 0) {
                $state->forceFill([
                    'last_activity_at' => now(),
                ])->save();

                return [
                    'status' => 429,
                    'body' => [
                        'message' => sprintf('Reward cooldown active (%ds).', $remainingSeconds),
                        'remainingSeconds' => $remainingSeconds,
                        'dailyStreak' => (int) $state->daily_streak,
                    ],
                ];
            }

            $previousStreak = (int) $state->daily_streak;
            $dailyStreak = $previousStreak;
            $streakBonusCoins = 0;
            $now = now();

            if ($period === 'day') {
                $dailyStreak = $this->nextDailyStreak($state, (int) $runtime['claim']['dailyStreakMax']);

                if ($previousStreak > 0 && $dailyStreak === 1 && $previousStreak !== 1) {
                    ActivityLog::log($user->id, 'Daily streak reset', $ip, 'rewards.streak_reset', [
                        'previousStreak' => $previousStreak,
                        'dailyStreak' => 1,
                        'period' => 'day',
                    ]);
                }

                if ($dailyStreak > $previousStreak) {
                    ActivityLog::log($user->id, 'Daily streak increased', $ip, 'rewards.streak_increased', [
                        'previousStreak' => $previousStreak,
                        'dailyStreak' => $dailyStreak,
                        'period' => 'day',
                    ]);
                }

                $streakBonusCoins = max(0, $dailyStreak - 1) * (int) $runtime['claim']['dailyStreakBonusCoins'];
            }

            $awardedCoins = $rewardCoins + $streakBonusCoins;
            $state->forceFill([
                'selected_period' => $period,
                'daily_streak' => $period === 'day' ? $dailyStreak : $state->daily_streak,
                'last_activity_at' => $now,
                'last_daily_claim_at' => $period === 'day' ? $now : $state->last_daily_claim_at,
                $claimColumn => $now,
            ])->save();

            $user->forceFill([
                'coins' => max(0, (int) $user->fresh()->coins + $awardedCoins),
            ])->save();

            ActivityLog::log($user->id, sprintf('Claimed %s reward', $period), $ip, 'rewards.claim', [
                'period' => $period,
                'coinsDelta' => $awardedCoins,
                'baseRewardCoins' => $rewardCoins,
                'streakBonusCoins' => $streakBonusCoins,
                'dailyStreak' => (int) $state->daily_streak,
            ]);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Reward claimed.',
                    'period' => $period,
                    'baseRewardCoins' => $rewardCoins,
                    'streakBonusCoins' => $streakBonusCoins,
                    'awardedCoins' => $awardedCoins,
                    'coins' => (int) $user->fresh()->coins,
                    'economyUnit' => $runtime['economyUnit'],
                    'dailyStreak' => (int) $state->daily_streak,
                    'streakResetSeconds' => $this->streakResetRemainingSeconds($state),
                    'remainingSeconds' => self::PERIOD_SECONDS[$period],
                ],
            ];
        });
    }

    public function pingAfk(User $user, ?string $ip = null): array
    {
        $runtime = $this->settings->rewardsRuntimeValues();

        return DB::transaction(function () use ($user, $runtime, $ip) {
            $rewardState = $this->hydrateRewardState($user->fresh(), $runtime, true);
            $afkState = $this->afkState($user, true);

            if ($rewardState->reward_accrual_disabled) {
                abort(response()->json([
                    'message' => 'Reward accrual is disabled for this account.',
                ], 403));
            }

            $now = now();
            $cooldown = (int) $runtime['afkTimer']['cooldownSeconds'];
            $rewardCoins = (int) $runtime['afkTimer']['rewardCoins'];
            $awardedCoins = 0;

            if (! $afkState->next_payout_at) {
                $afkState->forceFill([
                    'last_seen_at' => $now,
                    'next_payout_at' => $now->copy()->addSeconds($cooldown),
                ])->save();
            } elseif ($rewardCoins > 0 && $afkState->next_payout_at->lte($now)) {
                $awardedCoins = $rewardCoins;
                $user->forceFill([
                    'coins' => max(0, (int) $user->fresh()->coins + $awardedCoins),
                ])->save();

                $afkState->forceFill([
                    'last_timer_reward_at' => $now,
                    'next_payout_at' => $now->copy()->addSeconds($cooldown),
                ])->save();

                ActivityLog::log($user->id, 'AFK payout awarded', $ip, 'rewards.afk_payout', [
                    'coinsDelta' => $awardedCoins,
                    'period' => 'afk',
                ]);
            }

            $afkState->forceFill([
                'last_seen_at' => $now,
                'next_payout_at' => $afkState->next_payout_at ?: $now->copy()->addSeconds($cooldown),
            ])->save();

            return [
                'awarded' => $awardedCoins > 0,
                'awardedCoins' => $awardedCoins,
                'coins' => (int) $user->fresh()->coins,
                'rewardCoins' => $rewardCoins,
                'economyUnit' => $runtime['economyUnit'],
                'remainingSeconds' => $this->secondsUntil($afkState->next_payout_at),
                'nextPayoutAt' => optional($afkState->next_payout_at)?->toIso8601String(),
                'heartbeatStatus' => $this->heartbeatStatus($afkState, $cooldown),
            ];
        });
    }

    public function disableRewardAccrual(User $user): void
    {
        $state = AccountRewardState::query()->firstOrCreate(['user_id' => $user->id], [
            'selected_period' => 'minute',
        ]);

        $state->forceFill(['reward_accrual_disabled' => true])->save();
    }

    private function hydrateRewardState(User $user, array $runtime, bool $lockForUpdate = false): AccountRewardState
    {
        $query = AccountRewardState::query()->where('user_id', $user->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $state = $query->first();

        if (! $state) {
            $state = AccountRewardState::query()->create([
                'user_id' => $user->id,
                'selected_period' => $runtime['claim']['defaultPeriod'],
            ]);
        }

        if ($this->shouldResetStreak($state)) {
            $previousStreak = (int) $state->daily_streak;
            $state->forceFill([
                'daily_streak' => 0,
                'last_daily_claim_at' => null,
            ])->save();

            if ($previousStreak > 0) {
                ActivityLog::log($user->id, 'Daily streak reset from inactivity', null, 'rewards.streak_reset', [
                    'previousStreak' => $previousStreak,
                    'dailyStreak' => 0,
                    'reason' => 'inactivity',
                ]);
            }
        }

        $selected = $this->normalizeRewardPeriod($state->selected_period, $runtime['claim']['defaultPeriod']);

        if ($selected !== $state->selected_period) {
            $state->forceFill(['selected_period' => $selected])->save();
        }

        return $state->fresh();
    }

    private function afkState(User $user, bool $lockForUpdate = false): AccountAfkState
    {
        $query = AccountAfkState::query()->where('user_id', $user->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first() ?? AccountAfkState::query()->create([
            'user_id' => $user->id,
        ]);
    }

    private function nextDailyStreak(AccountRewardState $state, int $maxStreak): int
    {
        $lastDailyClaimAt = $state->last_daily_claim_at;

        if (! $lastDailyClaimAt) {
            return 1;
        }

        $elapsedSeconds = (int) $lastDailyClaimAt->diffInSeconds(now());

        if ($elapsedSeconds >= self::PERIOD_SECONDS['day'] && $elapsedSeconds < (self::PERIOD_SECONDS['day'] * 2)) {
            return max(1, min($maxStreak, (int) $state->daily_streak + 1));
        }

        if ($elapsedSeconds >= (self::PERIOD_SECONDS['day'] * 2)) {
            return 1;
        }

        return max(1, min($maxStreak, (int) max(1, $state->daily_streak)));
    }

    private function shouldResetStreak(AccountRewardState $state): bool
    {
        return $state->daily_streak > 0
            && $state->last_activity_at
            && $state->last_activity_at->diffInSeconds(now()) >= self::STREAK_RESET_INACTIVITY_SECONDS;
    }

    private function claimRemainingByPeriod(AccountRewardState $state): array
    {
        $remaining = [];

        foreach (array_keys(self::PERIOD_SECONDS) as $period) {
            $remaining[$period] = $this->cooldownRemainingSeconds($state->{$this->claimColumn($period)}, self::PERIOD_SECONDS[$period]);
        }

        return $remaining;
    }

    private function streakResetRemainingSeconds(AccountRewardState $state): int
    {
        if ($state->daily_streak <= 0 || ! $state->last_activity_at) {
            return 0;
        }

        return max(0, self::STREAK_RESET_INACTIVITY_SECONDS - (int) $state->last_activity_at->diffInSeconds(now()));
    }

    private function cooldownRemainingSeconds(?Carbon $claimedAt, int $seconds): int
    {
        if (! $claimedAt || $seconds <= 0) {
            return 0;
        }

        return max(0, $seconds - (int) $claimedAt->diffInSeconds(now()));
    }

    private function secondsUntil(?Carbon $moment): int
    {
        if (! $moment || $moment->isPast()) {
            return 0;
        }

        return now()->diffInSeconds($moment);
    }

    private function heartbeatStatus(AccountAfkState $state, int $cooldown): string
    {
        if (! $state->last_seen_at) {
            return 'idle';
        }

        return $state->last_seen_at->diffInSeconds(now()) <= max(15, $cooldown * 2)
            ? 'alive'
            : 'stale';
    }

    private function normalizeRewardPeriod(?string $period, string $fallback): string
    {
        $normalized = strtolower(trim((string) $period));

        return array_key_exists($normalized, self::PERIOD_SECONDS) ? $normalized : $fallback;
    }

    private function pickFirstRewardablePeriod(array $rewards, string $fallback): string
    {
        foreach (array_keys(self::PERIOD_SECONDS) as $period) {
            if ((int) ($rewards[$period] ?? 0) > 0) {
                return $period;
            }
        }

        return $fallback;
    }

    private function claimColumn(string $period): string
    {
        return match ($period) {
            'minute' => 'minute_claimed_at',
            'hour' => 'hour_claimed_at',
            'day' => 'day_claimed_at',
            'week' => 'week_claimed_at',
            'month' => 'month_claimed_at',
            'year' => 'year_claimed_at',
            default => 'minute_claimed_at',
        };
    }
}
