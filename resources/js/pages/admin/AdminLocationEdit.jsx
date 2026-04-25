import React, { useState, useEffect } from 'react';
import { 
    Box, 
    Typography, 
    Paper, 
    Grid, 
    TextField, 
    Button, 
    IconButton,
    Alert,
    CircularProgress,
    Divider
} from '@mui/material';
import { 
    ArrowBack as BackIcon, 
    Public as LocationIcon,
    Save as SaveIcon,
    Image as ImageIcon,
} from '@mui/icons-material';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';

const AdminLocationEdit = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const [formData, setFormData] = useState({
        short_name: '',
        name: '',
        description: '',
        image_url: '',
    });

    useEffect(() => {
        const fetchLocation = async () => {
            try {
                const res = await client.get(`/v1/admin/locations/${id}`);
                setFormData({
                    short_name: res.data.short_name || res.data.name,
                    name: res.data.name,
                    description: res.data.description || '',
                    image_url: res.data.image_url || '',
                });
            } catch (err) {
                setError('Failed to load location details.');
            } finally {
                setLoading(false);
            }
        };
        fetchLocation();
    }, [id]);

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        setSuccess('');

        try {
            await client.put(`/v1/admin/locations/${id}`, formData);
            setSuccess('Location updated successfully.');
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update location.');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
            <CircularProgress />
        </Box>
    );

    return (
        <Box>
            <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate('/admin/locations')} sx={{ color: 'text.secondary' }}>
                    <BackIcon />
                </IconButton>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'text.primary' }}>
                        Edit Location: {formData.name}
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Update geographical region details.
                    </Typography>
                </Box>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
            {success && <Alert severity="success" sx={{ mb: 3 }}>{success}</Alert>}

            <form onSubmit={handleUpdate}>
                <Grid container spacing={3}>
                    <Grid item xs={12} md={7}>
                        <Paper sx={{ p: 4, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 3, display: 'flex', alignItems: 'center', gap: 1 }}>
                                <LocationIcon sx={{ color: 'primary.main' }} /> Location Details
                            </Typography>
                            
                            <Grid container spacing={3}>
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        label="Short Name"
                                        value={formData.short_name}
                                        onChange={(e) => setFormData({ ...formData, short_name: e.target.value, name: e.target.value })}
                                        required
                                        variant="outlined"
                                        sx={{ '& .MuiOutlinedInput-root': { bgcolor: 'rgba(0,0,0,0.2)' } }}
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        label="Image URL"
                                        value={formData.image_url}
                                        onChange={(e) => setFormData({ ...formData, image_url: e.target.value })}
                                        variant="outlined"
                                        InputProps={{ startAdornment: <ImageIcon sx={{ mr: 1, color: 'text.secondary' }} /> }}
                                        sx={{ '& .MuiOutlinedInput-root': { bgcolor: 'rgba(0,0,0,0.2)' } }}
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        label="Description"
                                        value={formData.description}
                                        onChange={(e) => setFormData({ ...formData, description: e.target.value.substring(0, 30) })}
                                        variant="outlined"
                                        helperText={`${formData.description.length}/30 characters`}
                                        sx={{ '& .MuiOutlinedInput-root': { bgcolor: 'rgba(0,0,0,0.2)' } }}
                                    />
                                </Grid>
                            </Grid>
                        </Paper>
                    </Grid>

                    <Grid item xs={12} md={5}>
                        <Paper sx={{ p: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                            <Typography variant="subtitle2" sx={{ color: 'text.secondary', mb: 2, fontWeight: 700, textTransform: 'uppercase' }}>
                                Actions
                            </Typography>
                            <Divider sx={{ mb: 3, opacity: 0.1 }} />
                            
                            <Button
                                fullWidth
                                variant="contained"
                                startIcon={saving ? <CircularProgress size={20} color="inherit" /> : <SaveIcon />}
                                disabled={saving}
                                type="submit"
                                sx={{ py: 1.5 }}
                            >
                                {saving ? 'Saving...' : 'Save Changes'}
                            </Button>

                            <Button
                                fullWidth
                                variant="outlined"
                                sx={{ mt: 2, color: 'text.secondary', borderColor: 'rgba(255,255,255,0.1)' }}
                                onClick={() => navigate('/admin/locations')}
                                disabled={saving}
                            >
                                Cancel
                            </Button>
                        </Paper>
                    </Grid>
                </Grid>
            </form>
        </Box>
    );
};

export default AdminLocationEdit;
