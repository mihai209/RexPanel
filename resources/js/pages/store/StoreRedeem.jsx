import React, { useEffect, useState } from 'react';
import {
    Alert,
    Button,
    CircularProgress,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import { formatResourceList } from '../../components/commerce/commerceUtils';

const StoreRedeem = () => {
    const { setUser } = useAuth();
    const [data, setData] = useState(null);
    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const response = await client.get('/v1/store/redeem');
            setData(response.data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load redeem status.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const handleRedeem = async (event) => {
        event.preventDefault();
        setBusy(true);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post('/v1/store/redeem', { code });
            setData((current) => current ? ({
                ...current,
                wallet: response.data.wallet,
                inventory: response.data.inventory,
                usage: {
                    ...(current.usage || {}),
                    [response.data.code.id]: {
                        count: ((current.usage || {})[response.data.code.id]?.count || 0) + 1,
                        lastRedeemedAt: new Date().toISOString(),
                    },
                },
            }) : current);
            setUser((current) => current ? { ...current, coins: response.data.wallet.coins } : current);
            setCode('');
            setMessage({
                type: 'success',
                text: `Claimed ${response.data.code.code}. ${formatResourceList(response.data.code.rewards || {}) || 'No resources'}${(response.data.code.rewards?.coins || 0) > 0 ? ` · +${response.data.code.rewards.coins} ${response.data.wallet.economyUnit}` : ''}`,
            });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to redeem code.' });
        } finally {
            setBusy(false);
        }
    };

    if (loading) {
        return <CircularProgress size={28} />;
    }

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="h5" sx={{ fontWeight: 800, mb: 1 }}>Redeem Codes</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                    Codes can add wallet balance, grant inventory resources, or both. Expiry and per-user limits are checked server-side.
                </Typography>
                <form onSubmit={handleRedeem}>
                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                        <TextField
                            fullWidth
                            label="Redeem Code"
                            value={code}
                            onChange={(event) => setCode(event.target.value.toUpperCase())}
                        />
                        <Button type="submit" variant="contained" disabled={busy || !code.trim()}>
                            {busy ? <CircularProgress size={18} color="inherit" /> : 'Redeem'}
                        </Button>
                    </Stack>
                </form>
            </Paper>
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 1.5 }}>Current Inventory</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    {formatResourceList(data?.inventory?.resources || {}) || 'No inventory resources yet'}
                </Typography>
            </Paper>
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 1.5 }}>Available Codes</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    {(data?.codes || []).filter((entry) => entry.enabled).map((entry) => `${entry.name || entry.code} (${entry.remainingUses === null ? 'unlimited' : `${entry.remainingUses} left`})`).join(' · ') || 'No active codes published.'}
                </Typography>
            </Paper>
        </Stack>
    );
};

export default StoreRedeem;
