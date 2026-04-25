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
    CircularProgress,
    Alert,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions
} from '@mui/material';
import { 
    Public as LocationIcon, 
    Add as AddIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    LocationOff as EmptyIcon,
    Storage as StorageIcon,
    Hub as ConnectorIcon,
    Image as ImageIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';

const AdminLocations = () => {
    const navigate = useNavigate();
    const [locations, setLocations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [deleteId, setDeleteId] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const fetchLocations = async () => {
        setLoading(true);
        try {
            const res = await client.get('/v1/admin/locations');
            setLocations(res.data);
        } catch (err) {
            setError('Failed to load locations.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchLocations();
    }, []);

    const [errorModal, setErrorModal] = useState(false);
    const [selectedLocation, setSelectedLocation] = useState(null);

    const handleLocationsDelete = async () => {
        if (!deleteId) return;
        setDeleting(true);
        try {
            await client.delete(`/v1/admin/locations/${deleteId}`);
            setLocations(locations.filter(l => l.id !== deleteId));
            setDeleteId(null);
        } catch (err) {
            setErrorModal(true);
            setDeleteId(null);
        } finally {
            setDeleting(false);
        }
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <LocationIcon /> Locations
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Manage geographical regions for your nodes.
                    </Typography>
                </Box>
                <Button 
                    variant="contained" 
                    startIcon={<AddIcon />}
                    onClick={() => navigate('/admin/locations/create')}
                    sx={{ borderRadius: 2, px: 3 }}
                >
                    Create New
                </Button>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}

            <TableContainer component={Paper} elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { fontWeight: 700, color: 'text.secondary', borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                            <TableCell>ID</TableCell>
                            <TableCell>Short Name</TableCell>
                            <TableCell>Image</TableCell>
                            <TableCell>Description</TableCell>
                            <TableCell>Database Hosts</TableCell>
                            <TableCell>Connectors</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell colSpan={7} align="center" sx={{ py: 4 }}>
                                    <CircularProgress size={30} />
                                </TableCell>
                            </TableRow>
                        ) : locations.length > 0 ? (
                            locations.map((loc) => (
                                <TableRow key={loc.id} sx={{ '& td': { borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                                    <TableCell sx={{ color: 'text.secondary', fontWeight: 600 }}>{loc.id}</TableCell>
                                    <TableCell sx={{ fontWeight: 700 }}>{loc.short_name || loc.name}</TableCell>
                                    <TableCell>
                                        {loc.image_url ? (
                                            <img src={loc.image_url} alt={loc.short_name || loc.name} style={{ width: 36, height: 36, borderRadius: 10, objectFit: 'cover' }} />
                                        ) : (
                                            <ImageIcon sx={{ color: 'text.disabled' }} />
                                        )}
                                    </TableCell>
                                    <TableCell sx={{ color: 'text.secondary' }}>{loc.description || '-'}</TableCell>
                                    <TableCell>{loc.database_hosts_count || 0}</TableCell>
                                    <TableCell>{loc.connectors_count || 0}</TableCell>
                                    <TableCell align="right">
                                        <Button size="small" onClick={() => openDetails(loc.id)}>Assets</Button>
                                        <IconButton 
                                            size="small" 
                                            sx={{ color: 'text.secondary', mr: 1 }}
                                            onClick={() => navigate(`/admin/locations/${loc.id}/edit`)}
                                        >
                                            <EditIcon fontSize="small" />
                                        </IconButton>
                                        <IconButton 
                                            size="small" 
                                            color="error" 
                                            onClick={() => setDeleteId(loc.id)}
                                        >
                                            <DeleteIcon fontSize="small" />
                                        </IconButton>
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={7} align="center" sx={{ py: 10 }}>
                                    <EmptyIcon sx={{ fontSize: 60, color: 'rgba(255,255,255,0.05)', mb: 2 }} />
                                    <Typography variant="h6" sx={{ color: 'text.secondary', mb: 1 }}>No Locations Found</Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                                        You need to create at least one location before you can add nodes and servers.
                                    </Typography>
                                    <Button 
                                        variant="outlined" 
                                        onClick={() => navigate('/admin/locations/create')}
                                    >
                                        Create Your First Location
                                    </Button>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            {/* Delete Confirmation Dialog */}
            <Dialog open={Boolean(deleteId)} onClose={() => !deleting && setDeleteId(null)}>
                <DialogTitle sx={{ fontWeight: 700 }}>Delete Location?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Are you sure you want to delete this location? This will fail while connectors or database hosts are still assigned to it.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setDeleteId(null)} disabled={deleting} sx={{ color: 'text.secondary' }}>Cancel</Button>
                    <Button onClick={handleLocationsDelete} variant="contained" color="error" disabled={deleting}>
                        {deleting ? <CircularProgress size={20} color="inherit" /> : 'Yes, Delete'}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Error Dialog */}
            <Dialog open={errorModal} onClose={() => setErrorModal(false)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ fontWeight: 900, color: 'error.main' }}>Action Failed</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Failed to delete location. It might be in use by nodes. Please remove all nodes from this location before deleting it.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setErrorModal(false)} variant="contained" sx={{ fontWeight: 900, px: 3 }}>Understood</Button>
                </DialogActions>
            </Dialog>

            <Dialog open={Boolean(selectedLocation)} onClose={() => setSelectedLocation(null)} maxWidth="md" fullWidth>
                <DialogTitle sx={{ fontWeight: 700 }}>
                    {selectedLocation?.short_name || selectedLocation?.name} Assets
                </DialogTitle>
                <DialogContent dividers>
                    <Typography variant="subtitle2" sx={{ mb: 1, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                        <StorageIcon fontSize="small" /> Database Hosts
                    </Typography>
                    {(selectedLocation?.assets?.database_hosts || []).length ? (
                        selectedLocation.assets.database_hosts.map((host) => (
                            <Typography key={host.id} variant="body2" sx={{ color: 'text.secondary', mb: 0.75 }}>
                                {host.name} • {host.host}:{host.port} • {host.database_count} databases
                            </Typography>
                        ))
                    ) : (
                        <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>No database hosts assigned.</Typography>
                    )}
                    <Typography variant="subtitle2" sx={{ mt: 3, mb: 1, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                        <ConnectorIcon fontSize="small" /> Connectors
                    </Typography>
                    {(selectedLocation?.assets?.connectors || []).length ? (
                        selectedLocation.assets.connectors.map((node) => (
                            <Typography key={node.id} variant="body2" sx={{ color: 'text.secondary', mb: 0.75 }}>
                                {node.name} • {node.fqdn} • {node.server_count} servers • {node.allocation_count} allocations
                            </Typography>
                        ))
                    ) : (
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>No connectors assigned.</Typography>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setSelectedLocation(null)}>Close</Button>
                </DialogActions>
            </Dialog>

        </Box>
    );
};

export default AdminLocations;
    const openDetails = async (locationId) => {
        try {
            const res = await client.get(`/v1/admin/locations/${locationId}`);
            setSelectedLocation(res.data);
        } catch {
            setError('Failed to load location asset details.');
        }
    };
