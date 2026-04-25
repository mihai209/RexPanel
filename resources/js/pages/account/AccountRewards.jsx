import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import {
    Redeem as RedeemIcon,
    AccountBalanceWallet as WalletIcon,
    LocalFireDepartment as StreakIcon,
} from '@mui/icons-material';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';

const formatCountdown = (seconds) => {
    const parsed = Number(seconds || 0);

    if (!parsed || parsed <= 0) {
        return 'Ready';
    }

    if (parsed < 60) {
        return `${parsed}s`;
    }

    const days = Math.floor(parsed / 86400);
    const hours = Math.floor((parsed % 86400) / 3600);
    const minutes = Math.floor((parsed % 3600) / 60);

    if (days > 0) {
        return `${days}d ${hours}h`;
    }

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m ${parsed % 60}s`;
};

const AccountRewards = () => {
    const { user, setUser } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState('');
    const [message, setMessage] = useState({ type: '', text: '' });

    const loadRewards = async ({ silent = false } = {}) => {
        if (!silent) {
            setLoading(true);
        }

        try {
            const response = await client.get('/v1/account/rewards');
            setData(response.data);
        } catch (error) {
            if (!silent) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load rewards.' });
            }
        } finally {
            if (!silent) {
                setLoading(false);
            }
        }
    };

    useEffect(() => {
        loadRewards();
    }, []);

    useEffect(() => {
        if (!data) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            setData((current) => {
                if (!current) {
                    return current;
                }

                const nextRemaining = Object.fromEntries(
                    Object.entries(current.claim?.remainingByPeriod || {}).map(([period, seconds]) => [period, Math.max(0, Number(seconds || 0) - 1)]),
                );

                return {
                    ...current,
                    claim: {
                        ...current.claim,
                        remainingByPeriod: nextRemaining,
                        streakResetSeconds: Math.max(0, Number(current.claim?.streakResetSeconds || 0) - 1),
                    },
                };
            });
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, [data]);

    const handleClaim = async (period) => {
        setBusy(period);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post('/v1/account/rewards/claim', { period });
            const payload = response.data;

            setData((current) => current ? ({
                ...current,
                wallet: {
                    ...current.wallet,
                    coins: payload.coins,
                },
                claim: {
                    ...current.claim,
                    selectedPeriod: payload.period,
                    dailyStreak: payload.dailyStreak,
                    streakResetSeconds: payload.streakResetSeconds,
                    remainingByPeriod: {
                        ...current.claim.remainingByPeriod,
                        [payload.period]: payload.remainingSeconds,
                    },
                },
            }) : current);
            setUser((current) => current ? { ...current, coins: payload.coins } : current);
            setMessage({ type: 'success', text: `${payload.awardedCoins} ${payload.economyUnit} added to your wallet.` });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to claim reward.' });
            await loadRewards({ silent: true });
        } finally {
            setBusy('');
        }
    };

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}
            <Paper elevation={0} sx={{ p: 4, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                    <Paper elevation={0} sx={{ flex: 1, p: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                            Wallet
                        </Typography>
                        <Typography variant="h4" sx={{ mt: 1, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                            <WalletIcon sx={{ color: '#36d399' }} />
                            {user?.coins ?? data?.wallet?.coins ?? 0} {data?.wallet?.economyUnit || 'Coins'}
                        </Typography>
                    </Paper>
                    <Paper elevation={0} sx={{ flex: 1, p: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                            Daily Streak
                        </Typography>
                        <Typography variant="h4" sx={{ mt: 1, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                            <StreakIcon sx={{ color: '#f59e0b' }} />
                            {data?.claim?.dailyStreak ?? 0}
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            Bonus: +{data?.claim?.dailyStreakBonusCoins ?? 0} per extra day, capped at {data?.claim?.dailyStreakMax ?? 0}. Reset in {formatCountdown(data?.claim?.streakResetSeconds ?? 0)}.
                        </Typography>
                    </Paper>
                </Stack>
            </Paper>

            <Paper elevation={0} sx={{ p: 4, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1, mb: 1.5 }}>
                    <RedeemIcon sx={{ fontSize: 20 }} /> Claim Rewards
                </Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                    Claim minute, hour, day, week, month, and year rewards here. Cooldowns are enforced server-side and persist across reloads.
                </Typography>

                {loading ? (
                    <CircularProgress size={24} />
                ) : (
                    <Stack spacing={1.25}>
                        {Object.entries(data?.claim?.rewards || {}).map(([period, amount]) => {
                            const remainingSeconds = data?.claim?.remainingByPeriod?.[period] ?? 0;
                            const disabled = !data?.features?.claimRewardsEnabled
                                || data?.claim?.rewardAccrualDisabled
                                || amount <= 0
                                || remainingSeconds > 0
                                || (busy !== '' && busy !== period);

                            return (
                                <Paper
                                    key={period}
                                    elevation={0}
                                    sx={{
                                        p: 2,
                                        bgcolor: 'rgba(255,255,255,0.02)',
                                        border: '1px solid rgba(255,255,255,0.05)',
                                        borderRadius: 2,
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center',
                                        gap: 2,
                                    }}
                                >
                                    <Box>
                                        <Typography variant="body1" sx={{ fontWeight: 700, textTransform: 'capitalize' }}>
                                            {period}
                                        </Typography>
                                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                            {amount} {data?.wallet?.economyUnit || 'Coins'} · {remainingSeconds > 0 ? `Ready in ${formatCountdown(remainingSeconds)}` : 'Ready to claim'}
                                        </Typography>
                                    </Box>
                                    <Button
                                        variant={data?.claim?.selectedPeriod === period ? 'contained' : 'outlined'}
                                        disabled={disabled}
                                        onClick={() => handleClaim(period)}
                                    >
                                        {busy === period ? <CircularProgress size={18} color="inherit" /> : 'Claim'}
                                    </Button>
                                </Paper>
                            );
                        })}
                    </Stack>
                )}
            </Paper>
        </Stack>
    );
};

export default AccountRewards;
