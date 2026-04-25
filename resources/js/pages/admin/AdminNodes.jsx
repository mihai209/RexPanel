import React, { useState, useEffect } from 'react';
import { 
    Box, Typography, Paper, Grid, Table, TableBody, TableCell, TableContainer, 
    TableHead, TableRow, IconButton, Button, Chip, CircularProgress, 
    Alert, LinearProgress, Tooltip, Dialog, DialogTitle, DialogContent, 
    DialogActions, useTheme, alpha, Avatar
} from '@mui/material';
import { 
    Memory as NodesIcon, 
    Add as AddIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    Dns as DnsIcon,
    Favorite as HeartIcon,
    LocationOn as LocationIcon,
    Refresh as RefreshIcon,
    Terminal as TerminalIcon,
    Settings as SettingsIcon,
    Storage as StorageIcon,
    CloudQueue as CloudIcon,
    ArrowForward as ViewIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import client from '../../api/client';

const AdminNodes = () => {
    const navigate = useNavigate();
    const theme = useTheme();
    
    const [nodes, setNodes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [deleteId, setDeleteId] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const fetchNodes = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const res = await client.get('/v1/admin/nodes');
            setNodes(res.data);
            setError('');
        } catch (err) {
            if (!silent) setError('Failed to load nodes.');
        } finally {
            if (!silent) setLoading(false);
        }
    };

    useEffect(() => {
        fetchNodes();
        const interval = setInterval(() => fetchNodes(true), 5000);
        return () => clearInterval(interval);
    }, []);

    const handleDelete = async () => {
        if (!deleteId) return;
        setDeleting(true);
        try {
            await client.delete(`/v1/admin/nodes/${deleteId}`);
            setNodes(nodes.filter(n => n.id !== deleteId));
            setDeleteId(null);
        } catch (err) {
            alert('Failed to delete node. Ensure it has no servers.');
        } finally {
            setDeleting(false);
        }
    };

    const getStatus = (node) => {
        const health = node.health || {};

        if (health.is_active) {
            return { label: 'Healthy', online: true, reason: health.reason_text };
        }

        if (health.is_connected || health.last_heartbeat) {
            return { label: 'Degraded', online: false, reason: health.reason_text };
        }

        return { label: 'Offline', online: false, reason: health.reason_text };
    };

    const formatSize = (mib) => {
        if (mib >= 1024) return `${(mib / 1024).toFixed(1)} GB`;
        return `${mib} MiB`;
    };

    const onlineNodes = nodes.filter(n => getStatus(n).online).length;

    return (
        <Box sx={{ maxWidth: '1400px', mx: 'auto' }}>
            {/* Header Section */}
            <Box sx={{ mb: 4, display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: 3 }}>
                <Box>
                    <Typography variant="h4" sx={{ fontWeight: 900, color: 'text.primary', letterSpacing: '-0.02em', mb: 0.5 }}>
                        Infrastructure Nodes
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <CloudIcon sx={{ fontSize: 16 }} /> {nodes.length} registered nodes &bull; {onlineNodes} active
                    </Typography>
                </Box>
                <Box sx={{ display: 'flex', gap: 2 }}>
                    <Tooltip title="Refresh List">
                        <IconButton onClick={() => fetchNodes()} disabled={loading} sx={{ border: '1px solid rgba(255,255,255,0.08)', borderRadius: 2 }}>
                            <RefreshIcon sx={{ animation: loading ? 'spin 1s linear infinite' : 'none', '@keyframes spin': { from: { rotate: 0 }, to: { rotate: 360 } } }} />
                        </IconButton>
                    </Tooltip>
                    <Button 
                        variant="contained" 
                        startIcon={<AddIcon />}
                        onClick={() => navigate('/admin/nodes/create')}
                        sx={{ borderRadius: 2.5, px: 3, fontWeight: 800, boxShadow: `0 8px 16px ${alpha(theme.palette.primary.main, 0.2)}` }}
                    >
                        Deploy Node
                    </Button>
                </Box>
            </Box>

            {/* Quick Stats Grid */}
            <Grid container spacing={2} sx={{ mb: 4 }}>
                <Grid item xs={12} sm={4}>
                    <Paper sx={{ p: 2, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', bgcolor: alpha(theme.palette.success.main, 0.03), display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Avatar sx={{ bgcolor: alpha(theme.palette.success.main, 0.1), color: 'success.main' }}><HeartIcon /></Avatar>
                        <Box><Typography variant="h6" sx={{ fontWeight: 900, lineHeight: 1 }}>{onlineNodes}</Typography><Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700 }}>Online Now</Typography></Box>
                    </Paper>
                </Grid>
                <Grid item xs={12} sm={4}>
                    <Paper sx={{ p: 2, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', bgcolor: alpha(theme.palette.primary.main, 0.03), display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Avatar sx={{ bgcolor: alpha(theme.palette.primary.main, 0.1), color: 'primary.main' }}><NodesIcon /></Avatar>
                        <Box><Typography variant="h6" sx={{ fontWeight: 900, lineHeight: 1 }}>{nodes.length}</Typography><Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700 }}>Total Nodes</Typography></Box>
                    </Paper>
                </Grid>
                <Grid item xs={12} sm={4}>
                    <Paper sx={{ p: 2, borderRadius: 3, border: '1px solid rgba(255,255,255,0.05)', bgcolor: alpha(theme.palette.info.main, 0.03), display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Avatar sx={{ bgcolor: alpha(theme.palette.info.main, 0.1), color: 'info.main' }}><DnsIcon /></Avatar>
                        <Box><Typography variant="h6" sx={{ fontWeight: 900, lineHeight: 1 }}>{nodes.reduce((acc, n) => acc + (n.server_count || 0), 0)}</Typography><Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700 }}>Hosted Servers</Typography></Box>
                    </Paper>
                </Grid>
            </Grid>

            {error && <Alert severity="error" sx={{ mb: 3, borderRadius: 3 }}>{error}</Alert>}

            <TableContainer component={Paper} sx={{ bgcolor: alpha(theme.palette.background.paper, 0.4), backdropFilter: 'blur(10px)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 4, overflow: 'hidden' }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { py: 2, bgcolor: alpha(theme.palette.background.paper, 0.5), fontWeight: 800, fontSize: '0.7rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 1, borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                            <TableCell>Node Instance</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Location</TableCell>
                            <TableCell>Allocated Resources</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        <AnimatePresence mode="popLayout">
                            {loading && nodes.length === 0 ? (
                                <TableRow><TableCell colSpan={5} align="center" sx={{ py: 10 }}><CircularProgress size={40} thickness={4} /></TableCell></TableRow>
                            ) : nodes.length > 0 ? (
                                nodes.map((node, idx) => {
                                    const status = getStatus(node);
                                    const memPct = node.memory_limit > 0 ? Math.min((node.allocated_memory / node.memory_limit) * 100, 100) : 0;
                                    const diskPct = node.disk_limit > 0 ? Math.min((node.allocated_disk / node.disk_limit) * 100, 100) : 0;
                                    
                                    return (
                                        <TableRow 
                                            key={node.id} 
                                            component={motion.tr}
                                            initial={{ opacity: 0, x: -10 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            transition={{ delay: idx * 0.05 }}
                                            sx={{ transition: '0.2s', '&:hover': { bgcolor: alpha(theme.palette.primary.main, 0.02) }, '& td': { borderBottom: '1px solid rgba(255,255,255,0.03)' } }}
                                        >
                                            <TableCell>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                                    <Avatar sx={{ bgcolor: 'rgba(255,255,255,0.05)', borderRadius: 2, color: 'primary.main' }}><TerminalIcon /></Avatar>
                                                    <Box>
                                                        <Typography variant="body1" sx={{ fontWeight: 800, cursor: 'pointer', '&:hover': { color: 'primary.main' } }} onClick={() => navigate(`/admin/nodes/${node.id}/overview`)}>
                                                            {node.name}
                                                        </Typography>
                                                        <Typography variant="caption" sx={{ color: 'text.disabled', fontFamily: 'monospace' }}>{node.fqdn}:{node.daemon_port}</Typography>
                                                    </Box>
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <HeartIcon sx={{ 
                                                        fontSize: 14, 
                                                        color: status.online ? 'success.main' : 'text.disabled',
                                                        animation: status.online ? 'heartbeat 1.2s ease-in-out infinite' : 'none',
                                                        '@keyframes heartbeat': {
                                                            '0%': { transform: 'scale(1)' },
                                                            '14%': { transform: 'scale(1.2)' },
                                                            '28%': { transform: 'scale(1)' },
                                                            '42%': { transform: 'scale(1.2)' },
                                                            '70%': { transform: 'scale(1)' },
                                                        }
                                                    }} />
                                                    <Typography variant="caption" sx={{ fontWeight: 800, color: status.online ? 'success.main' : 'text.disabled', textTransform: 'uppercase', fontSize: '0.65rem' }}>
                                                        {status.label}
                                                    </Typography>
                                                </Box>
                                                {status.reason ? (
                                                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mt: 0.5 }}>
                                                        {status.reason}
                                                    </Typography>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                <Chip label={node.location?.name || 'Local'} size="small" variant="outlined" icon={<LocationIcon sx={{ fontSize: '12px !important' }} />} sx={{ fontWeight: 700, borderRadius: 1.5, borderColor: 'rgba(255,255,255,0.1)' }} />
                                            </TableCell>
                                            <TableCell sx={{ minWidth: 220 }}>
                                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                                    <Box>
                                                        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, fontSize: '0.65rem' }}>RAM: {formatSize(node.allocated_memory)} / {formatSize(node.memory_limit)}</Typography>
                                                            <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 800, fontSize: '0.6rem' }}>{Math.round(memPct)}%</Typography>
                                                        </Box>
                                                        <LinearProgress variant="determinate" value={memPct} color={memPct > 80 ? 'error' : memPct > 50 ? 'warning' : 'primary'} sx={{ height: 4, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.05)' }} />
                                                    </Box>
                                                    <Box>
                                                        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, fontSize: '0.65rem' }}>Disk: {formatSize(node.allocated_disk)} / {formatSize(node.disk_limit)}</Typography>
                                                            <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 800, fontSize: '0.6rem' }}>{Math.round(diskPct)}%</Typography>
                                                        </Box>
                                                        <LinearProgress variant="determinate" value={diskPct} color={diskPct > 80 ? 'error' : diskPct > 50 ? 'warning' : 'primary'} sx={{ height: 4, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.05)' }} />
                                                    </Box>
                                                </Box>
                                            </TableCell>
                                            <TableCell align="right">
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                    <Button 
                                                        variant="outlined" 
                                                        size="small" 
                                                        startIcon={<ViewIcon />}
                                                        onClick={() => navigate(`/admin/nodes/${node.id}/overview`)}
                                                        sx={{ fontWeight: 800, borderRadius: 2, textTransform: 'none', px: 2 }}
                                                    >
                                                        Manage
                                                    </Button>
                                                    <IconButton size="small" onClick={() => navigate(`/admin/nodes/${node.id}/edit`)} sx={{ bgcolor: 'rgba(255,255,255,0.03)', borderRadius: 2 }}><SettingsIcon fontSize="small" /></IconButton>
                                                    <IconButton size="small" color="error" onClick={() => setDeleteId(node.id)} sx={{ bgcolor: alpha(theme.palette.error.main, 0.05), borderRadius: 2 }}><DeleteIcon fontSize="small" /></IconButton>
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={5} align="center" sx={{ py: 15 }}>
                                        <Box sx={{ opacity: 0.2 }}>
                                            <StorageIcon sx={{ fontSize: 80, mb: 2 }} />
                                            <Typography variant="h5" sx={{ fontWeight: 900 }}>Empty Fleet</Typography>
                                            <Typography variant="body2">You haven't added any infrastructure nodes yet.</Typography>
                                        </Box>
                                    </TableCell>
                                </TableRow>
                            )}
                        </AnimatePresence>
                    </TableBody>
                </Table>
            </TableContainer>

            {/* Delete Confirmation Dialog */}
            <Dialog open={Boolean(deleteId)} onClose={() => !deleting && setDeleteId(null)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ fontWeight: 900, letterSpacing: -0.5 }}>Remove Node?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
                        Are you sure you want to remove this node? This action is irreversible.
                    </Typography>
                    <Alert severity="error" sx={{ borderRadius: 2, fontWeight: 700, fontSize: '0.75rem' }}>
                        All servers currently on this node will become disconnected!
                    </Alert>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setDeleteId(null)} disabled={deleting} sx={{ color: 'text.secondary', fontWeight: 700 }}>Cancel</Button>
                    <Button onClick={handleDelete} variant="contained" color="error" disabled={deleting} sx={{ fontWeight: 900, px: 3, borderRadius: 2 }}>
                        {deleting ? <CircularProgress size={20} color="inherit" /> : 'Yes, Remove Node'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AdminNodes;
