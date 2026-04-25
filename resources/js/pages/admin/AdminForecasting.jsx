import React, { useEffect, useState } from 'react';
import {
    Alert,
    CircularProgress,
    Grid,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import client from '../../api/client';
import { formatResourceList } from '../../components/commerce/commerceUtils';

const AdminForecasting = () => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState('');

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const response = await client.get('/v1/admin/forecasting');
                setData(response.data);
            } catch (error) {
                setMessage(error.response?.data?.message || 'Failed to load forecasting report.');
            } finally {
                setLoading(false);
            }
        };

        load();
    }, []);

    if (loading) {
        return <CircularProgress size={28} />;
    }

    return (
        <Stack spacing={3}>
            {message && <Alert severity="error">{message}</Alert>}
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="h5" sx={{ fontWeight: 800 }}>Forecasting</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                    Durable billing events and current commerce profiles are summarized here for pricing and quota forecasting.
                </Typography>
            </Paper>
            <Grid container spacing={2}>
                <Grid item xs={12} md={3}><Paper elevation={0} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}><Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Revenue</Typography><Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>{data?.summary?.totalRevenueCoins ?? 0} Coins</Typography></Paper></Grid>
                <Grid item xs={12} md={3}><Paper elevation={0} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}><Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Credits</Typography><Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>{data?.summary?.totalCreditsCoins ?? 0} Coins</Typography></Paper></Grid>
                <Grid item xs={12} md={3}><Paper elevation={0} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}><Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Events</Typography><Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>{data?.summary?.eventCount ?? 0}</Typography></Paper></Grid>
                <Grid item xs={12} md={3}><Paper elevation={0} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}><Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700 }}>Active Profiles</Typography><Typography variant="h6" sx={{ mt: 1, fontWeight: 800 }}>{data?.summary?.activeRevenueProfiles ?? 0}</Typography></Paper></Grid>
            </Grid>
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>Recent Billing Events</Typography>
                <Stack spacing={1.5}>
                    {(data?.recentEvents || []).map((event) => (
                        <Paper key={event.id} elevation={0} sx={{ p: 2, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="body2" sx={{ fontWeight: 700 }}>{event.event_type}</Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mt: 0.5 }}>
                                User #{event.user_id} · {event.coins_delta} coins · {formatResourceList(event.resource_delta || {}) || 'No resources'}
                            </Typography>
                        </Paper>
                    ))}
                </Stack>
            </Paper>
        </Stack>
    );
};

export default AdminForecasting;
