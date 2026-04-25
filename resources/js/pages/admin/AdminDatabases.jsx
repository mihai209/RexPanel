import React, { useState, useEffect } from 'react';
import { 
    Box, Typography, Paper, Grid, Table, TableBody, TableCell, TableContainer, 
    TableHead, TableRow, IconButton, Button, Chip, CircularProgress, 
    Alert, Tooltip, Dialog, DialogTitle, DialogContent, 
    DialogActions, useTheme, alpha, Avatar, TextField, MenuItem,
    FormControl, FormLabel, RadioGroup, FormControlLabel, Radio
} from '@mui/material';
import { 
    Storage as StorageIcon, 
    Add as AddIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    Refresh as RefreshIcon,
    Dns as DnsIcon,
    LocationOn as LocationIcon,
    CheckCircle as CheckCircleIcon,
    Error as ErrorIcon,
    Settings as SettingsIcon,
    Link as LinkIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import client from '../../api/client';

const AdminDatabases = () => {
    const navigate = useNavigate();
    const theme = useTheme();
    
    const [hosts, setHosts] = useState([]);
    const [locations, setLocations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    
    // Modal State
    const [modalOpen, setModalOpen] = useState(false);
    const [editingHost, setEditingHost] = useState(null);
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    
    // Form State
    const [form, setForm] = useState({
        name: '',
        host: '',
        port: 3306,
        username: '',
        password: '',
        database: 'mysql',
        location_id: '',
        max_databases: 0,
        type: 'mysql'
    });

    const fetchHosts = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const [hostsRes, locsRes] = await Promise.all([
                client.get('/v1/admin/databases'),
                client.get('/v1/admin/locations')
            ]);
            setHosts(hostsRes.data);
            setLocations(locsRes.data);
            setError('');
        } catch (err) {
            if (!silent) setError('Failed to load database hosts.');
        } finally {
            if (!silent) setLoading(false);
        }
    };

    useEffect(() => {
        fetchHosts();
    }, []);

    const handleOpenModal = (host = null) => {
        if (host) {
            setEditingHost(host);
            setForm({
                name: host.name,
                host: host.host,
                port: host.port,
                username: host.username,
                password: '', // Don't show password for security
                database: host.database,
                location_id: host.location_id || host.locationId,
                max_databases: host.max_databases,
                type: host.type || 'mysql'
            });
        } else {
            setEditingHost(null);
            setForm({
                name: '',
                host: '',
                port: 3306,
                username: '',
                password: '',
                database: 'mysql',
                location_id: locations[0]?.id || '',
                max_databases: 0,
                type: 'mysql'
            });
        }
        setTestResult(null);
        setModalOpen(true);
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            if (editingHost) {
                await client.put(`/v1/admin/databases/${editingHost.id}`, form);
            } else {
                await client.post('/v1/admin/databases', form);
            }
            setModalOpen(false);
            fetchHosts();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to save database host. Check your inputs.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Are you sure you want to delete this database host?')) return;
        try {
            await client.delete(`/v1/admin/databases/${id}`);
            fetchHosts();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to delete host.');
        }
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const res = await client.post('/v1/admin/databases/test', form);
            setTestResult({ success: true, message: res.data.message });
        } catch (err) {
            setTestResult({ success: false, message: err.response?.data?.message || 'Connection failed.' });
        } finally {
            setTesting(false);
        }
    };

    const cardStyle = {
        p: 0,
        borderRadius: 3,
        border: '1px solid rgba(255,255,255,0.05)',
        bgcolor: alpha(theme.palette.background.paper, 0.6),
        backdropFilter: 'blur(10px)',
        overflow: 'hidden',
        height: '100%'
    };

    const headerStyle = {
        px: 2,
        py: 1.5,
        borderBottom: '1px solid rgba(255,255,255,0.05)',
        bgcolor: alpha(theme.palette.primary.main, 0.05),
        display: 'flex',
        alignItems: 'center',
        gap: 1.5
    };

    const inputStyle = {
        '& .MuiOutlinedInput-root': {
            borderRadius: 2,
            bgcolor: 'rgba(0,0,0,0.2)',
            transition: '0.2s',
            '&:hover': { bgcolor: 'rgba(0,0,0,0.3)' },
            '&.Mui-focused': { bgcolor: 'rgba(0,0,0,0.4)' }
        }
    };

    return (
        <Box sx={{ maxWidth: '1400px', mx: 'auto' }}>
            {/* Header Section */}
            <Box sx={{ mb: 4, display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: 3 }}>
                <Box>
                    <Typography variant="h4" sx={{ fontWeight: 900, color: 'text.primary', letterSpacing: '-0.02em', mb: 0.5 }}>
                        Database Management
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <StorageIcon sx={{ fontSize: 16 }} /> {hosts.length} configured database hosts
                    </Typography>
                </Box>
                <Box sx={{ display: 'flex', gap: 2 }}>
                    <Tooltip title="Refresh List">
                        <IconButton onClick={() => fetchHosts()} disabled={loading} sx={{ border: '1px solid rgba(255,255,255,0.08)', borderRadius: 2 }}>
                            <RefreshIcon sx={{ animation: loading ? 'spin 1s linear infinite' : 'none', '@keyframes spin': { from: { rotate: 0 }, to: { rotate: 360 } } }} />
                        </IconButton>
                    </Tooltip>
                    <Button 
                        variant="contained" 
                        startIcon={<AddIcon />}
                        onClick={() => handleOpenModal()}
                        sx={{ borderRadius: 2.5, px: 3, fontWeight: 800, boxShadow: `0 8px 16px ${alpha(theme.palette.primary.main, 0.2)}` }}
                    >
                        Create Host
                    </Button>
                </Box>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3, borderRadius: 3 }}>{error}</Alert>}

            <TableContainer component={Paper} sx={{ bgcolor: alpha(theme.palette.background.paper, 0.4), backdropFilter: 'blur(10px)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 4, overflow: 'hidden' }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { py: 2, bgcolor: alpha(theme.palette.background.paper, 0.5), fontWeight: 800, fontSize: '0.7rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 1, borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                            <TableCell>Host Details</TableCell>
                            <TableCell>Location</TableCell>
                            <TableCell>Usage</TableCell>
                            <TableCell>Endpoint</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        <AnimatePresence mode="popLayout">
                            {loading && hosts.length === 0 ? (
                                <TableRow><TableCell colSpan={5} align="center" sx={{ py: 10 }}><CircularProgress size={40} thickness={4} /></TableCell></TableRow>
                            ) : hosts.length > 0 ? (
                                hosts.map((host, idx) => (
                                    <TableRow 
                                        key={host.id} 
                                        component={motion.tr}
                                        initial={{ opacity: 0, x: -10 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        transition={{ delay: idx * 0.05 }}
                                        sx={{ transition: '0.2s', '&:hover': { bgcolor: alpha(theme.palette.primary.main, 0.02) }, '& td': { borderBottom: '1px solid rgba(255,255,255,0.03)' } }}
                                    >
                                        <TableCell>
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                                <Avatar sx={{ bgcolor: 'rgba(255,255,255,0.05)', borderRadius: 2, color: 'primary.main' }}><DnsIcon /></Avatar>
                                                <Box>
                                                    <Typography variant="body1" sx={{ fontWeight: 800 }}>
                                                        {host.name}
                                                    </Typography>
                                                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>User: {host.username}</Typography>
                                                </Box>
                                            </Box>
                                        </TableCell>
                                        <TableCell>
                                            <Chip label={host.location?.short_name || host.location?.name || 'Unknown'} size="small" variant="outlined" icon={<LocationIcon sx={{ fontSize: '12px !important' }} />} sx={{ fontWeight: 700, borderRadius: 1.5, borderColor: 'rgba(255,255,255,0.1)' }} />
                                            {host.location?.image_url && <Avatar src={host.location.image_url} sx={{ width: 24, height: 24, ml: 1 }} />}
                                        </TableCell>
                                        <TableCell>
                                            <Typography variant="body2" sx={{ fontWeight: 800 }}>
                                                {host.database_count} <Typography variant="caption" sx={{ color: 'text.disabled' }}>/ {host.max_databases || '∞'}</Typography>
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            <Typography variant="caption" sx={{ color: 'text.disabled', fontFamily: 'monospace' }}>{host.host}:{host.port}</Typography>
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                <IconButton size="small" onClick={() => handleOpenModal(host)} sx={{ bgcolor: 'rgba(255,255,255,0.03)', borderRadius: 2 }}><EditIcon fontSize="small" /></IconButton>
                                                <IconButton size="small" color="error" onClick={() => handleDelete(host.id)} sx={{ bgcolor: alpha(theme.palette.error.main, 0.05), borderRadius: 2 }}><DeleteIcon fontSize="small" /></IconButton>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={5} align="center" sx={{ py: 15 }}>
                                        <Box sx={{ opacity: 0.2 }}>
                                            <StorageIcon sx={{ fontSize: 80, mb: 2 }} />
                                            <Typography variant="h5" sx={{ fontWeight: 900 }}>No Hosts</Typography>
                                            <Typography variant="body2">Add a MySQL/MariaDB host to start provisioning databases.</Typography>
                                        </Box>
                                    </TableCell>
                                </TableRow>
                            )}
                        </AnimatePresence>
                    </TableBody>
                </Table>
            </TableContainer>

            {/* Create/Edit Modal */}
            <Dialog open={modalOpen} onClose={() => !saving && setModalOpen(false)} maxWidth="md" fullWidth PaperProps={{ sx: { borderRadius: 4, bgcolor: '#111827', backgroundImage: 'none' } }}>
                <DialogTitle sx={{ fontWeight: 900, fontSize: '1.5rem', p: 3, pb: 1 }}>
                    {editingHost ? 'Edit Database Host' : 'Create Database Host'}
                </DialogTitle>
                <DialogContent sx={{ p: 3 }}>
                    <Grid container spacing={3}>
                        <Grid item xs={12} md={6}>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>Friendly Name</Typography>
                                    <TextField fullWidth size="small" sx={inputStyle} value={form.name} onChange={e => setForm({...form, name: e.target.value})} placeholder="Production MySQL" />
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>IP / Domain</Typography>
                                    <TextField fullWidth size="small" sx={inputStyle} value={form.host} onChange={e => setForm({...form, host: e.target.value})} placeholder="127.0.0.1" />
                                </Box>
                                <Grid container spacing={2}>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>Port</Typography>
                                        <TextField fullWidth size="small" type="number" sx={inputStyle} value={form.port} onChange={e => setForm({...form, port: e.target.value})} />
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>DB Name</Typography>
                                        <TextField fullWidth size="small" sx={inputStyle} value={form.database} onChange={e => setForm({...form, database: e.target.value})} placeholder="mysql" />
                                    </Grid>
                                </Grid>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>Host Type</Typography>
                                    <TextField select fullWidth size="small" sx={inputStyle} value={form.type} onChange={e => setForm({...form, type: e.target.value})}>
                                        <MenuItem value="mysql">MySQL</MenuItem>
                                        <MenuItem value="mariadb">MariaDB</MenuItem>
                                        <MenuItem value="postgres">PostgreSQL</MenuItem>
                                    </TextField>
                                </Box>
                            </Box>
                        </Grid>
                        <Grid item xs={12} md={6}>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>DB User</Typography>
                                    <TextField fullWidth size="small" sx={inputStyle} value={form.username} onChange={e => setForm({...form, username: e.target.value})} />
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>DB Password</Typography>
                                    <TextField fullWidth size="small" type="password" sx={inputStyle} value={form.password} onChange={e => setForm({...form, password: e.target.value})} placeholder={editingHost ? "(Unchanged)" : ""} />
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>Location</Typography>
                                    <TextField select fullWidth size="small" sx={inputStyle} value={form.location_id} onChange={e => setForm({...form, location_id: e.target.value})}>
                                        {locations.map(loc => <MenuItem key={loc.id} value={loc.id}>{loc.short_name || loc.name}</MenuItem>)}
                                    </TextField>
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 1, display: 'block' }}>Max Databases (0 = Unlimited)</Typography>
                                    <TextField fullWidth size="small" type="number" sx={inputStyle} value={form.max_databases} onChange={e => setForm({...form, max_databases: e.target.value})} />
                                </Box>
                            </Box>
                        </Grid>
                    </Grid>

                    {testResult && (
                        <Alert severity={testResult.success ? 'success' : 'error'} sx={{ mt: 3, borderRadius: 2 }}>
                            {testResult.message}
                        </Alert>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3, pt: 1, gap: 1 }}>
                    <Button 
                        onClick={handleTestConnection} 
                        disabled={testing || saving}
                        startIcon={testing ? <CircularProgress size={16} color="inherit" /> : <RefreshIcon />}
                        sx={{ fontWeight: 800, color: 'text.secondary' }}
                    >
                        Test Connection
                    </Button>
                    <Box sx={{ flexGrow: 1 }} />
                    <Button onClick={() => setModalOpen(false)} disabled={saving} sx={{ fontWeight: 800, color: 'text.secondary' }}>Cancel</Button>
                    <Button 
                        onClick={handleSave} 
                        variant="contained" 
                        disabled={saving}
                        sx={{ fontWeight: 900, borderRadius: 2, px: 4 }}
                    >
                        {saving ? <CircularProgress size={24} color="inherit" /> : 'Save Host'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AdminDatabases;
