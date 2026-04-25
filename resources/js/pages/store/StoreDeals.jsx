import React, { useEffect, useState } from 'react';
import {
    Alert,
    CircularProgress,
    Grid,
    Paper,
    Stack,
    Typography,
    Button,
    Chip,
} from '@mui/material';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { formatResourceList } from '../../components/commerce/commerceUtils';

const StoreDeals = () => {
    const { setUser } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState('');
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const response = await client.get('/v1/store/deals');
            setData(response.data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load deals.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const handleBuy = async (dealId) => {
        setBusy(dealId);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post('/v1/store/deals/purchase', { dealId });
            setData((current) => current ? ({
                ...current,
                wallet: response.data.wallet,
                inventory: response.data.inventory,
                deals: current.deals.map((deal) => deal.id === response.data.deal.id ? response.data.deal : deal),
            }) : current);
            setUser((current) => current ? { ...current, coins: response.data.wallet.coins } : current);
            setMessage({ type: 'success', text: response.data.message || 'Store deal purchased.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to purchase deal.' });
        } finally {
            setBusy('');
        }
    };

    if (loading) {
        return <CircularProgress size={28} />;
    }

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="h5" sx={{ fontWeight: 800, mb: 1 }}>Store Deals</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    Purchase durable inventory resources with your wallet balance. Stock, enabled state, and featured deals are all enforced server-side.
                </Typography>
            </Paper>
            <Grid container spacing={2}>
                {(data?.deals || []).map((deal) => (
                    <Grid item xs={12} md={6} lg={4} key={deal.id}>
                        <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1.5 }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>{deal.name}</Typography>
                                {deal.featured && <Chip size="small" label="Featured" color="success" />}
                            </Stack>
                            <Typography variant="body2" sx={{ color: 'text.secondary', minHeight: 40 }}>
                                {deal.description || 'No description.'}
                            </Typography>
                            <Typography variant="body2" sx={{ mt: 2, color: 'text.secondary' }}>
                                {formatResourceList(deal.resources || {}) || 'No resources'}
                            </Typography>
                            <Typography variant="h6" sx={{ mt: 2, fontWeight: 800 }}>
                                {deal.priceCoins} {data?.wallet?.economyUnit || 'Coins'}
                            </Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                                {deal.status} · remaining {deal.remainingStock ?? 0} / {deal.stockTotal ?? 0}
                            </Typography>
                            <Button
                                fullWidth
                                variant="contained"
                                disabled={!deal.enabled || Number(deal.remainingStock || 0) <= 0 || (busy !== '' && busy !== deal.id)}
                                onClick={() => handleBuy(deal.id)}
                            >
                                {busy === deal.id ? <CircularProgress size={18} color="inherit" /> : 'Buy'}
                            </Button>
                        </Paper>
                    </Grid>
                ))}
            </Grid>
        </Stack>
    );
};

export default StoreDeals;
