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
import { convertApiResourcesToEditor, convertEditorResourcesToApi, formatResourceList } from '../../components/commerce/commerceUtils';

const blankCode = {
    code: '',
    name: '',
    description: '',
    rewardCoins: 0,
    maxUses: '',
    perUserLimit: '',
    expiresAt: '',
    enabled: true,
    rewards: convertApiResourcesToEditor(),
};

const AdminRedeemCodes = () => {
    const [codes, setCodes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(blankCode);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const response = await client.get('/v1/admin/store/redeem-codes');
                setCodes(response.data.codes || []);
            } catch (error) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load redeem codes.' });
            } finally {
                setLoading(false);
            }
        };

        load();
    }, []);

    const openEditor = (entry = null) => {
        setEditing(entry);
        setForm(entry ? {
            ...blankCode,
            ...entry,
            rewardCoins: entry.rewards?.coins || 0,
            perUserLimit: entry.perUserLimit ?? '',
            expiresAt: entry.expiresAtMs ? new Date(entry.expiresAtMs).toISOString().slice(0, 16) : '',
            rewards: convertApiResourcesToEditor(entry.rewards || {}),
        } : blankCode);
        setDialogOpen(true);
    };

    const closeEditor = () => {
        setDialogOpen(false);
        setEditing(null);
        setForm(blankCode);
    };

    const save = async () => {
        setSaving(true);
        const payload = {
            ...form,
            expiresAtMs: form.expiresAt ? new Date(form.expiresAt).getTime() : 0,
            rewards: {
                coins: Number(form.rewardCoins || 0),
                ...convertEditorResourcesToApi(form.rewards),
            },
        };
        try {
            const response = editing
                ? await client.put(`/v1/admin/store/redeem-codes/${editing.id}`, payload)
                : await client.post('/v1/admin/store/redeem-codes', payload);
            const next = response.data.code;
            setCodes((current) => editing ? current.map((entry) => entry.id === next.id ? next : entry) : [...current, next]);
            closeEditor();
            setMessage({ type: 'success', text: response.data.message || 'Redeem code saved.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save redeem code.' });
        } finally {
            setSaving(false);
        }
    };

    const remove = async (codeId) => {
        try {
            await client.delete(`/v1/admin/store/redeem-codes/${codeId}`);
            setCodes((current) => current.filter((entry) => entry.id !== codeId));
            setMessage({ type: 'success', text: 'Redeem code deleted.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete redeem code.' });
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
                        <Typography variant="h5" sx={{ fontWeight: 800 }}>Redeem Codes</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            Codes can credit wallets, grant resource inventory, expire, and enforce global or per-user limits.
                        </Typography>
                    </Box>
                    <Button variant="contained" onClick={() => openEditor()}>Create Code</Button>
                </Stack>
            </Paper>
            <Grid container spacing={2}>
                {codes.map((entry) => (
                    <Grid item xs={12} md={6} lg={4} key={entry.id}>
                        <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>{entry.name || entry.code}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.description || 'No description.'}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 2 }}>
                                +{entry.rewards?.coins || 0} Coins{formatResourceList(entry.rewards || {}) ? ` · ${formatResourceList(entry.rewards || {})}` : ''}
                            </Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                                {entry.status} · used {entry.usesCount || 0}{entry.maxUses ? ` / ${entry.maxUses}` : ''}{entry.perUserLimit ? ` · per user ${entry.perUserLimit}` : ''}{entry.remainingUses !== null ? ` · remaining ${entry.remainingUses}` : ''}
                            </Typography>
                            <Stack direction="row" spacing={1}>
                                <Button variant="outlined" fullWidth onClick={() => openEditor(entry)}>Edit</Button>
                                <Button variant="outlined" color="error" fullWidth onClick={() => remove(entry.id)}>Delete</Button>
                            </Stack>
                        </Paper>
                    </Grid>
                ))}
            </Grid>
            <Dialog open={dialogOpen} onClose={closeEditor} fullWidth maxWidth="md">
                <DialogTitle>{editing ? 'Edit Redeem Code' : 'Create Redeem Code'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField label="Code" value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} fullWidth />
                        <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} fullWidth />
                        <TextField label="Description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} fullWidth multiline minRows={3} />
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={4}><TextField label="Reward Coins" type="number" fullWidth value={form.rewardCoins} onChange={(event) => setForm({ ...form, rewardCoins: Number(event.target.value || 0) })} /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Max Uses" type="number" fullWidth value={form.maxUses} onChange={(event) => setForm({ ...form, maxUses: event.target.value })} helperText="Blank = unlimited" /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Per User Limit" type="number" fullWidth value={form.perUserLimit} onChange={(event) => setForm({ ...form, perUserLimit: event.target.value })} helperText="Blank = unlimited" /></Grid>
                        </Grid>
                        <TextField
                            label="Expires At"
                            type="datetime-local"
                            value={form.expiresAt}
                            onChange={(event) => setForm({ ...form, expiresAt: event.target.value })}
                            InputLabelProps={{ shrink: true }}
                            fullWidth
                        />
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={3}><TextField label="RAM (GB)" type="number" fullWidth value={form.rewards.ramGb} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, ramGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="CPU (cores)" type="number" fullWidth value={form.rewards.cpuCores} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, cpuCores: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Disk (GB)" type="number" fullWidth value={form.rewards.diskGb} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, diskGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Swap (GB)" type="number" fullWidth value={form.rewards.swapGb} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, swapGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Allocations" type="number" fullWidth value={form.rewards.allocations} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, allocations: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Images" type="number" fullWidth value={form.rewards.images} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, images: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Databases" type="number" fullWidth value={form.rewards.databases} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, databases: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Packages" type="number" fullWidth value={form.rewards.packages} onChange={(event) => setForm({ ...form, rewards: { ...form.rewards, packages: Number(event.target.value || 0) } })} /></Grid>
                        </Grid>
                        <FormControlLabel control={<Switch checked={form.enabled} onChange={(event) => setForm({ ...form, enabled: event.target.checked })} />} label="Enabled" />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={closeEditor}>Cancel</Button>
                    <Button onClick={save} variant="contained" disabled={saving}>{saving ? <CircularProgress size={18} color="inherit" /> : 'Save'}</Button>
                </DialogActions>
            </Dialog>
        </Stack>
    );
};

export default AdminRedeemCodes;
