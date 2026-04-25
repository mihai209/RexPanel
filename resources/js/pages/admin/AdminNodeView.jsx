import React, { useState, useEffect, useMemo } from 'react';
import { 
    Box, Typography, Paper, Tabs, Tab, Grid, Button, IconButton, 
    Alert, CircularProgress, LinearProgress, Table, TableBody, 
    TableCell, TableHead, TableRow, Chip, Tooltip, Snackbar,
    Avatar, useTheme, alpha, Dialog, DialogTitle, DialogContent, DialogActions,
    Divider, TextField, Checkbox, MenuItem, InputAdornment, FormControlLabel, Radio, RadioGroup,
    Menu
} from '@mui/material';
import { 
    Settings as SettingsIcon,
    ContentCopy as CopyIcon,
    Storage as StorageIcon,
    Memory as MemoryIcon,
    Dns as ServerIcon,
    ArrowForward as ViewIcon,
    Favorite as HeartIcon,
    Refresh as RefreshIcon,
    Info as InfoIcon,
    Code as CodeIcon,
    Delete as DeleteIcon,
    MoreVert as MoreIcon,
    Save as SaveIcon,
    Key as KeyIcon,
    Sync as SyncIcon,
    Warning as WarningIcon
} from '@mui/icons-material';
import { useNavigate, useParams, useLocation, Link, Routes, Route, Navigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import client from '../../api/client';

const AdminNodeView = () => {
    const { id } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const theme = useTheme();
    
    const tabMap = ['overview', 'settings', 'configuration', 'allocations', 'servers'];
    const currentTabPath = location.pathname.split('/').pop();
    const activeTab = tabMap.indexOf(currentTabPath) !== -1 ? tabMap.indexOf(currentTabPath) : 0;

    const [data, setData] = useState(null);
    const [config, setConfig] = useState(null);
    const [allocations, setAllocations] = useState([]);
    const [locations, setLocations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState('');
    const [snackbar, setSnackbar] = useState('');
    const [showToken, setShowToken] = useState(false);
    const [multiPanelConfig, setMultiPanelConfig] = useState(true);
    const [deleteDialog, setDeleteDialog] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [updating, setUpdating] = useState(false);

    // Allocation Specific States
    const [selectedAllocations, setSelectedAllocations] = useState([]);
    const [massActionAnchor, setMassActionAnchor] = useState(null);
    const [confirmAllocDelete, setConfirmAllocDelete] = useState(null); 
    const [bulkDeleting, setBulkDeleting] = useState(false);

    // Forms
    const [settingsForm, setSettingsForm] = useState({});
    const [allocForm, setAllocForm] = useState({ ip: '', alias: '', ports: '' });
    const [creatingAlloc, setCreatingAlloc] = useState(false);

    const fetchData = async (isRefresh = false) => {
        if (isRefresh) setRefreshing(true);
        try {
            const [res, configRes, allocRes, locRes] = await Promise.all([
                client.get(`/v1/admin/nodes/${id}`),
                client.get(`/v1/admin/nodes/${id}/configuration`),
                client.get(`/v1/admin/nodes/${id}/allocations`),
                client.get('/v1/admin/locations')
            ]);
            setData(res.data);
            setConfig(configRes.data);
            setAllocations(allocRes.data);
            setLocations(locRes.data);
            setSettingsForm(res.data.node);
            setError('');
        } catch (err) {
            setError('Failed to load node data.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchData();
        const interval = setInterval(() => fetchData(true), 10000);
        return () => clearInterval(interval);
    }, [id]);

    const singlePanelConfig = useMemo(() => {
        if (!config) {
            return null;
        }

        const currentPanel = Array.isArray(config.panels) && config.panels.length > 0
            ? config.panels[0]
            : null;

        return {
            ...config,
            panels: currentPanel ? [currentPanel] : [],
        };
    }, [config]);

    const displayedConfig = useMemo(() => {
        if (!config) {
            return null;
        }

        return multiPanelConfig ? config : singlePanelConfig;
    }, [config, multiPanelConfig, singlePanelConfig]);

    const autoConfigCommand = useMemo(() => {
        if (!displayedConfig) {
            return '';
        }

        if (typeof window === 'undefined' || typeof window.btoa !== 'function') {
            return '';
        }

        const encodedConfig = window.btoa(unescape(encodeURIComponent(JSON.stringify(displayedConfig))));

        return `connector-go --set-config '${encodedConfig}' --config-path /etc/ra-panel/config.json`;
    }, [displayedConfig]);

    const handleCopy = (text) => {
        navigator.clipboard.writeText(text);
        setSnackbar('Copied to clipboard!');
    };

    const handleUpdateSettings = async (e) => {
        e.preventDefault();
        setUpdating(true);
        try {
            await client.put(`/v1/admin/nodes/${id}`, settingsForm);
            setSnackbar('Node settings updated successfully.');
            fetchData(true);
        } catch (err) {
            alert('Failed to update node.');
        } finally {
            setUpdating(false);
        }
    };

    const handleRegenerateToken = async () => {
        try {
            await client.post(`/v1/admin/nodes/${id}/regenerate-token`);
            setSnackbar('Token regenerated successfully.');
            fetchData(true);
        } catch (err) {
            alert('Failed to regenerate token.');
        }
    };

    const handleCreateAllocations = async (e) => {
        e.preventDefault();
        setCreatingAlloc(true);
        try {
            await client.post(`/v1/admin/nodes/${id}/allocations`, allocForm);
            setAllocForm({ ...allocForm, ports: '' });
            setSnackbar('Allocations created.');
            fetchData(true);
        } catch (err) {
            alert('Failed to create allocations.');
        } finally {
            setCreatingAlloc(false);
        }
    };

    const handleDeleteAllocation = async () => {
        if (!confirmAllocDelete) return;
        setBulkDeleting(true);
        try {
            if (confirmAllocDelete === 'bulk') {
                await Promise.all(selectedAllocations.map(aid => 
                    client.delete(`/v1/admin/nodes/${id}/allocations/${aid}`).catch(() => null)
                ));
                setSelectedAllocations([]);
                setSnackbar(`Successfully processed bulk deletion.`);
            } else {
                await client.delete(`/v1/admin/nodes/${id}/allocations/${confirmAllocDelete}`);
                setSnackbar('Allocation deleted.');
            }
            fetchData(true);
        } catch (err) {
            alert('Failed to delete allocation.');
        } finally {
            setBulkDeleting(false);
            setConfirmAllocDelete(null);
        }
    };

    const toggleSelection = (allocId) => {
        setSelectedAllocations(prev => 
            prev.includes(allocId) ? prev.filter(i => i !== allocId) : [...prev, allocId]
        );
    };

    const toggleSelectAll = () => {
        if (selectedAllocations.length === allocations.length) {
            setSelectedAllocations([]);
        } else {
            setSelectedAllocations(allocations.map(a => a.id));
        }
    };

    if (loading) return (
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', py: 20, gap: 2 }}>
            <CircularProgress size={40} thickness={4} />
            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 800 }}>LOADING INFRASTRUCTURE...</Typography>
        </Box>
    );

    if (!data?.node) return <Alert severity="error" sx={{ m: 2 }}>Node not found.</Alert>;

    const { node, allocated, servers, system } = data;
    const memPercent = node.memory_limit > 0 ? Math.min((allocated.memory / node.memory_limit) * 100, 100) : 0;
    const diskPercent = node.disk_limit > 0 ? Math.min((allocated.disk / node.disk_limit) * 100, 100) : 0;

    // AESTHETIC STYLES
    const cardStyle = {
        borderRadius: 1,
        bgcolor: '#232b35',
        border: '1px solid rgba(255,255,255,0.05)',
        overflow: 'hidden',
        boxShadow: '0 2px 4px rgba(0,0,0,0.2)'
    };

    const headerStyle = {
        px: 2, py: 1.5,
        bgcolor: '#232b35',
        borderTop: '2px solid #57c7ff',
        borderBottom: '1px solid rgba(255,255,255,0.05)'
    };

    const inputStyle = {
        '& .MuiOutlinedInput-root': {
            borderRadius: 1,
            bgcolor: '#1a2028',
            '& fieldset': { borderColor: 'rgba(255,255,255,0.1)' },
            '&:hover fieldset': { borderColor: 'rgba(255,255,255,0.2)' },
            '&.Mui-focused fieldset': { borderColor: '#57c7ff' }
        },
        '& .MuiInputBase-input': { fontSize: '0.85rem', py: 1.2 },
        '& .MuiInputLabel-root': { color: '#8e99a3', fontWeight: 700, fontSize: '0.8rem' },
        '& .MuiFormHelperText-root': { color: '#6b7280', fontSize: '0.65rem', lineHeight: 1.2, mt: 0.5 }
    };

    const handleTabChange = (event, newValue) => {
        navigate(`/admin/nodes/${id}/${tabMap[newValue]}`);
    };

    return (
        <Box sx={{ maxWidth: '1400px', mx: 'auto', pb: 5 }}>
            {/* Header */}
            <Box sx={{ mb: 4 }}>
                <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.02em', mb: 0.5 }}>{node.name}</Typography>
                <Typography variant="body2" sx={{ color: 'text.secondary', display: 'flex', alignItems: 'center', gap: 1 }}>
                    {activeTab === 0 && 'A quick overview of your node.'}
                    {activeTab === 1 && 'Configure your node settings.'}
                    {activeTab === 2 && 'Configuration file for your node daemon.'}
                    {activeTab === 3 && 'Control allocations available for servers on this node.'}
                    {activeTab === 4 && 'Servers currently hosted on this node.'}
                    <Box sx={{ ml: 2, display: 'flex', alignItems: 'center', gap: 0.5, fontSize: '0.7rem', color: 'text.disabled' }}>
                        Admin &gt; Nodes &gt; {node.name} &gt; <span style={{ color: '#57c7ff' }}>{tabMap[activeTab].charAt(0).toUpperCase() + tabMap[activeTab].slice(1)}</span>
                    </Box>
                </Typography>
            </Box>

            {/* Tabs */}
            <Tabs 
                value={activeTab} 
                onChange={handleTabChange} 
                sx={{ 
                    mb: 4, bgcolor: alpha('#232b35', 0.8), borderRadius: 1, px: 1,
                    '& .MuiTab-root': { fontWeight: 800, fontSize: '0.8rem', color: '#8e99a3', minHeight: 48, '&.Mui-selected': { color: '#57c7ff' } },
                    '& .MuiTabs-indicator': { bgcolor: '#57c7ff', height: 3 }
                }}
            >
                <Tab label="About" />
                <Tab label="Settings" />
                <Tab label="Configuration" />
                <Tab label="Allocations" />
                <Tab label="Servers" />
            </Tabs>

            <AnimatePresence mode="wait">
                <motion.div key={activeTab} initial={{ opacity: 0, y: 5 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.15 }}>
                    <Routes>
                        <Route path="overview" element={
                            <Grid container spacing={3}>
                                <Grid item xs={12} md={8}>
                                    <Paper sx={cardStyle}>
                                        <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff' }}>Information</Typography></Box>
                                        <Box sx={{ p: 0 }}>
                                            {[
                                                { l: 'Daemon Version', v: '1.0.0 (Latest: 1.0.0)' },
                                                { l: 'System Information', v: `${system?.type || 'Linux'} (${system?.arch || 'amd64'}) ${system?.release || ''}` },
                                                { l: 'Total CPU Cores', v: system?.cpus || 1 }
                                            ].map((item, i) => (
                                                <Box key={i} sx={{ px: 3, py: 1.5, display: 'flex', justifyContent: 'space-between', borderBottom: i < 2 ? '1px solid rgba(255,255,255,0.02)' : 'none' }}>
                                                    <Typography variant="body2" sx={{ color: '#8e99a3', fontWeight: 700 }}>{item.l}</Typography>
                                                    <Typography variant="body2" sx={{ fontWeight: 700 }}>{item.v}</Typography>
                                                </Box>
                                            ))}
                                        </Box>
                                    </Paper>
                                    <Paper sx={{ mt: 3, borderRadius: 1, border: '1px solid rgba(244,67,54,0.2)', overflow: 'hidden', bgcolor: alpha('#f44336', 0.05) }}>
                                        <Box sx={{ px: 3, py: 2, bgcolor: alpha('#f44336', 0.1), borderBottom: '1px solid rgba(244,67,54,0.2)' }}>
                                            <Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#f44336' }}>Delete Node</Typography>
                                        </Box>
                                        <Box sx={{ p: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                            <Typography variant="body2" sx={{ color: '#8e99a3' }}>Deleting a node is irreversible. Remove all servers first.</Typography>
                                            <Button variant="contained" color="error" size="small" onClick={() => setDeleteDialog(true)} disabled={servers.length > 0} sx={{ fontWeight: 800 }}>Yes, Delete This Node</Button>
                                        </Box>
                                    </Paper>
                                </Grid>
                                <Grid item xs={12} md={4}>
                                    <Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#8e99a3', mb: 2, textTransform: 'uppercase', letterSpacing: 1 }}>At-a-Glance</Typography>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                        <Paper sx={{ p: 0, borderRadius: 1, overflow: 'hidden', border: '1px solid rgba(76,175,80,0.3)', bgcolor: alpha('#4caf50', 0.1) }}>
                                            <Box sx={{ p: 2, display: 'flex', gap: 2 }}>
                                                <Avatar sx={{ bgcolor: alpha('#4caf50', 0.2), color: '#4caf50', borderRadius: 1 }}><StorageIcon /></Avatar>
                                                <Box><Typography variant="caption" sx={{ fontWeight: 900, color: '#4caf50' }}>DISK SPACE ALLOCATED</Typography><Typography variant="h6" sx={{ fontWeight: 900 }}>{allocated.disk} / {node.disk_limit} MiB</Typography></Box>
                                            </Box>
                                            <LinearProgress variant="determinate" value={diskPercent} sx={{ height: 10, bgcolor: alpha('#4caf50', 0.1), '& .MuiLinearProgress-bar': { bgcolor: '#4caf50' } }} />
                                        </Paper>
                                        <Paper sx={{ p: 0, borderRadius: 1, overflow: 'hidden', border: '1px solid rgba(33,150,243,0.3)', bgcolor: alpha('#2196f3', 0.1) }}>
                                            <Box sx={{ p: 2, display: 'flex', gap: 2 }}>
                                                <Avatar sx={{ bgcolor: alpha('#2196f3', 0.2), color: '#2196f3', borderRadius: 1 }}><MemoryIcon /></Avatar>
                                                <Box><Typography variant="caption" sx={{ fontWeight: 900, color: '#2196f3' }}>MEMORY ALLOCATED</Typography><Typography variant="h6" sx={{ fontWeight: 900 }}>{allocated.memory} / {node.memory_limit} MiB</Typography></Box>
                                            </Box>
                                            <LinearProgress variant="determinate" value={memPercent} sx={{ height: 10, bgcolor: alpha('#2196f3', 0.1), '& .MuiLinearProgress-bar': { bgcolor: '#2196f3' } }} />
                                        </Paper>
                                    </Box>
                                </Grid>
                            </Grid>
                        } />

                        <Route path="settings" element={
                            <form onSubmit={handleUpdateSettings}>
                                <Grid container spacing={2}>
                                    <Grid item xs={6}>
                                        <Paper sx={cardStyle}>
                                            <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff', fontSize: '0.85rem' }}>Basic Details</Typography></Box>
                                            <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Node Name</Typography>
                                                    <TextField fullWidth size="small" value={settingsForm.name} onChange={e => setSettingsForm({...settingsForm, name: e.target.value})} sx={inputStyle} />
                                                </Box>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Description</Typography>
                                                    <TextField fullWidth size="small" multiline rows={2} value={settingsForm.description} onChange={e => setSettingsForm({...settingsForm, description: e.target.value})} sx={inputStyle} />
                                                </Box>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Location</Typography>
                                                    <TextField select fullWidth size="small" value={settingsForm.location_id} onChange={e => setSettingsForm({...settingsForm, location_id: e.target.value})} sx={inputStyle}>
                                                        {locations.map(l => <MenuItem key={l.id} value={l.id}>{l.name}</MenuItem>)}
                                                    </TextField>
                                                </Box>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Node Visibility</Typography>
                                                    <RadioGroup row value={settingsForm.is_public ? '1' : '0'} onChange={e => setSettingsForm({...settingsForm, is_public: e.target.value === '1'})} sx={{ gap: 2 }}>
                                                        <FormControlLabel value="1" control={<Radio size="small" color="success" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Public</Typography>} />
                                                        <FormControlLabel value="0" control={<Radio size="small" color="error" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Private</Typography>} />
                                                    </RadioGroup>
                                                </Box>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>FQDN</Typography>
                                                    <TextField fullWidth size="small" value={settingsForm.fqdn} onChange={e => setSettingsForm({...settingsForm, fqdn: e.target.value})} sx={inputStyle} />
                                                </Box>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Maintenance Mode</Typography>
                                                    <RadioGroup row value={settingsForm.maintenance_mode ? '1' : '0'} onChange={e => setSettingsForm({...settingsForm, maintenance_mode: e.target.value === '1'})} sx={{ gap: 2 }}>
                                                        <FormControlLabel value="0" control={<Radio size="small" color="success" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Disabled</Typography>} />
                                                        <FormControlLabel value="1" control={<Radio size="small" color="warning" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Enabled</Typography>} />
                                                    </RadioGroup>
                                                </Box>
                                            </Box>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Paper sx={cardStyle}>
                                            <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff', fontSize: '0.85rem' }}>Configuration</Typography></Box>
                                            <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                                <Grid container spacing={2}>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Total Memory</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.memory_limit} onChange={e => setSettingsForm({...settingsForm, memory_limit: e.target.value})} InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& p': { fontSize: '0.7rem' } }}>MiB</InputAdornment> }} sx={inputStyle} />
                                                    </Grid>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Overallocate</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.memory_overallocate} onChange={e => setSettingsForm({...settingsForm, memory_overallocate: e.target.value})} InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& p': { fontSize: '0.7rem' } }}>%</InputAdornment> }} sx={inputStyle} />
                                                    </Grid>
                                                </Grid>
                                                <Grid container spacing={2}>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Disk Space</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.disk_limit} onChange={e => setSettingsForm({...settingsForm, disk_limit: e.target.value})} InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& p': { fontSize: '0.7rem' } }}>MiB</InputAdornment> }} sx={inputStyle} />
                                                    </Grid>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Overallocate</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.disk_overallocate} onChange={e => setSettingsForm({...settingsForm, disk_overallocate: e.target.value})} InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& p': { fontSize: '0.7rem' } }}>%</InputAdornment> }} sx={inputStyle} />
                                                    </Grid>
                                                </Grid>
                                                <Grid container spacing={2}>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon Port</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.daemon_port} onChange={e => setSettingsForm({...settingsForm, daemon_port: e.target.value})} sx={inputStyle} />
                                                    </Grid>
                                                    <Grid item xs={6}>
                                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon SFTP Port</Typography>
                                                        <TextField fullWidth size="small" type="number" value={settingsForm.daemon_sftp_port} onChange={e => setSettingsForm({...settingsForm, daemon_sftp_port: e.target.value})} sx={inputStyle} />
                                                    </Grid>
                                                </Grid>
                                                <Box>
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon Base Directory</Typography>
                                                    <TextField fullWidth size="small" value={settingsForm.daemon_base} onChange={e => setSettingsForm({...settingsForm, daemon_base: e.target.value})} sx={inputStyle} />
                                                </Box>
                                            </Box>
                                        </Paper>
                                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
                                            <Button type="submit" variant="contained" disabled={updating} startIcon={<SaveIcon />} sx={{ bgcolor: '#28a745', '&:hover': { bgcolor: '#218838' }, fontWeight: 700, px: 3, py: 0.8, borderRadius: 1, textTransform: 'none' }}>
                                                {updating ? 'Saving...' : 'Save Changes'}
                                            </Button>
                                        </Box>
                                    </Grid>
                                </Grid>
                            </form>
                        } />

                        <Route path="configuration" element={
                            <Grid container spacing={3}>
                                <Grid item xs={12} md={8}>
                                    <Paper sx={cardStyle}>
                                        <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff' }}>Configuration File</Typography></Box>
                                        <Box sx={{ p: 3 }}>
                                            <Alert severity={config?.panels?.length > 1 ? 'success' : 'info'} sx={{ mb: 2, borderRadius: 1 }}>
                                                {config?.panels?.length > 1
                                                    ? `Shared connector mode is active. This JSON already includes ${config.panels.length} panel entries and can be copied directly.`
                                                    : 'Single-panel Rex config. Set CONNECTOR_SHARED_ROCKY_URL / ID / TOKEN on RA-panel if you want this page to auto-include the Rocky entry too.'}
                                            </Alert>
                                            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2, gap: 2, flexWrap: 'wrap' }}>
                                                <Typography variant="caption" sx={{ color: '#8e99a3', fontWeight: 700 }}>
                                                    {multiPanelConfig
                                                        ? `MultiPanel enabled. Copying the shared config with ${config?.panels?.length || 0} panel entries.`
                                                        : 'MultiPanel disabled. Copying only the local Rex config.'}
                                                </Typography>
                                                <FormControlLabel
                                                    sx={{ m: 0, color: '#e6edf3' }}
                                                    control={
                                                        <Checkbox
                                                            checked={multiPanelConfig}
                                                            onChange={(event) => setMultiPanelConfig(event.target.checked)}
                                                            disabled={!config}
                                                        />
                                                    }
                                                    label="MultiPanel"
                                                />
                                            </Box>
                                            <Box sx={{ p: 2, borderRadius: 1, bgcolor: '#1a2028', border: '1px solid rgba(255,255,255,0.1)', overflow: 'auto', maxHeight: 500 }}>
                                                <pre style={{ margin: 0, color: '#e6edf3', fontSize: '0.8rem', fontFamily: 'monospace' }}>{JSON.stringify(displayedConfig, null, 4)}</pre>
                                            </Box>
                                            <Button startIcon={<CopyIcon />} size="small" onClick={() => handleCopy(JSON.stringify(displayedConfig, null, 4))} sx={{ mt: 2, fontWeight: 800, color: '#57c7ff' }}>Copy JSON</Button>
                                        </Box>
                                    </Paper>
                                </Grid>
                                <Grid item xs={12} md={4}>
                                    <Paper sx={{ p: 3, ...cardStyle, mb: 3 }}>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 800, mb: 2, display: 'flex', alignItems: 'center', gap: 1, color: '#fff' }}>
                                            <CodeIcon fontSize="small" sx={{ color: '#57c7ff' }} /> Auto Config
                                        </Typography>
                                        <Alert severity="info" sx={{ mb: 2, borderRadius: 1 }}>
                                            RA-panel can generate a one-shot bootstrap command for this node. Run it on the connector host to write the current config automatically.
                                        </Alert>
                                        <Box sx={{ p: 2, borderRadius: 1, bgcolor: '#1a2028', border: '1px solid rgba(255,255,255,0.1)', overflow: 'auto', maxHeight: 220 }}>
                                            <pre style={{ margin: 0, color: '#e6edf3', fontSize: '0.72rem', fontFamily: 'monospace', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{autoConfigCommand}</pre>
                                        </Box>
                                        <Button startIcon={<CopyIcon />} size="small" onClick={() => handleCopy(autoConfigCommand)} sx={{ mt: 2, fontWeight: 800, color: '#57c7ff' }}>Copy Auto Command</Button>
                                    </Paper>
                                    <Paper sx={{ p: 3, ...cardStyle }}>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 800, mb: 2, display: 'flex', alignItems: 'center', gap: 1, color: '#fff' }}><KeyIcon fontSize="small" sx={{ color: '#57c7ff' }} /> Daemon Token</Typography>
                                        <Box sx={{ p: 2, borderRadius: 1, bgcolor: '#1a2028', border: '1px solid rgba(255,255,255,0.1)', fontFamily: 'monospace', fontSize: '0.75rem', mb: 2, textAlign: 'center', wordBreak: 'break-all', color: '#8e99a3' }}>
                                            {showToken ? node.daemon_token : '••••••••••••••••••••••••••••••••'}
                                        </Box>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
                                            <Button fullWidth variant="outlined" onClick={() => setShowToken(!showToken)} sx={{ borderRadius: 1, color: '#8e99a3', borderColor: 'rgba(255,255,255,0.1)' }}>{showToken ? 'Hide' : 'Show'}</Button>
                                            <Button fullWidth variant="contained" onClick={() => setConfirmAllocDelete('rotate')} startIcon={<SyncIcon />} sx={{ borderRadius: 1, bgcolor: '#57c7ff', '&:hover': { bgcolor: alpha('#57c7ff', 0.8) } }}>Rotate</Button>
                                        </Box>
                                    </Paper>
                                </Grid>
                            </Grid>
                        } />

                        <Route path="allocations" element={
                            <Grid container spacing={3}>
                                <Grid item xs={12} md={8}>
                                    <Paper sx={cardStyle}>
                                        <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff' }}>Existing Allocations</Typography></Box>
                                        <Box sx={{ px: 2, py: 1.5, display: 'flex', justifyContent: 'flex-end', borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                                            <Button 
                                                size="small" variant="outlined" endIcon={<MoreIcon />} 
                                                onClick={(e) => setMassActionAnchor(e.currentTarget)}
                                                disabled={selectedAllocations.length === 0}
                                                sx={{ borderRadius: 1, textTransform: 'none', fontWeight: 800, color: '#8e99a3', borderColor: 'rgba(255,255,255,0.1)' }}
                                            >
                                                Mass Actions ({selectedAllocations.length})
                                            </Button>
                                            <Menu anchorEl={massActionAnchor} open={Boolean(massActionAnchor)} onClose={() => setMassActionAnchor(null)}>
                                                <MenuItem onClick={() => { setConfirmAllocDelete('bulk'); setMassActionAnchor(null); }} sx={{ color: 'error.main', fontWeight: 700 }}>Delete Selected</MenuItem>
                                            </Menu>
                                        </Box>
                                        <Table size="small" sx={{ '& .MuiTableCell-root': { py: 1, px: 2, borderBottom: '1px solid rgba(255,255,255,0.03)' } }}>
                                            <TableHead><TableRow sx={{ bgcolor: alpha('#000', 0.2) }}><TableCell padding="checkbox"><Checkbox size="small" checked={selectedAllocations.length === allocations.length && allocations.length > 0} onChange={toggleSelectAll} /></TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>IP ADDRESS</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>IP ALIAS</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>PORT</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>ASSIGNED TO</TableCell><TableCell align="right"></TableCell></TableRow></TableHead>
                                            <TableBody>
                                                {allocations.map(a => (
                                                    <TableRow key={a.id} sx={{ '&:hover': { bgcolor: alpha('#57c7ff', 0.02) } }}>
                                                        <TableCell padding="checkbox"><Checkbox size="small" checked={selectedAllocations.includes(a.id)} onChange={() => toggleSelection(a.id)} /></TableCell>
                                                        <TableCell sx={{ fontWeight: 700, color: '#fff' }}>{a.ip}</TableCell>
                                                        <TableCell><Box sx={{ px: 1, bgcolor: '#1a2028', borderRadius: 1, width: 'fit-content', border: '1px solid rgba(255,255,255,0.05)' }}><Typography variant="caption" sx={{ color: '#8e99a3' }}>{a.ip_alias || 'none'}</Typography></Box></TableCell>
                                                        <TableCell sx={{ fontWeight: 800, color: '#fff' }}>{a.port}</TableCell>
                                                        <TableCell>{a.server ? <Typography variant="body2" sx={{ color: '#57c7ff', fontWeight: 800 }}>{a.server.name}</Typography> : <Typography variant="caption" sx={{ color: '#6b7280' }}>unassigned</Typography>}</TableCell>
                                                        <TableCell align="right">{!a.server && <IconButton size="small" color="error" onClick={() => setConfirmAllocDelete(a.id)} sx={{ borderRadius: 1 }}><DeleteIcon sx={{ fontSize: 16 }} /></IconButton>}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </Paper>
                                </Grid>
                                <Grid item xs={12} md={4}>
                                    <Paper sx={{ p: 0, borderRadius: 1, border: '1px solid rgba(76,175,80,0.3)', overflow: 'hidden', bgcolor: '#232b35' }}>
                                        <Box sx={{ px: 2, py: 1.5, bgcolor: alpha('#4caf50', 0.1), borderTop: '2px solid #4caf50', borderBottom: '1px solid rgba(76,175,80,0.3)' }}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff' }}>Assign New Allocations</Typography></Box>
                                        <Box sx={{ p: 3 }}>
                                            <form onSubmit={handleCreateAllocations}>
                                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                                    <Box><Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.5, display: 'block' }}>IP Address</Typography><TextField fullWidth size="small" required value={allocForm.ip} onChange={e => setAllocForm({...allocForm, ip: e.target.value})} sx={inputStyle} /></Box>
                                                    <Box><Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.5, display: 'block' }}>IP Alias</Typography><TextField fullWidth size="small" value={allocForm.alias} onChange={e => setAllocForm({...allocForm, alias: e.target.value})} sx={inputStyle} /></Box>
                                                    <Box><Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.5, display: 'block' }}>Ports</Typography><TextField fullWidth size="small" required multiline rows={3} value={allocForm.ports} onChange={e => setAllocForm({...allocForm, ports: e.target.value})} sx={inputStyle} helperText="Ranges allowed (ex: 25565, 27000-27010)" /></Box>
                                                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}><Button type="submit" variant="contained" disabled={creatingAlloc} sx={{ bgcolor: '#28a745', '&:hover': { bgcolor: '#218838' }, fontWeight: 900, px: 4, borderRadius: 1 }}>Submit</Button></Box>
                                                </Box>
                                            </form>
                                        </Box>
                                    </Paper>
                                </Grid>
                            </Grid>
                        } />

                        <Route path="servers" element={
                            <Paper sx={cardStyle}>
                                <Box sx={headerStyle}><Typography variant="subtitle2" sx={{ fontWeight: 800, color: '#fff' }}>Servers ({servers.length})</Typography></Box>
                                <Table size="small" sx={{ '& .MuiTableCell-root': { py: 1.5, px: 2, borderBottom: '1px solid rgba(255,255,255,0.03)' } }}>
                                    <TableHead><TableRow sx={{ bgcolor: alpha('#000', 0.2) }}><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>SERVER NAME</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>OWNER</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>MEMORY</TableCell><TableCell sx={{ fontSize: '0.7rem', fontWeight: 900, color: '#8e99a3' }}>DISK</TableCell><TableCell align="right"></TableCell></TableRow></TableHead>
                                    <TableBody>
                                        {servers.map(s => (
                                            <TableRow key={s.id} sx={{ '&:hover': { bgcolor: alpha('#57c7ff', 0.02) } }}>
                                                <TableCell><Typography variant="body2" sx={{ fontWeight: 800, color: '#fff' }}>{s.name}</Typography><Typography variant="caption" sx={{ color: '#6b7280', fontFamily: 'monospace' }}>{s.id}</Typography></TableCell>
                                                <TableCell sx={{ color: '#fff' }}>{s.owner?.username}</TableCell><TableCell sx={{ color: '#fff' }}>{s.memory} MiB</TableCell><TableCell sx={{ color: '#fff' }}>{s.disk} MiB</TableCell>
                                                <TableCell align="right"><IconButton size="small" onClick={() => navigate(`/admin/servers/${s.id}/edit`)} sx={{ color: '#57c7ff' }}><ViewIcon fontSize="small" /></IconButton></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Paper>
                        } />

                        <Route path="*" element={<Navigate to="overview" replace />} />
                    </Routes>
                </motion.div>
            </AnimatePresence>

            <Snackbar open={Boolean(snackbar)} autoHideDuration={3000} onClose={() => setSnackbar('')} message={snackbar} anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }} />
            
            <Dialog open={deleteDialog || Boolean(confirmAllocDelete)} onClose={() => { setDeleteDialog(false); setConfirmAllocDelete(null); }} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ fontWeight: 900, display: 'flex', alignItems: 'center', gap: 1.5, bgcolor: '#232b35', color: '#fff' }}>
                    <WarningIcon color="warning" />
                    {deleteDialog ? 'Delete Node?' : (confirmAllocDelete === 'rotate' ? 'Rotate Master Key?' : 'Confirm Deletion')}
                </DialogTitle>
                <DialogContent sx={{ bgcolor: '#232b35' }}>
                    <Typography variant="body2" sx={{ color: '#8e99a3', mt: 1 }}>
                        {deleteDialog && `Confirm permanent deletion of ${node.name}. This action is irreversible.`}
                        {confirmAllocDelete === 'rotate' && 'Resetting the master key will void any request from the old key. The daemon will be disconnected immediately.'}
                        {confirmAllocDelete === 'bulk' && `Are you sure you want to delete ${selectedAllocations.length} selected allocations? Assigned ones will be skipped.`}
                        {typeof confirmAllocDelete === 'number' && 'Are you sure you want to delete this specific allocation?'}
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 3, bgcolor: '#232b35' }}>
                    <Button onClick={() => { setDeleteDialog(false); setConfirmAllocDelete(null); }} disabled={deleting || bulkDeleting} sx={{ color: '#8e99a3' }}>Cancel</Button>
                    <Button 
                        onClick={async () => {
                            if (deleteDialog) {
                                setDeleting(true);
                                try { await client.delete(`/v1/admin/nodes/${id}`); navigate('/admin/nodes'); } catch { setDeleteDialog(false); } finally { setDeleting(false); }
                            } else if (confirmAllocDelete === 'rotate') {
                                handleRegenerateToken();
                                setConfirmAllocDelete(null);
                            } else {
                                handleDeleteAllocation();
                            }
                        }} 
                        variant="contained" 
                        color={confirmAllocDelete === 'rotate' ? 'primary' : 'error'} 
                        disabled={deleting || bulkDeleting}
                        sx={{ fontWeight: 900, px: 3 }}
                    >
                        {(deleting || bulkDeleting) ? <CircularProgress size={20} color="inherit" /> : 'Confirm Action'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AdminNodeView;
