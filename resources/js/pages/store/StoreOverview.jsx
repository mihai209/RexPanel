import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Grid,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import {
    AccountBalanceWallet as WalletIcon,
    Insights as ForecastIcon,
    LocalOffer as DealIcon,
    WorkspacePremium as RevenueIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { formatResourceList } from '../../components/commerce/commerceUtils';

const StoreOverview = () => {
    const navigate = useNavigate();
    const { user, setUser } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [busyPlan, setBusyPlan] = useState('');
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);

        try {
            const response = await client.get('/v1/store');
            setData(response.data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load store.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const handleSubscribe = async (planId) => {
        setBusyPlan(planId);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post('/v1/store/revenue/subscribe', { planId });
            setData((current) => current ? ({
                ...current,
                wallet: response.data.wallet,
                revenueProfile: response.data.profile,
            }) : current);
            setUser((current) => current ? { ...current, coins: response.data.wallet.coins } : current);
            setMessage({ type: 'success', text: response.data.message || 'Revenue plan subscribed.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to subscribe to revenue plan.' });
        } finally {
            setBusyPlan('');
        }
    };

    if (loading) {
        return <CircularProgress size={28} />;
    }

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}

            <Paper elevation={0} sx={{ p: 4, borderRadius: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between">
                    <Box>
                        <Typography variant="h4" sx={{ fontWeight: 800, mb: 1 }}>Store</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 720 }}>
                            Revenue plans, durable resource inventory, redeem rewards, and forecast visibility all live here as a first-class user area.
                        </Typography>
                    </Box>
                    <Chip
                        icon={<WalletIcon sx={{ fontSize: 16 }} />}
                        label={`${user?.coins ?? data?.wallet?.coins ?? 0} ${data?.wallet?.economyUnit || 'Coins'}`}
                        sx={{ alignSelf: 'flex-start', bgcolor: 'rgba(54,211,153,0.12)', color: '#9ae6b4', border: '1px solid rgba(54,211,153,0.18)' }}
                    />
                </Stack>
            </Paper>

            <Grid container spacing={2}>
                <Grid item xs={12} md={4}>
                    <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Active Revenue</Typography>
                        <Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>
                            {data?.activePlan?.name || data?.revenueProfile?.planName || 'No plan'}
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            {data?.revenueProfile?.active ? `Status: ${data?.revenueProfile?.status}` : 'Subscribe to apply revenue-plan limits before inventory fallback.'}
                        </Typography>
                    </Paper>
                </Grid>
                <Grid item xs={12} md={4}>
                    <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Inventory</Typography>
                        <Typography variant="body1" sx={{ mt: 1, fontWeight: 700 }}>
                            {formatResourceList(data?.inventory?.resources || {}) || 'No inventory resources yet'}
                        </Typography>
                    </Paper>
                </Grid>
                <Grid item xs={12} md={4}>
                    <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Forecast Value</Typography>
                        <Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>
                            {data?.forecast?.estimatedCoinValue ?? 0} {data?.wallet?.economyUnit || 'Coins'}
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            Based on current effective limits and admin-configured pricing controls.
                        </Typography>
                    </Paper>
                </Grid>
            </Grid>

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <Button startIcon={<DealIcon />} variant="outlined" onClick={() => navigate('/store/deals')}>Browse Deals</Button>
                <Button startIcon={<RevenueIcon />} variant="outlined" onClick={() => navigate('/store/redeem')}>Redeem Codes</Button>
                <Button startIcon={<ForecastIcon />} variant="outlined" onClick={() => navigate('/store/deals?tab=forecast')}>Forecast Details</Button>
            </Stack>

            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Revenue Plans</Typography>
                <Grid container spacing={2}>
                    {(data?.revenuePlans || []).map((plan) => (
                        <Grid item xs={12} md={6} lg={4} key={plan.id}>
                            <Paper elevation={0} sx={{ p: 2.5, height: '100%', bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                                <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1.5 }}>
                                    <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>{plan.name}</Typography>
                                    {plan.featured && <Chip label="Featured" size="small" color="success" />}
                                </Stack>
                                <Typography variant="body2" sx={{ color: 'text.secondary', minHeight: 42 }}>
                                    {plan.description || 'Revenue-backed provisioning profile.'}
                                </Typography>
                                <Typography variant="body2" sx={{ mt: 1.5, color: 'text.secondary' }}>
                                    {formatResourceList({
                                        ramMb: plan.maxMemoryMb,
                                        cpuPercent: plan.maxCpuPercent,
                                        diskMb: plan.maxDiskMb,
                                    }) || 'No ceilings'}
                                </Typography>
                                <Typography variant="h6" sx={{ mt: 2, fontWeight: 800 }}>
                                    {plan.priceCoins} {data?.wallet?.economyUnit || 'Coins'}
                                </Typography>
                                <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                                    {plan.periodDays} days · max servers {plan.maxServers || 'unlimited'}
                                </Typography>
                                <Button
                                    fullWidth
                                    variant="contained"
                                    disabled={!plan.enabled || (busyPlan !== '' && busyPlan !== plan.id)}
                                    onClick={() => handleSubscribe(plan.id)}
                                >
                                    {busyPlan === plan.id ? <CircularProgress size={18} color="inherit" /> : 'Subscribe'}
                                </Button>
                            </Paper>
                        </Grid>
                    ))}
                </Grid>
            </Paper>
        </Stack>
    );
};

export default StoreOverview;
