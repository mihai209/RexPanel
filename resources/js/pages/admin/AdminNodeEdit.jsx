import React, { useState, useEffect } from 'react';
import { 
    Box, Typography, Paper, Grid, TextField, Button, IconButton,
    Alert, CircularProgress, MenuItem, Switch, FormControlLabel,
    InputAdornment, Dialog, DialogTitle, DialogContent, DialogActions,
    Snackbar, Divider
} from '@mui/material';
import { 
    ArrowBack as BackIcon, 
    Save as SaveIcon,
    VpnKey as TokenIcon,
    Dns as DnsIcon,
    LocationOn as LocationIcon,
    Storage as StorageIcon,
    Language as WebIcon,
    Settings as SettingsIcon,
    Autorenew as RotateIcon,
    Warning as WarningIcon
} from '@mui/icons-material';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';

const AdminNodeEdit = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [locations, setLocations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [rotating, setRotating] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [snackbar, setSnackbar] = useState('');
    const [rotateDialog, setRotateDialog] = useState(false);

    const [formData, setFormData] = useState({
        name: '', location_id: '', fqdn: '',
        daemon_port: 8080, daemon_sftp_port: 2022, daemon_token: '',
        is_public: true, maintenance_mode: false,
        memory_limit: 8192, memory_overallocate: 0,
        disk_limit: 51200, disk_overallocate: 0,
        daemon_base: '/var/lib/ra-panel'
    });

    useEffect(() => {
        const fetchData = async () => {
            try {
                const [locRes, nodeRes] = await Promise.all([
                    client.get('/v1/admin/locations'),
                    client.get(`/v1/admin/nodes/${id}`)
                ]);
                setLocations(locRes.data);
                const node = nodeRes.data.node ?? nodeRes.data;
                setFormData({
                    name: node.name,
                    location_id: node.location_id,
                    fqdn: node.fqdn,
                    daemon_port: node.daemon_port,
                    daemon_sftp_port: node.daemon_sftp_port,
                    daemon_token: node.daemon_token,
                    is_public: node.is_public,
                    maintenance_mode: node.maintenance_mode,
                    memory_limit: node.memory_limit,
                    memory_overallocate: node.memory_overallocate,
                    disk_limit: node.disk_limit,
                    disk_overallocate: node.disk_overallocate,
                    daemon_base: node.daemon_base
                });
            } catch {
                setError('Failed to load node data.');
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, [id]);

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await client.put(`/v1/admin/nodes/${id}`, formData);
            setSnackbar('Node updated successfully!');
            setTimeout(() => navigate(`/admin/nodes/${id}/overview`), 1200);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update node.');
        } finally {
            setSaving(false);
        }
    };

    const handleRotateToken = async () => {
        setRotating(true);
        setRotateDialog(false);
        try {
            const res = await client.post(`/v1/admin/nodes/${id}/regenerate-token`);
            // Update the shown token in the form
            setFormData(prev => ({ ...prev, daemon_token: res.data.token }));
            setSnackbar('Token rotated! Connector has been disconnected and must be restarted.');
        } catch {
            setError('Failed to rotate token.');
        } finally {
            setRotating(false);
        }
    };

    if (loading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}><CircularProgress /></Box>;

    return (
        <Box sx={{ maxWidth: '1400px', mx: 'auto' }}>
            {/* Header */}
            <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    <IconButton onClick={() => navigate(`/admin/nodes/${id}/overview`)} sx={{ color: 'text.secondary', bgcolor: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.08)' }}>
                        <BackIcon />
                    </IconButton>
                    <Box>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.3 }}>
                            <Typography variant="body2" sx={{ color: 'text.secondary', cursor: 'pointer', '&:hover': { color: 'primary.main' } }} onClick={() => navigate('/admin/nodes')}>Nodes</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>/</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', cursor: 'pointer', '&:hover': { color: 'primary.main' } }} onClick={() => navigate(`/admin/nodes/${id}/overview`)}>{formData.name}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>/</Typography>
                            <Typography variant="body2" sx={{ color: 'primary.main', fontWeight: 700 }}>Edit</Typography>
                        </Box>
                        <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.02em' }}>Edit Node</Typography>
                    </Box>
                </Box>
                <Button
                    variant="contained"
                    startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />}
                    disabled={saving}
                    onClick={handleUpdate}
                    sx={{ borderRadius: 2, fontWeight: 700, px: 4 }}
                >
                    {saving ? 'Saving…' : 'Save Changes'}
                </Button>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3, borderRadius: 2 }} onClose={() => setError('')}>{error}</Alert>}

            <form onSubmit={handleUpdate}>
                <Grid container spacing={3}>
                    {/* ─── LEFT: Basic Details ─── */}
                    <Grid item xs={12} md={8}>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                            {/* Node Details */}
                            <Paper sx={{ p: 0, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                                <Box sx={{ px: 3, py: 2, bgcolor: 'rgba(255,255,255,0.02)', borderBottom: '1px solid rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <DnsIcon sx={{ fontSize: 18, color: 'primary.main' }} />
                                    <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>Node Details</Typography>
                                </Box>
                                <Box sx={{ p: 3, display: 'flex', flexDirection: 'column', gap: 3 }}>
                                    <Grid container spacing={2}>
                                        <Grid item xs={12} sm={8}>
                                            <TextField fullWidth label="Name" value={formData.name} onChange={e => setFormData({ ...formData, name: e.target.value })} required size="small" />
                                        </Grid>
                                        <Grid item xs={12} sm={4}>
                                            <TextField fullWidth select label="Location" value={formData.location_id} onChange={e => setFormData({ ...formData, location_id: e.target.value })} required size="small"
                                                InputProps={{ startAdornment: <InputAdornment position="start"><LocationIcon sx={{ fontSize: 16, color: 'primary.main', opacity: 0.7 }} /></InputAdornment> }}>
                                                {locations.map(loc => <MenuItem key={loc.id} value={loc.id}>{loc.name}</MenuItem>)}
                                            </TextField>
                                        </Grid>
                                    </Grid>
                                    <TextField fullWidth label="FQDN / IP Address" value={formData.fqdn} onChange={e => setFormData({ ...formData, fqdn: e.target.value })} required size="small"
                                        InputProps={{ startAdornment: <InputAdornment position="start"><WebIcon sx={{ fontSize: 16, color: 'primary.main', opacity: 0.7 }} /></InputAdornment> }} />
                                    <Grid container spacing={2}>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="Daemon Port" value={formData.daemon_port} onChange={e => setFormData({ ...formData, daemon_port: parseInt(e.target.value) })} required size="small" />
                                        </Grid>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="SFTP Port" value={formData.daemon_sftp_port} onChange={e => setFormData({ ...formData, daemon_sftp_port: parseInt(e.target.value) })} required size="small" />
                                        </Grid>
                                    </Grid>
                                    <TextField fullWidth label="Data Directory" value={formData.daemon_base} onChange={e => setFormData({ ...formData, daemon_base: e.target.value })} required size="small"
                                        InputProps={{ startAdornment: <InputAdornment position="start"><StorageIcon sx={{ fontSize: 16, color: 'primary.main', opacity: 0.7 }} /></InputAdornment> }} />
                                </Box>
                            </Paper>

                            {/* Resource Limits */}
                            <Paper sx={{ p: 0, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                                <Box sx={{ px: 3, py: 2, bgcolor: 'rgba(255,255,255,0.02)', borderBottom: '1px solid rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <SettingsIcon sx={{ fontSize: 18, color: 'primary.main' }} />
                                    <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>Resource Limits</Typography>
                                </Box>
                                <Box sx={{ p: 3 }}>
                                    <Grid container spacing={2}>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="Memory Limit" value={formData.memory_limit} onChange={e => setFormData({ ...formData, memory_limit: parseInt(e.target.value) })} required size="small"
                                                InputProps={{ endAdornment: <InputAdornment position="end">MiB</InputAdornment> }} />
                                        </Grid>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="Memory Over-Alloc" value={formData.memory_overallocate} onChange={e => setFormData({ ...formData, memory_overallocate: parseInt(e.target.value) })} required size="small"
                                                InputProps={{ endAdornment: <InputAdornment position="end">%</InputAdornment> }} />
                                        </Grid>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="Disk Limit" value={formData.disk_limit} onChange={e => setFormData({ ...formData, disk_limit: parseInt(e.target.value) })} required size="small"
                                                InputProps={{ endAdornment: <InputAdornment position="end">MiB</InputAdornment> }} />
                                        </Grid>
                                        <Grid item xs={6}>
                                            <TextField fullWidth type="number" label="Disk Over-Alloc" value={formData.disk_overallocate} onChange={e => setFormData({ ...formData, disk_overallocate: parseInt(e.target.value) })} required size="small"
                                                InputProps={{ endAdornment: <InputAdornment position="end">%</InputAdornment> }} />
                                        </Grid>
                                    </Grid>
                                </Box>
                            </Paper>
                        </Box>
                    </Grid>

                    {/* ─── RIGHT: Settings & Token ─── */}
                    <Grid item xs={12} md={4}>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                            {/* Node Flags */}
                            <Paper sx={{ p: 0, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                                <Box sx={{ px: 3, py: 2, bgcolor: 'rgba(255,255,255,0.02)', borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                                    <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>Settings</Typography>
                                </Box>
                                <Box sx={{ p: 2, display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                    <FormControlLabel
                                        control={<Switch checked={formData.is_public} onChange={e => setFormData({ ...formData, is_public: e.target.checked })} color="primary" size="small" />}
                                        label={<Box><Typography variant="body2" sx={{ fontWeight: 700 }}>Public Node</Typography><Typography variant="caption" sx={{ color: 'text.secondary' }}>Allow auto-deployment</Typography></Box>}
                                        sx={{ mx: 0, py: 1, borderBottom: '1px solid rgba(255,255,255,0.04)' }}
                                    />
                                    <FormControlLabel
                                        control={<Switch checked={formData.maintenance_mode} onChange={e => setFormData({ ...formData, maintenance_mode: e.target.checked })} color="warning" size="small" />}
                                        label={<Box><Typography variant="body2" sx={{ fontWeight: 700 }}>Maintenance Mode</Typography><Typography variant="caption" sx={{ color: 'text.secondary' }}>Block new deployments</Typography></Box>}
                                        sx={{ mx: 0, pt: 1 }}
                                    />
                                </Box>
                            </Paper>

                            {/* Token */}
                            <Paper sx={{ p: 0, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                                <Box sx={{ px: 3, py: 2, bgcolor: 'rgba(255,255,255,0.02)', borderBottom: '1px solid rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <TokenIcon sx={{ fontSize: 18, color: 'primary.main' }} />
                                    <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>Authentication Token</Typography>
                                </Box>
                                <Box sx={{ p: 2 }}>
                                    <Box sx={{ p: 1.5, borderRadius: 1.5, bgcolor: 'rgba(0,0,0,0.3)', border: '1px solid rgba(255,255,255,0.07)', fontFamily: 'monospace', fontSize: '0.75rem', color: 'text.secondary', wordBreak: 'break-all', mb: 2 }}>
                                        {formData.daemon_token}
                                    </Box>
                                    <Button
                                        fullWidth
                                        variant="outlined"
                                        color="warning"
                                        startIcon={rotating ? <CircularProgress size={16} color="inherit" /> : <RotateIcon />}
                                        disabled={rotating}
                                        onClick={() => setRotateDialog(true)}
                                        sx={{ fontWeight: 700 }}
                                    >
                                        {rotating ? 'Rotating…' : 'Rotate Token'}
                                    </Button>
                                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mt: 1 }}>
                                        Rotating the token will immediately disconnect the live connector. You'll need to update config.json and restart it.
                                    </Typography>
                                </Box>
                            </Paper>
                        </Box>
                    </Grid>
                </Grid>
            </form>

            {/* Rotate Token Confirm Dialog */}
            <Dialog open={rotateDialog} onClose={() => setRotateDialog(false)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1.5, fontWeight: 800 }}>
                    <WarningIcon color="warning" /> Rotate Authentication Token?
                </DialogTitle>
                <DialogContent>
                    <Alert severity="warning" sx={{ mb: 2 }}>
                        This will <strong>immediately disconnect</strong> the live connector. You must update <code>config.json</code> on the node and restart the daemon.
                    </Alert>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        The old token will be invalidated instantly and cannot be undone.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 2.5 }}>
                    <Button onClick={() => setRotateDialog(false)} sx={{ color: 'text.secondary' }}>Cancel</Button>
                    <Button onClick={handleRotateToken} variant="contained" color="warning" sx={{ fontWeight: 700 }}>
                        Yes, Rotate Token
                    </Button>
                </DialogActions>
            </Dialog>

            <Snackbar open={!!snackbar} autoHideDuration={4000} onClose={() => setSnackbar('')} message={snackbar} />
        </Box>
    );
};

export default AdminNodeEdit;
