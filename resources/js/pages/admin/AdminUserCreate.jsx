import React, { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Divider,
    FormControlLabel,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Switch,
    TextField,
    Typography,
    CircularProgress,
} from '@mui/material';
import {
    ArrowBack as BackIcon,
    PersonAdd as CreateIcon,
    Person as PersonIcon,
    Security as SecurityIcon,
    Image as AvatarIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';

const avatarOptions = [
    { value: 'gravatar', label: 'Gravatar' },
    { value: 'url', label: 'URL' },
    { value: 'custom', label: 'Custom Override' },
    { value: 'google', label: 'Google' },
    { value: 'discord', label: 'Discord' },
    { value: 'github', label: 'GitHub' },
    { value: 'reddit', label: 'Reddit' },
];

const AdminUserCreate = () => {
    const navigate = useNavigate();
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const [formData, setFormData] = useState({
        username: '',
        email: '',
        password: '',
        first_name: '',
        last_name: '',
        avatar_provider: 'gravatar',
        avatar_url: '',
        custom_avatar_url: '',
        is_admin: false,
        is_suspended: false,
    });

    const setField = (field, value) => {
        setFormData((current) => ({ ...current, [field]: value }));
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError('');

        try {
            const response = await client.post('/v1/admin/users', formData);
            navigate(`/admin/users/${response.data.user.id}`);
        } catch (requestError) {
            const validationMessage = requestError.response?.data?.errors
                ? Object.values(requestError.response.data.errors).flat().join(' ')
                : null;
            setError(requestError.response?.data?.message || validationMessage || 'Failed to create user.');
            setSaving(false);
        }
    };

    return (
        <Box>
            <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate('/admin/users')}>
                    <BackIcon />
                </IconButton>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>
                        Create User
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Create an account with the same admin-facing profile and security options exposed in the users console.
                    </Typography>
                </Box>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}

            <form onSubmit={handleSubmit}>
                <Grid container spacing={3}>
                    <Grid item xs={12} md={8}>
                        <Paper sx={{ p: 4, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 3, display: 'flex', alignItems: 'center', gap: 1 }}>
                                <PersonIcon sx={{ color: 'primary.main' }} /> Identity
                            </Typography>

                            <Grid container spacing={2}>
                                <Grid item xs={12} sm={6}>
                                    <TextField fullWidth required label="Username" value={formData.username} onChange={(event) => setField('username', event.target.value)} />
                                </Grid>
                                <Grid item xs={12} sm={6}>
                                    <TextField fullWidth required type="email" label="Email" value={formData.email} onChange={(event) => setField('email', event.target.value)} />
                                </Grid>
                                <Grid item xs={12} sm={6}>
                                    <TextField fullWidth label="First Name" value={formData.first_name} onChange={(event) => setField('first_name', event.target.value)} />
                                </Grid>
                                <Grid item xs={12} sm={6}>
                                    <TextField fullWidth label="Last Name" value={formData.last_name} onChange={(event) => setField('last_name', event.target.value)} />
                                </Grid>
                            </Grid>
                        </Paper>

                        <Paper sx={{ p: 4, mt: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 3, display: 'flex', alignItems: 'center', gap: 1 }}>
                                <AvatarIcon sx={{ color: 'primary.main' }} /> Avatar
                            </Typography>

                            <Grid container spacing={2}>
                                <Grid item xs={12} sm={6}>
                                    <TextField
                                        fullWidth
                                        select
                                        label="Avatar Provider"
                                        value={formData.avatar_provider}
                                        onChange={(event) => setField('avatar_provider', event.target.value)}
                                    >
                                        {avatarOptions.map((option) => (
                                            <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                                <Grid item xs={12} sm={6}>
                                    <TextField
                                        fullWidth
                                        label="Avatar URL"
                                        placeholder="https://example.com/avatar.png"
                                        value={formData.avatar_url}
                                        onChange={(event) => setField('avatar_url', event.target.value)}
                                        helperText="Optional source avatar. Useful with external providers or direct URL mode."
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        label="Custom Avatar Override URL"
                                        placeholder="https://example.com/custom.png"
                                        value={formData.custom_avatar_url}
                                        onChange={(event) => setField('custom_avatar_url', event.target.value)}
                                        helperText="If set, this overrides the computed avatar everywhere."
                                    />
                                </Grid>
                            </Grid>
                        </Paper>

                        <Paper sx={{ p: 4, mt: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 3, display: 'flex', alignItems: 'center', gap: 1 }}>
                                <SecurityIcon sx={{ color: 'primary.main' }} /> Security & Access
                            </Typography>

                            <Grid container spacing={2}>
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        required
                                        type="password"
                                        label="Password"
                                        value={formData.password}
                                        onChange={(event) => setField('password', event.target.value)}
                                        helperText="Minimum 8 characters."
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <FormControlLabel
                                        control={<Switch checked={formData.is_admin} onChange={(event) => setField('is_admin', event.target.checked)} />}
                                        label="Grant administrator access"
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <FormControlLabel
                                        control={<Switch checked={formData.is_suspended} onChange={(event) => setField('is_suspended', event.target.checked)} />}
                                        label="Create in suspended state"
                                    />
                                </Grid>
                            </Grid>
                        </Paper>
                    </Grid>

                    <Grid item xs={12} md={4}>
                        <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle2" sx={{ color: 'text.secondary', textTransform: 'uppercase', fontWeight: 700, mb: 2 }}>
                                Actions
                            </Typography>
                            <Divider sx={{ mb: 3, opacity: 0.12 }} />

                            <Button fullWidth type="submit" variant="contained" startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <CreateIcon />} disabled={saving}>
                                {saving ? 'Creating...' : 'Create User'}
                            </Button>
                            <Button fullWidth variant="outlined" sx={{ mt: 2 }} onClick={() => navigate('/admin/users')} disabled={saving}>
                                Cancel
                            </Button>
                        </Paper>
                    </Grid>
                </Grid>
            </form>
        </Box>
    );
};

export default AdminUserCreate;
