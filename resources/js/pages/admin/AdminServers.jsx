import React, { useState, useEffect } from 'react';
import { 
    Box, 
    Typography, 
    Paper, 
    Table, 
    TableBody, 
    TableCell, 
    TableContainer, 
    TableHead, 
    TableRow, 
    IconButton, 
    Button,
    Chip,
    CircularProgress,
    Alert,
    Tooltip
} from '@mui/material';
import { 
    Dns as ServersIcon, 
    Add as AddIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    Memory as RamIcon,
    Speed as CpuIcon,
    Storage as DiskIcon,
    Refresh as RefreshIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';

const AdminServers = () => {
    const navigate = useNavigate();
    const [servers, setServers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [deleteId, setDeleteId] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const canDeleteServer = (server) => Boolean(server?.action_permissions?.connector_actions);

    const fetchServers = async () => {
        setLoading(true);
        try {
            const res = await client.get('/v1/admin/servers');
            setServers(res.data);
        } catch (err) {
            setError('Failed to load servers.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchServers();
    }, []);

    const handleDelete = async (id) => {
        if (!confirm('Are you sure you want to delete this server? This will destroy the container and files.')) return;
        setDeleting(true);
        try {
            await client.delete(`/v1/admin/servers/${id}`);
            setServers(servers.filter(s => s.id !== id));
        } catch (err) {
            alert('Failed to delete server.');
        } finally {
            setDeleting(false);
        }
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <ServersIcon /> Servers
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Manage and allocate resources for user instances.
                    </Typography>
                </Box>
                <Box sx={{ display: 'flex', gap: 2 }}>
                    <IconButton onClick={fetchServers} sx={{ color: 'text.secondary' }}>
                        <RefreshIcon />
                    </IconButton>
                    <Button 
                        variant="contained" 
                        startIcon={<AddIcon />}
                        onClick={() => navigate('/admin/servers/create')}
                        sx={{ borderRadius: 2, px: 3 }}
                    >
                        Create Server
                    </Button>
                </Box>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}

            <TableContainer component={Paper} elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { fontWeight: 700, color: 'text.secondary', borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                            <TableCell>Server Name</TableCell>
                            <TableCell>Owner</TableCell>
                            <TableCell>Deployment</TableCell>
                            <TableCell>Resources</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell colSpan={6} align="center" sx={{ py: 4 }}>
                                    <CircularProgress size={30} />
                                </TableCell>
                            </TableRow>
                        ) : servers.length > 0 ? (
                            servers.map((server) => (
                                <TableRow key={server.id} sx={{ '& td': { borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                                    <TableCell>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{server.name}</Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary', fontFamily: 'monospace' }}>{server.uuid.substring(0, 8)}</Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="body2">{server.user?.username}</Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>{server.node?.name || '—'}</Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>
                                            {server.image?.package?.name || '—'} / {server.image?.name || '—'}
                                        </Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', fontFamily: 'monospace' }}>
                                            {server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : 'No allocation'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        <Box sx={{ display: 'flex', gap: 2, color: 'text.secondary' }}>
                                            <Tooltip title="CPU">
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                    <CpuIcon sx={{ fontSize: 16 }} />
                                                    <Typography variant="caption">{server.cpu}%</Typography>
                                                </Box>
                                            </Tooltip>
                                            <Tooltip title="RAM">
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                    <RamIcon sx={{ fontSize: 16 }} />
                                                    <Typography variant="caption">{server.memory}MB</Typography>
                                                </Box>
                                            </Tooltip>
                                            <Tooltip title="Disk">
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                    <DiskIcon sx={{ fontSize: 16 }} />
                                                    <Typography variant="caption">{server.disk}MB</Typography>
                                                </Box>
                                            </Tooltip>
                                        </Box>
                                    </TableCell>
                                    <TableCell>
                                        <Chip 
                                            label={server.status} 
                                            color={server.status === 'running' ? 'success' : 'default'} 
                                            size="small" 
                                            sx={{ fontWeight: 700, textTransform: 'uppercase', fontSize: '0.6rem' }}
                                        />
                                        {server.node_health?.reason_text ? (
                                            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mt: 0.5 }}>
                                                {server.node_health.reason_text}
                                            </Typography>
                                        ) : null}
                                    </TableCell>
                                    <TableCell align="right">
                                        <IconButton 
                                            size="small" 
                                            sx={{ color: 'text.secondary', mr: 1 }}
                                            onClick={() => navigate(`/admin/servers/${server.id}`)}
                                        >
                                            <EditIcon fontSize="small" />
                                        </IconButton>
                                        <IconButton 
                                            size="small" 
                                            color="error"
                                            onClick={() => handleDelete(server.id)}
                                            disabled={!canDeleteServer(server)}
                                        >
                                            <DeleteIcon fontSize="small" />
                                        </IconButton>
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={6} align="center" sx={{ py: 10 }}>
                                    <ServersIcon sx={{ fontSize: 60, color: 'rgba(255,255,255,0.05)', mb: 2 }} />
                                    <Typography variant="h6" sx={{ color: 'text.secondary', mb: 1 }}>No Servers Found</Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                                        Start by creating a new server for a user.
                                    </Typography>
                                    <Button 
                                        variant="outlined" 
                                        onClick={() => navigate('/admin/servers/create')}
                                    >
                                        Create Your First Server
                                    </Button>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>
        </Box>
    );
};

export default AdminServers;
