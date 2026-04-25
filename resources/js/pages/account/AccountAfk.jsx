import React, { useEffect, useState } from 'react';
import {
    Alert,
    CircularProgress,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import {
    Timer as TimerIcon,
    Bolt as HeartbeatIcon,
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

    const hours = Math.floor(parsed / 3600);
    const minutes = Math.floor((parsed % 3600) / 60);

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m ${parsed % 60}s`;
};

const AccountAfk = () => {
    const { setUser } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState({ type: '', text: '' });

    const loadAfk = async ({ silent = false } = {}) => {
        if (!silent) {
            setLoading(true);
        }

        try {
            const response = await client.get('/v1/account/afk');
            setData(response.data);
        } catch (error) {
            if (!silent) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load AFK state.' });
            }
        } finally {
            if (!silent) {
                setLoading(false);
            }
        }
    };

    useEffect(() => {
        loadAfk();
    }, []);

    useEffect(() => {
        if (!data?.features?.afkRewardsEnabled) {
            return undefined;
        }

        const tickId = window.setInterval(() => {
            setData((current) => current ? ({
                ...current,
                afkTimer: {
                    ...current.afkTimer,
                    remainingSeconds: Math.max(0, Number(current.afkTimer?.remainingSeconds || 0) - 1),
                },
            }) : current);
        }, 1000);

        const pingId = window.setInterval(async () => {
            try {
                const response = await client.post('/v1/account/afk/ping');
                const payload = response.data;

                setData((current) => current ? ({
                    ...current,
                    wallet: {
                        ...current.wallet,
                        coins: payload.coins,
                    },
                    afkTimer: {
                        ...current.afkTimer,
                        remainingSeconds: payload.remainingSeconds,
                        rewardCoins: payload.rewardCoins,
                        nextPayoutAt: payload.nextPayoutAt,
                        heartbeatStatus: payload.heartbeatStatus,
                    },
                }) : current);
                setUser((current) => current ? { ...current, coins: payload.coins } : current);

                if (payload.awarded) {
                    setMessage({ type: 'success', text: `${payload.awardedCoins} ${payload.economyUnit} added from AFK timer.` });
                }
            } catch (error) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'AFK heartbeat failed.' });
            }
        }, 15000);

        return () => {
            window.clearInterval(tickId);
            window.clearInterval(pingId);
        };
    }, [data?.features?.afkRewardsEnabled, setUser]);

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}
            <Paper elevation={0} sx={{ p: 4, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1, mb: 1.5 }}>
                    <TimerIcon sx={{ fontSize: 20 }} /> AFK Runtime
                </Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                    This page keeps the AFK heartbeat active. The timer state is server-backed, so reloads keep the same next payout.
                </Typography>

                {loading ? (
                    <CircularProgress size={24} />
                ) : (
                    <Stack spacing={2}>
                        <Paper elevation={0} sx={{ p: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                                Next payout
                            </Typography>
                            <Typography variant="h4" sx={{ mt: 1, fontWeight: 800 }}>
                                {formatCountdown(data?.afkTimer?.remainingSeconds ?? 0)}
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                                {data?.afkTimer?.rewardCoins ?? 0} {data?.wallet?.economyUnit || 'Coins'} every {formatCountdown(data?.afkTimer?.cooldownSeconds ?? 0)}.
                            </Typography>
                        </Paper>

                        <Paper elevation={0} sx={{ p: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                                Heartbeat
                            </Typography>
                            <Typography variant="h5" sx={{ mt: 1, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                                <HeartbeatIcon sx={{ color: data?.afkTimer?.heartbeatStatus === 'alive' ? '#36d399' : '#f59e0b' }} />
                                {data?.afkTimer?.heartbeatStatus || 'idle'}
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                                Last seen: {data?.afkTimer?.lastSeenAt ? new Date(data.afkTimer.lastSeenAt).toLocaleString() : 'Never'}
                            </Typography>
                        </Paper>
                    </Stack>
                )}
            </Paper>
        </Stack>
    );
};

export default AccountAfk;
