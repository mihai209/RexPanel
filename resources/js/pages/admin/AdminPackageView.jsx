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
    Grid,
    IconButton,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    ArrowBack as BackIcon,
    Delete as DeleteIcon,
    Download as ExportIcon,
    Save as SaveIcon,
} from '@mui/icons-material';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';

const imageDefaults = {
    name: '',
    description: '',
    author: '',
    docker_image: '',
};

const AdminPackageView = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [creatingImage, setCreatingImage] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [packageData, setPackageData] = useState(null);
    const [form, setForm] = useState({ name: '', slug: '', description: '', image_url: '' });
    const [createImageOpen, setCreateImageOpen] = useState(false);
    const [imageForm, setImageForm] = useState(imageDefaults);

    const fetchPackage = async () => {
        setLoading(true);
        try {
            const response = await client.get(`/v1/admin/packages/${id}`);
            setPackageData(response.data);
            setForm({
                name: response.data.name || '',
                slug: response.data.slug || '',
                description: response.data.description || '',
                image_url: response.data.image_url || '',
            });
        } catch {
            setMessage({ type: 'error', text: 'Failed to load package details.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPackage();
    }, [id]);

    const handleSavePackage = async () => {
        setSaving(true);
        try {
            const response = await client.put(`/v1/admin/packages/${id}`, form);
            setPackageData((current) => ({ ...current, ...response.data.package }));
            setMessage({ type: 'success', text: 'Package updated successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update package.' });
        } finally {
            setSaving(false);
        }
    };

    const handleCreateImage = async () => {
        setCreatingImage(true);
        try {
            const payload = {
                ...imageForm,
                docker_images: { [imageForm.docker_image]: imageForm.docker_image },
            };
            const response = await client.post(`/v1/admin/packages/${id}/images`, payload);
            setCreateImageOpen(false);
            setImageForm(imageDefaults);
            setMessage({ type: 'success', text: 'Image created successfully.' });
            navigate(`/admin/images/${response.data.image.id}`);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to create image.' });
        } finally {
            setCreatingImage(false);
        }
    };

    const handleDeletePackage = async () => {
        if (!window.confirm(`Delete package "${packageData.name}" and all of its images? This only works if none of those images are assigned to servers.`)) {
            return;
        }

        try {
            await client.delete(`/v1/admin/packages/${id}`);
            navigate('/admin/packages');
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete package.' });
        }
    };

    const handleDeleteImage = async (imageId, imageName) => {
        if (!window.confirm(`Delete image "${imageName}"? This only works if it has no assigned servers.`)) {
            return;
        }

        try {
            await client.delete(`/v1/admin/images/${imageId}`);
            setMessage({ type: 'success', text: 'Image deleted successfully.' });
            await fetchPackage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete image.' });
        }
    };

    if (loading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}><CircularProgress /></Box>;
    }

    if (!packageData) {
        return <Alert severity="error">Package not found.</Alert>;
    }

    return (
        <Box>
            <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate('/admin/packages')}>
                    <BackIcon />
                </IconButton>
                <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>{packageData.name}</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Package detail view with nested images, matching the CPanel package layout.
                    </Typography>
                </Box>
                <Button variant="outlined" color="error" startIcon={<DeleteIcon />} onClick={handleDeletePackage}>
                    Delete Package
                </Button>
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreateImageOpen(true)}>
                    New Image
                </Button>
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <Grid container spacing={3}>
                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Package</Typography>
                        <Box sx={{ display: 'grid', gap: 2 }}>
                            <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
                            <TextField label="Slug" value={form.slug} onChange={(event) => setForm({ ...form, slug: event.target.value })} />
                            <TextField label="Description" multiline minRows={5} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
                            <TextField label="Image URL" value={form.image_url} onChange={(event) => setForm({ ...form, image_url: event.target.value })} />
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                <Button variant="contained" startIcon={<SaveIcon />} onClick={handleSavePackage} disabled={saving}>
                                    {saving ? 'Saving...' : 'Save'}
                                </Button>
                            </Box>
                        </Box>
                    </Paper>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Identifiers</Typography>
                        <Box sx={{ display: 'grid', gap: 2 }}>
                            <TextField label="Package ID" value={packageData.id} InputProps={{ readOnly: true }} />
                            <TextField label="Slug" value={packageData.slug} InputProps={{ readOnly: true }} />
                            <TextField label="Images Count" value={packageData.images_count ?? 0} InputProps={{ readOnly: true }} />
                            <TextField label="Servers Count" value={packageData.servers_count ?? 0} InputProps={{ readOnly: true }} />
                        </Box>
                    </Paper>
                </Grid>
            </Grid>

            <Paper sx={{ mt: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                <Box sx={{ p: 3, borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>Package Images</Typography>
                </Box>
                <TableContainer>
                    <Table>
                        <TableHead>
                            <TableRow sx={{ '& th': { fontWeight: 700, color: 'text.secondary' } }}>
                                <TableCell>Name</TableCell>
                                <TableCell>Description</TableCell>
                                <TableCell align="center">Servers</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {packageData.images?.map((image) => (
                                <TableRow key={image.id} hover sx={{ cursor: 'pointer' }} onClick={() => navigate(`/admin/images/${image.id}`)}>
                                    <TableCell>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{image.name}</Typography>
                                    </TableCell>
                                    <TableCell>{image.description || '—'}</TableCell>
                                    <TableCell align="center">{image.servers_count ?? 0}</TableCell>
                                    <TableCell align="right">
                                        <Button
                                            size="small"
                                            variant="text"
                                            startIcon={<ExportIcon />}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                window.open(`/api/v1/admin/images/${image.id}/export`, '_blank');
                                            }}
                                        >
                                            Export
                                        </Button>
                                        <Button
                                            size="small"
                                            color="error"
                                            variant="text"
                                            startIcon={<DeleteIcon />}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                handleDeleteImage(image.id, image.name);
                                            }}
                                        >
                                            Delete
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!packageData.images?.length && (
                                <TableRow>
                                    <TableCell colSpan={4} align="center" sx={{ py: 5 }}>
                                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                            No images in this package yet.
                                        </Typography>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Paper>

            <Dialog open={createImageOpen} onClose={() => !creatingImage && setCreateImageOpen(false)} fullWidth maxWidth="sm">
                <DialogTitle>New Image</DialogTitle>
                <DialogContent sx={{ pt: '8px !important' }}>
                    <Box sx={{ display: 'grid', gap: 2 }}>
                        <TextField label="Name" value={imageForm.name} onChange={(event) => setImageForm({ ...imageForm, name: event.target.value })} />
                        <TextField label="Description" value={imageForm.description} onChange={(event) => setImageForm({ ...imageForm, description: event.target.value })} />
                        <TextField label="Author" value={imageForm.author} onChange={(event) => setImageForm({ ...imageForm, author: event.target.value })} />
                        <TextField label="Docker Image" value={imageForm.docker_image} onChange={(event) => setImageForm({ ...imageForm, docker_image: event.target.value })} />
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setCreateImageOpen(false)} disabled={creatingImage}>Cancel</Button>
                    <Button onClick={handleCreateImage} variant="contained" disabled={creatingImage || !imageForm.name || !imageForm.docker_image}>
                        {creatingImage ? 'Creating...' : 'Create'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AdminPackageView;
