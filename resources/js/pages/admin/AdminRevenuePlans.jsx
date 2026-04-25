import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    Grid,
    Paper,
    Stack,
    Switch,
    TextField,
    Typography,
} from '@mui/material';
import client from '../../api/client';
import { formatResourceList } from '../../components/commerce/commerceUtils';

const blankPlan = {
    name: '',
    description: '',
    priceCoins: 0,
    periodDays: 30,
    maxServers: 0,
    maxMemoryGb: 0,
    maxCpuCores: 0,
    maxDiskGb: 0,
    enabled: true,
    featured: false,
};

const AdminRevenuePlans = () => {
    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(blankPlan);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const response = await client.get('/v1/admin/revenue-plans');
            setPlans(response.data.plans || []);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load revenue plans.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const openEditor = (plan = null) => {
        setEditing(plan);
        setForm(plan ? {
            ...blankPlan,
            ...plan,
            maxMemoryGb: Number(plan.maxMemoryMb || 0) / 1024,
            maxCpuCores: Number(plan.maxCpuPercent || 0) / 100,
            maxDiskGb: Number(plan.maxDiskMb || 0) / 1024,
        } : blankPlan);
        setDialogOpen(true);
    };

    const closeEditor = () => {
        setDialogOpen(false);
        setEditing(null);
        setForm(blankPlan);
    };

    const save = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });
        const payload = {
            ...form,
            maxMemoryMb: Math.round(Number(form.maxMemoryGb || 0) * 1024),
            maxCpuPercent: Math.round(Number(form.maxCpuCores || 0) * 100),
            maxDiskMb: Math.round(Number(form.maxDiskGb || 0) * 1024),
        };

        try {
            const response = editing
                ? await client.put(`/v1/admin/revenue-plans/${editing.id}`, payload)
                : await client.post('/v1/admin/revenue-plans', payload);
            const next = response.data.plan;
            setPlans((current) => editing
                ? current.map((entry) => entry.id === next.id ? next : entry)
                : [...current, next]);
            closeEditor();
            setMessage({ type: 'success', text: response.data.message || 'Revenue plan saved.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save revenue plan.' });
        } finally {
            setSaving(false);
        }
    };

    const remove = async (planId) => {
        try {
            await client.delete(`/v1/admin/revenue-plans/${planId}`);
            setPlans((current) => current.filter((entry) => entry.id !== planId));
            setMessage({ type: 'success', text: 'Revenue plan deleted.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete revenue plan.' });
        }
    };

    if (loading) {
        return <CircularProgress size={28} />;
    }

    return (
        <Stack spacing={3}>
            {message.text && <Alert severity={message.type || 'info'}>{message.text}</Alert>}
            <Paper elevation={0} sx={{ p: 4, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 800 }}>Revenue Plans</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            Manage the revenue-plan catalog stored in system settings and consumed by the user store flow.
                        </Typography>
                    </Box>
                    <Button variant="contained" onClick={() => openEditor()}>Create Plan</Button>
                </Stack>
            </Paper>
            <Grid container spacing={2}>
                {plans.map((plan) => (
                    <Grid item xs={12} md={6} lg={4} key={plan.id}>
                        <Paper elevation={0} sx={{ p: 3, borderRadius: 2.5, height: '100%', bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>{plan.name}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                                {plan.description || 'No description.'}
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 2 }}>
                                {formatResourceList({
                                    ramMb: plan.maxMemoryMb,
                                    cpuPercent: plan.maxCpuPercent,
                                    diskMb: plan.maxDiskMb,
                                }) || 'No resource ceilings'}
                            </Typography>
                            <Typography variant="h6" sx={{ mt: 2, fontWeight: 800 }}>{plan.priceCoins} Coins</Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                                {plan.periodDays} days · max servers {plan.maxServers || 'unlimited'}
                            </Typography>
                            <Stack direction="row" spacing={1}>
                                <Button variant="outlined" fullWidth onClick={() => openEditor(plan)}>Edit</Button>
                                <Button variant="outlined" color="error" fullWidth onClick={() => remove(plan.id)}>Delete</Button>
                            </Stack>
                        </Paper>
                    </Grid>
                ))}
            </Grid>

            <Dialog open={dialogOpen} onClose={closeEditor} fullWidth maxWidth="md">
                <DialogTitle>{editing ? 'Edit Revenue Plan' : 'Create Revenue Plan'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} fullWidth />
                        <TextField label="Description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} fullWidth multiline minRows={3} />
                        <Grid container spacing={2}>
                            {[
                                ['priceCoins', 'Price Coins'],
                                ['periodDays', 'Period Days'],
                                ['maxServers', 'Max Servers'],
                            ].map(([key, label]) => (
                                <Grid item xs={12} sm={6} md={4} key={key}>
                                    <TextField label={label} type="number" value={form[key]} onChange={(event) => setForm({ ...form, [key]: Number(event.target.value || 0) })} fullWidth />
                                </Grid>
                            ))}
                        </Grid>
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={4}><TextField label="Max RAM (GB)" type="number" value={form.maxMemoryGb} onChange={(event) => setForm({ ...form, maxMemoryGb: Number(event.target.value || 0) })} fullWidth /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Max CPU (cores)" type="number" value={form.maxCpuCores} onChange={(event) => setForm({ ...form, maxCpuCores: Number(event.target.value || 0) })} fullWidth /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Max Disk (GB)" type="number" value={form.maxDiskGb} onChange={(event) => setForm({ ...form, maxDiskGb: Number(event.target.value || 0) })} fullWidth /></Grid>
                        </Grid>
                        <FormControlLabel control={<Switch checked={form.enabled} onChange={(event) => setForm({ ...form, enabled: event.target.checked })} />} label="Enabled" />
                        <FormControlLabel control={<Switch checked={form.featured} onChange={(event) => setForm({ ...form, featured: event.target.checked })} />} label="Featured" />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={closeEditor}>Cancel</Button>
                    <Button onClick={save} variant="contained" disabled={saving}>
                        {saving ? <CircularProgress size={18} color="inherit" /> : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Stack>
    );
};

export default AdminRevenuePlans;
