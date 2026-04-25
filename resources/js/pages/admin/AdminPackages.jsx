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
    IconButton,
    MenuItem,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Tooltip,
    Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    Inventory2 as PackageIcon,
    Refresh as RefreshIcon,
    Upload as ImportIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';

const packageDefaults = {
    name: '',
    slug: '',
    description: '',
    image_url: '',
};

const importDefaults = {
    package_id: '',
    json_payload: '',
    is_public: true,
};

const AdminPackages = () => {
    const navigate = useNavigate();
    const [packages, setPackages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [packageModalOpen, setPackageModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [packageForm, setPackageForm] = useState(packageDefaults);
    const [importForm, setImportForm] = useState(importDefaults);
    const [importFileName, setImportFileName] = useState('');
    const [savingPackage, setSavingPackage] = useState(false);
    const [importing, setImporting] = useState(false);

    const fetchPackages = async () => {
        setLoading(true);
        try {
            const response = await client.get('/v1/admin/packages');
            setPackages(response.data);
            setImportForm((current) => ({
                ...current,
                package_id: current.package_id || response.data[0]?.id || '',
            }));
        } catch {
            setMessage({ type: 'error', text: 'Failed to load packages.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPackages();
    }, []);

    const handleCreatePackage = async () => {
        setSavingPackage(true);
        try {
            await client.post('/v1/admin/packages', packageForm);
            setPackageModalOpen(false);
            setPackageForm(packageDefaults);
            setMessage({ type: 'success', text: 'Package created successfully.' });
            await fetchPackages();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to create package.' });
        } finally {
            setSavingPackage(false);
        }
    };

    const handleImport = async () => {
        setImporting(true);
        try {
            const response = await client.post('/v1/admin/images/import', importForm);
            const summary = response.data.summary;
            setImportModalOpen(false);
            setImportForm((current) => ({ ...current, json_payload: '' }));
            setImportFileName('');
            setMessage({
                type: 'success',
                text: `Import complete. Created: ${summary.created}, Updated: ${summary.updated}, Failed: ${summary.failed}.`,
            });
            await fetchPackages();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to import image JSON.' });
        } finally {
            setImporting(false);
        }
    };

    const handleImportFileChange = async (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            setImportFileName('');
            setImportForm((current) => ({ ...current, json_payload: '' }));
            return;
        }

        try {
            const text = await file.text();
            setImportFileName(file.name);
            setImportForm((current) => ({ ...current, json_payload: text }));
            setMessage({ type: '', text: '' });
        } catch {
            setImportFileName('');
            setImportForm((current) => ({ ...current, json_payload: '' }));
            setMessage({ type: 'error', text: 'Failed to read the selected JSON file.' });
        } finally {
            event.target.value = '';
        }
    };

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4, gap: 2, flexWrap: 'wrap' }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                        <PackageIcon /> Packages
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Main entry point for package and image administration, following the CPanel package flow.
                    </Typography>
                </Box>
                <Box sx={{ display: 'flex', gap: 1.5, flexWrap: 'wrap' }}>
                    <Tooltip title="Refresh">
                        <IconButton onClick={fetchPackages} sx={{ color: 'text.secondary' }}>
                            <RefreshIcon />
                        </IconButton>
                    </Tooltip>
                    <Button variant="outlined" startIcon={<ImportIcon />} onClick={() => setImportModalOpen(true)}>
                        Import Image
                    </Button>
                    <Button variant="contained" startIcon={<AddIcon />} onClick={() => setPackageModalOpen(true)}>
                        Create Package
                    </Button>
                </Box>
            </Box>

            <Alert severity="warning" sx={{ mb: 3 }}>
                Images are powerful and flexible, but editing them carelessly can break deployments. Treat package and image changes like template changes, not cosmetic changes.
            </Alert>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <TableContainer component={Paper} elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { fontWeight: 700, color: 'text.secondary', borderBottom: '1px solid rgba(255,255,255,0.05)' } }}>
                            <TableCell>Name</TableCell>
                            <TableCell>Description</TableCell>
                            <TableCell align="center">Images</TableCell>
                            <TableCell align="center">Servers</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell colSpan={4} align="center" sx={{ py: 5 }}>
                                    <CircularProgress size={28} />
                                </TableCell>
                            </TableRow>
                        ) : packages.length > 0 ? (
                            packages.map((pkg) => (
                                <TableRow
                                    key={pkg.id}
                                    hover
                                    onClick={() => navigate(`/admin/packages/${pkg.id}`)}
                                    sx={{ cursor: 'pointer', '& td': { borderBottom: '1px solid rgba(255,255,255,0.05)' } }}
                                >
                                    <TableCell>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{pkg.name}</Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary', fontFamily: 'monospace' }}>{pkg.slug}</Typography>
                                    </TableCell>
                                    <TableCell>{pkg.description || '—'}</TableCell>
                                    <TableCell align="center">{pkg.images_count ?? 0}</TableCell>
                                    <TableCell align="center">{pkg.servers_count ?? 0}</TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={4} align="center" sx={{ py: 8 }}>
                                    <Typography variant="h6" sx={{ color: 'text.secondary', mb: 1 }}>No packages found</Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                        Create a package or import image JSON files to initialize the catalog.
                                    </Typography>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog open={packageModalOpen} onClose={() => !savingPackage && setPackageModalOpen(false)} fullWidth maxWidth="sm">
                <DialogTitle>Create Package</DialogTitle>
                <DialogContent sx={{ pt: '8px !important' }}>
                    <Box sx={{ display: 'grid', gap: 2 }}>
                        <TextField label="Name" value={packageForm.name} onChange={(event) => setPackageForm({ ...packageForm, name: event.target.value })} />
                        <TextField label="Slug" value={packageForm.slug} onChange={(event) => setPackageForm({ ...packageForm, slug: event.target.value })} />
                        <TextField label="Description" value={packageForm.description} onChange={(event) => setPackageForm({ ...packageForm, description: event.target.value })} />
                        <TextField label="Image URL" value={packageForm.image_url} onChange={(event) => setPackageForm({ ...packageForm, image_url: event.target.value })} />
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setPackageModalOpen(false)} disabled={savingPackage}>Cancel</Button>
                    <Button onClick={handleCreatePackage} variant="contained" disabled={savingPackage}>
                        {savingPackage ? 'Saving...' : 'Create'}
                    </Button>
                </DialogActions>
            </Dialog>

            <Dialog open={importModalOpen} onClose={() => !importing && setImportModalOpen(false)} fullWidth maxWidth="md">
                <DialogTitle>Import Image</DialogTitle>
                <DialogContent sx={{ pt: '8px !important' }}>
                    <Box sx={{ display: 'grid', gap: 2 }}>
                        <TextField
                            select
                            label="Target Package"
                            value={importForm.package_id}
                            onChange={(event) => setImportForm({ ...importForm, package_id: event.target.value })}
                        >
                            {packages.map((pkg) => (
                                <MenuItem key={pkg.id} value={pkg.id}>{pkg.name}</MenuItem>
                            ))}
                        </TextField>
                        <Box sx={{ display: 'grid', gap: 1 }}>
                            <Button variant="outlined" component="label" startIcon={<ImportIcon />}>
                                Upload JSON File
                                <input type="file" accept=".json,application/json" hidden onChange={handleImportFileChange} />
                            </Button>
                            <Typography variant="body2" sx={{ color: importFileName ? 'text.primary' : 'text.secondary' }}>
                                {importFileName || 'No JSON file selected.'}
                            </Typography>
                            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                                Select a `.json` egg/image file to import into the chosen package.
                            </Typography>
                        </Box>
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => { setImportModalOpen(false); setImportFileName(''); setImportForm((current) => ({ ...current, json_payload: '' })); }} disabled={importing}>Cancel</Button>
                    <Button onClick={handleImport} variant="contained" disabled={importing || !importForm.package_id || !importForm.json_payload}>
                        {importing ? 'Importing...' : 'Import'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AdminPackages;
