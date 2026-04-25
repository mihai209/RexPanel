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

const blankDeal = {
    name: '',
    description: '',
    priceCoins: 0,
    imageUrl: '',
    stockTotal: 1,
    stockSold: 0,
    enabled: true,
    featured: false,
    resources: convertApiResourcesToEditor(),
};

const AdminStoreDeals = () => {
    const [deals, setDeals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(blankDeal);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const response = await client.get('/v1/admin/store/deals');
                setDeals(response.data.deals || []);
            } catch (error) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load store deals.' });
            } finally {
                setLoading(false);
            }
        };

        load();
    }, []);

    const openEditor = (deal = null) => {
        setEditing(deal);
        setForm(deal ? { ...blankDeal, ...deal, resources: convertApiResourcesToEditor(deal.resources || {}) } : blankDeal);
        setDialogOpen(true);
    };

    const closeEditor = () => {
        setDialogOpen(false);
        setEditing(null);
        setForm(blankDeal);
    };

    const save = async () => {
        setSaving(true);
        const payload = {
            ...form,
            resources: convertEditorResourcesToApi(form.resources),
        };
        try {
            const response = editing
                ? await client.put(`/v1/admin/store/deals/${editing.id}`, payload)
                : await client.post('/v1/admin/store/deals', payload);
            const next = response.data.deal;
            setDeals((current) => editing ? current.map((entry) => entry.id === next.id ? next : entry) : [...current, next]);
            closeEditor();
            setMessage({ type: 'success', text: response.data.message || 'Store deal saved.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save store deal.' });
        } finally {
            setSaving(false);
        }
    };

    const remove = async (dealId) => {
        try {
            await client.delete(`/v1/admin/store/deals/${dealId}`);
            setDeals((current) => current.filter((entry) => entry.id !== dealId));
            setMessage({ type: 'success', text: 'Store deal deleted.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete store deal.' });
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
                        <Typography variant="h5" sx={{ fontWeight: 800 }}>Store Deals</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
                            Deals control resource grants, stock, featured state, and wallet pricing.
                        </Typography>
                    </Box>
                    <Button variant="contained" onClick={() => openEditor()}>Create Deal</Button>
                </Stack>
            </Paper>
            <Grid container spacing={2}>
                {deals.map((deal) => (
                    <Grid item xs={12} md={6} lg={4} key={deal.id}>
                        <Paper elevation={0} sx={{ p: 3, height: '100%', borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>{deal.name}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{deal.description || 'No description.'}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 2 }}>{formatResourceList(deal.resources || {}) || 'No resources'}</Typography>
                            <Typography variant="h6" sx={{ mt: 2, fontWeight: 800 }}>{deal.priceCoins} Coins</Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                                {deal.status} · sold {deal.stockSold || 0} / {deal.stockTotal || 0} · remaining {deal.remainingStock || 0}
                            </Typography>
                            <Stack direction="row" spacing={1}>
                                <Button variant="outlined" fullWidth onClick={() => openEditor(deal)}>Edit</Button>
                                <Button variant="outlined" color="error" fullWidth onClick={() => remove(deal.id)}>Delete</Button>
                            </Stack>
                        </Paper>
                    </Grid>
                ))}
            </Grid>
            <Dialog open={dialogOpen} onClose={closeEditor} fullWidth maxWidth="md">
                <DialogTitle>{editing ? 'Edit Store Deal' : 'Create Store Deal'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} fullWidth />
                        <TextField label="Description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} fullWidth multiline minRows={3} />
                        <TextField label="Image URL" value={form.imageUrl} onChange={(event) => setForm({ ...form, imageUrl: event.target.value })} fullWidth />
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={4}><TextField label="Price Coins" type="number" fullWidth value={form.priceCoins} onChange={(event) => setForm({ ...form, priceCoins: Number(event.target.value || 0) })} /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Stock Total" type="number" fullWidth value={form.stockTotal} onChange={(event) => setForm({ ...form, stockTotal: Number(event.target.value || 0) })} /></Grid>
                            <Grid item xs={12} sm={4}><TextField label="Stock Sold" type="number" fullWidth value={form.stockSold} onChange={(event) => setForm({ ...form, stockSold: Number(event.target.value || 0) })} /></Grid>
                        </Grid>
                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={3}><TextField label="RAM (GB)" type="number" fullWidth value={form.resources.ramGb} onChange={(event) => setForm({ ...form, resources: { ...form.resources, ramGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="CPU (cores)" type="number" fullWidth value={form.resources.cpuCores} onChange={(event) => setForm({ ...form, resources: { ...form.resources, cpuCores: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Disk (GB)" type="number" fullWidth value={form.resources.diskGb} onChange={(event) => setForm({ ...form, resources: { ...form.resources, diskGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Swap (GB)" type="number" fullWidth value={form.resources.swapGb} onChange={(event) => setForm({ ...form, resources: { ...form.resources, swapGb: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Allocations" type="number" fullWidth value={form.resources.allocations} onChange={(event) => setForm({ ...form, resources: { ...form.resources, allocations: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Images" type="number" fullWidth value={form.resources.images} onChange={(event) => setForm({ ...form, resources: { ...form.resources, images: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Databases" type="number" fullWidth value={form.resources.databases} onChange={(event) => setForm({ ...form, resources: { ...form.resources, databases: Number(event.target.value || 0) } })} /></Grid>
                            <Grid item xs={12} sm={3}><TextField label="Packages" type="number" fullWidth value={form.resources.packages} onChange={(event) => setForm({ ...form, resources: { ...form.resources, packages: Number(event.target.value || 0) } })} /></Grid>
                        </Grid>
                        <FormControlLabel control={<Switch checked={form.enabled} onChange={(event) => setForm({ ...form, enabled: event.target.checked })} />} label="Enabled" />
                        <FormControlLabel control={<Switch checked={form.featured} onChange={(event) => setForm({ ...form, featured: event.target.checked })} />} label="Featured" />
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

export default AdminStoreDeals;
