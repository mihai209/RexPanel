import React, { useEffect, useState } from 'react';
import {
    Alert,
    Avatar,
    Box,
    Button,
    Chip,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Divider,
    FormControlLabel,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Snackbar,
    Stack,
    Switch,
    TextField,
    Typography,
} from '@mui/material';
import {
    ArrowBack as BackIcon,
    Delete as DeleteIcon,
    Forum as DiscordIcon,
    GitHub as GitHubIcon,
    Google as GoogleIcon,
    LinkOff as UnlinkIcon,
    LockReset as Disable2FaIcon,
    Reddit as RedditIcon,
    Refresh as ResetQuotaIcon,
    Save as SaveIcon,
    Security as SecurityIcon,
    ShieldOutlined as ShieldOutlinedIcon,
} from '@mui/icons-material';
import { motion } from 'framer-motion';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';

const avatarOptions = [
    { value: 'gravatar', label: 'Gravatar' },
    { value: 'url', label: 'URL' },
    { value: 'custom', label: 'Custom Override' },
    { value: 'google', label: 'Google' },
    { value: 'discord', label: 'Discord' },
    { value: 'github', label: 'GitHub' },
    { value: 'reddit', label: 'Reddit' },
];

const providerMeta = {
    google: { icon: GoogleIcon, label: 'Google', color: '#DB4437' },
    github: { icon: GitHubIcon, label: 'GitHub', color: '#f5f5f5' },
    reddit: { icon: RedditIcon, label: 'Reddit', color: '#FF4500' },
    discord: { icon: DiscordIcon, label: 'Discord', color: '#5865F2' },
};

const emptyForm = {
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
    coins: 0,
};

const pageSectionMotion = {
    initial: { opacity: 0, y: 10 },
    animate: { opacity: 1, y: 0 },
    transition: { duration: 0.22 },
};

const panelSx = {
    borderRadius: 4,
    border: '1px solid rgba(255,255,255,0.06)',
    background: 'linear-gradient(180deg, rgba(22,27,34,0.98) 0%, rgba(14,18,24,0.98) 100%)',
    boxShadow: '0 20px 60px rgba(0,0,0,0.25)',
};

const fieldSx = {
    '& .MuiOutlinedInput-root': {
        borderRadius: 2.5,
        backgroundColor: 'rgba(255,255,255,0.02)',
    },
};

const MetricPill = ({ label, value, tone = 'default' }) => {
    const tones = {
        default: { bg: 'rgba(255,255,255,0.04)', border: 'rgba(255,255,255,0.08)', color: 'text.primary' },
        info: { bg: 'rgba(56,189,248,0.12)', border: 'rgba(56,189,248,0.3)', color: '#7dd3fc' },
        success: { bg: 'rgba(34,197,94,0.12)', border: 'rgba(34,197,94,0.3)', color: '#86efac' },
        warning: { bg: 'rgba(245,158,11,0.12)', border: 'rgba(245,158,11,0.3)', color: '#fcd34d' },
        danger: { bg: 'rgba(239,68,68,0.12)', border: 'rgba(239,68,68,0.3)', color: '#fca5a5' },
    };
    const style = tones[tone] || tones.default;

    return (
        <Box
            sx={{
                px: 1.5,
                py: 1.25,
                borderRadius: 2.5,
                border: `1px solid ${style.border}`,
                bgcolor: style.bg,
                minWidth: 104,
            }}
        >
            <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.4 }}>
                {label}
            </Typography>
            <Typography variant="body2" sx={{ fontWeight: 800, color: style.color }}>
                {value}
            </Typography>
        </Box>
    );
};

const SectionTitle = ({ eyebrow, title, description, action = null }) => (
    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 2, mb: 3 }}>
        <Box>
            <Typography variant="caption" sx={{ textTransform: 'uppercase', letterSpacing: '0.12em', color: 'text.secondary', fontWeight: 700 }}>
                {eyebrow}
            </Typography>
            <Typography variant="h6" sx={{ fontWeight: 800, mt: 0.6, mb: 0.5 }}>
                {title}
            </Typography>
            {description && (
                <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 620 }}>
                    {description}
                </Typography>
            )}
        </Box>
        {action}
    </Box>
);

const LinkedProviderRow = ({ provider, unlinking, onUnlink }) => {
    const meta = providerMeta[provider.provider] || { icon: ShieldOutlinedIcon, label: provider.provider, color: '#94a3b8' };
    const Icon = meta.icon;

    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 2,
                px: 2.25,
                py: 1.75,
                borderRadius: 3,
                border: '1px solid rgba(255,255,255,0.06)',
                bgcolor: 'rgba(255,255,255,0.02)',
            }}
        >
            <Stack direction="row" spacing={1.5} alignItems="center">
                <Box
                    sx={{
                        width: 38,
                        height: 38,
                        borderRadius: '50%',
                        display: 'grid',
                        placeItems: 'center',
                        bgcolor: 'rgba(255,255,255,0.04)',
                        border: '1px solid rgba(255,255,255,0.08)',
                    }}
                >
                    <Icon sx={{ color: meta.color, fontSize: 20 }} />
                </Box>
                <Box>
                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                        {meta.label}
                    </Typography>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                        {provider.provider_email || provider.provider_username || 'Linked account'}
                    </Typography>
                </Box>
            </Stack>
            <Button
                size="small"
                color="error"
                variant="outlined"
                startIcon={unlinking ? <CircularProgress size={14} color="inherit" /> : <UnlinkIcon />}
                disabled={unlinking}
                onClick={onUnlink}
                sx={{ borderRadius: 999 }}
            >
                Unlink
            </Button>
        </Box>
    );
};

const AdminUserEdit = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const { user: currentUser } = useAuth();
    const [user, setUser] = useState(null);
    const [formData, setFormData] = useState(emptyForm);
    const [quotaInput, setQuotaInput] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [busyKey, setBusyKey] = useState('');
    const [error, setError] = useState('');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [snackbar, setSnackbar] = useState({ open: false, severity: 'success', message: '' });

    const isSelf = Number(currentUser?.id) === Number(id);

    const showSnackbar = (severity, message) => {
        setSnackbar({ open: true, severity, message });
    };

    const hydrate = (payload) => {
        setUser(payload);
        setFormData({
            username: payload.username || '',
            email: payload.email || '',
            password: '',
            first_name: payload.first_name || '',
            last_name: payload.last_name || '',
            avatar_provider: payload.avatar_provider || 'gravatar',
            avatar_url: payload.avatar_url || '',
            custom_avatar_url: payload.custom_avatar_url || '',
            is_admin: !!payload.is_admin,
            is_suspended: !!payload.is_suspended,
            coins: payload.coins ?? 0,
        });
        setQuotaInput(payload.ai_quota_override ?? '');
    };

    const fetchUser = async () => {
        setLoading(true);
        setError('');

        try {
            const response = await client.get(`/v1/admin/users/${id}`);
            hydrate(response.data);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to load user details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUser();
    }, [id]);

    const setField = (field, value) => {
        setFormData((current) => ({ ...current, [field]: value }));
    };

    const withBusy = async (key, callback) => {
        setBusyKey(key);
        try {
            await callback();
        } catch (requestError) {
            const validationMessage = requestError.response?.data?.errors
                ? Object.values(requestError.response.data.errors).flat().join(' ')
                : null;
            setError(requestError.response?.data?.message || validationMessage || 'Action failed.');
        } finally {
            setBusyKey('');
        }
    };

    const handleSave = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError('');

        try {
            const payload = {
                ...formData,
                coins: Number(formData.coins || 0),
            };

            if (!payload.password) {
                delete payload.password;
            }

            const response = await client.put(`/v1/admin/users/${id}`, payload);
            hydrate(response.data.user);
            showSnackbar('success', response.data.message || 'User updated successfully.');
        } catch (requestError) {
            const validationMessage = requestError.response?.data?.errors
                ? Object.values(requestError.response.data.errors).flat().join(' ')
                : null;
            setError(requestError.response?.data?.message || validationMessage || 'Failed to update user.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        await withBusy('delete', async () => {
            try {
                await client.delete(`/v1/admin/users/${id}`);
                navigate('/admin/users');
            } catch (requestError) {
                setDeleteDialogOpen(false);
                setError(requestError.response?.data?.message || 'Failed to delete user.');
            }
        });
    };

    const saveQuota = async () => withBusy('quota-save', async () => {
        const payload = {
            daily_quota: quotaInput === '' ? null : Number(quotaInput),
        };

        const response = await client.post(`/v1/admin/users/${id}/ai-quota`, payload);
        hydrate(response.data.user);
        showSnackbar('success', response.data.message || 'AI quota updated.');
    });

    const resetQuota = async () => withBusy('quota-reset', async () => {
        const response = await client.post(`/v1/admin/users/${id}/ai-quota/reset`);
        hydrate(response.data.user);
        showSnackbar('success', response.data.message || 'AI quota reset.');
    });

    const disableTwoFactor = async () => withBusy('disable-2fa', async () => {
        const response = await client.post(`/v1/admin/users/${id}/disable-2fa`);
        hydrate(response.data.user);
        showSnackbar('success', response.data.message || 'Two-factor authentication disabled.');
    });

    const unlinkProvider = async (provider) => withBusy(`unlink-${provider}`, async () => {
        const response = await client.post(`/v1/admin/users/${id}/unlink/${provider}`);
        hydrate(response.data.user);
        showSnackbar('success', response.data.message || 'Linked account removed.');
    });

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 12 }}>
                <CircularProgress />
            </Box>
        );
    }

    const displayName = user?.full_name || user?.username || 'User';
    const createdDate = user?.created_at ? new Date(user.created_at).toLocaleDateString() : 'Unknown';

    return (
        <Box sx={{ maxWidth: 1440, mx: 'auto', pb: 6 }}>
            <motion.div {...pageSectionMotion}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, mb: 3, flexWrap: 'wrap' }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    <IconButton onClick={() => navigate('/admin/users')}>
                        <BackIcon />
                    </IconButton>
                        <Box>
                            <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.03em' }}>
                                {displayName}
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                User profile, authentication state, quota controls, and linked provider management.
                            </Typography>
                        </Box>
                    </Box>
                    <Button
                        variant="contained"
                        type="submit"
                        form="admin-user-edit-form"
                        startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />}
                        disabled={saving}
                        sx={{ borderRadius: 999, px: 2.5 }}
                    >
                        {saving ? 'Saving...' : 'Save User Changes'}
                    </Button>
                </Box>
            </motion.div>

            {error && <Alert severity="error" sx={{ mb: 3, borderRadius: 3 }}>{error}</Alert>}

            <Box component="form" id="admin-user-edit-form" onSubmit={handleSave}>
                <Stack spacing={3}>
                    <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.04 }}>
                        <Paper sx={{ ...panelSx, p: 0, overflow: 'hidden' }}>
                            <Box
                                sx={{
                                    px: { xs: 2.5, md: 3.25 },
                                    py: { xs: 2.5, md: 3 },
                                    background: 'radial-gradient(circle at top left, rgba(56,189,248,0.22), transparent 34%), linear-gradient(180deg, #121923 0%, #0d1219 100%)',
                                    borderBottom: '1px solid rgba(255,255,255,0.06)',
                                }}
                            >
                                <Grid container spacing={3} alignItems="center">
                                    <Grid item xs={12} lg={5}>
                                        <Stack direction="row" spacing={2.5} alignItems="center">
                                            <Avatar
                                                src={user?.avatar_url}
                                                sx={{
                                                    width: 92,
                                                    height: 92,
                                                    fontSize: 34,
                                                    border: '3px solid rgba(255,255,255,0.1)',
                                                    boxShadow: '0 12px 30px rgba(0,0,0,0.28)',
                                                }}
                                            >
                                                {user?.username?.[0]?.toUpperCase()}
                                            </Avatar>
                                            <Box sx={{ minWidth: 0 }}>
                                                <Typography variant="h5" sx={{ fontWeight: 900, lineHeight: 1.05, mb: 0.7 }}>
                                                    {displayName}
                                                </Typography>
                                                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>
                                                    @{user?.username} • #{user?.id}
                                                </Typography>
                                                <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
                                                    <Chip size="small" label={user?.is_admin ? 'Admin' : 'User'} color={user?.is_admin ? 'primary' : 'default'} />
                                                    {user?.is_suspended && <Chip size="small" label="Suspended" color="warning" variant="outlined" />}
                                                    <Chip
                                                        size="small"
                                                        icon={user?.two_factor_enabled ? <SecurityIcon sx={{ fontSize: '14px !important' }} /> : <ShieldOutlinedIcon sx={{ fontSize: '14px !important' }} />}
                                                        label={user?.two_factor_enabled ? '2FA On' : '2FA Off'}
                                                        color={user?.two_factor_enabled ? 'info' : 'default'}
                                                        variant="outlined"
                                                    />
                                                </Stack>
                                            </Box>
                                        </Stack>
                                    </Grid>
                                    <Grid item xs={12} lg={7}>
                                        <Grid container spacing={1.2}>
                                            <Grid item xs={6} md={3}>
                                                <MetricPill label="Servers" value={user?.servers_count ?? 0} tone="info" />
                                            </Grid>
                                            <Grid item xs={6} md={3}>
                                                <MetricPill label="Coins" value={user?.coins ?? 0} tone="default" />
                                            </Grid>
                                            <Grid item xs={6} md={3}>
                                                <MetricPill label="Quota Left" value={user?.ai_quota_remaining ?? 0} tone="success" />
                                            </Grid>
                                            <Grid item xs={6} md={3}>
                                                <MetricPill label="Used Today" value={user?.ai_quota_used_today ?? 0} tone="warning" />
                                            </Grid>
                                        </Grid>
                                    </Grid>
                                </Grid>
                            </Box>

                            <Box sx={{ p: { xs: 2.5, md: 3.25 } }}>
                                <Grid container spacing={2}>
                                    <Grid item xs={12} md={3}>
                                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>Email</Typography>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>{user?.email}</Typography>
                                    </Grid>
                                    <Grid item xs={12} sm={4} md={3}>
                                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>Avatar Source</Typography>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>{user?.avatar_provider || 'gravatar'}</Typography>
                                    </Grid>
                                    <Grid item xs={12} sm={4} md={3}>
                                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>Registered</Typography>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>{createdDate}</Typography>
                                    </Grid>
                                </Grid>
                            </Box>
                        </Paper>
                    </motion.div>

                    <Grid container spacing={3}>
                        <Grid item xs={12} xl={7}>
                            <Stack spacing={3}>
                            <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.08 }}>
                                <Paper sx={{ ...panelSx, p: 3.25 }}>
                                    <SectionTitle
                                        eyebrow="Profile"
                                        title="Identity"
                                        description="Core account fields, display identity, and avatar selection."
                                    />
                                    <Grid container spacing={2}>
                                        <Grid item xs={12} md={6}>
                                            <TextField fullWidth required label="Username" value={formData.username} onChange={(event) => setField('username', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                        <Grid item xs={12} md={6}>
                                            <TextField fullWidth required type="email" label="Email" value={formData.email} onChange={(event) => setField('email', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                        <Grid item xs={12} md={6}>
                                            <TextField fullWidth label="First Name" value={formData.first_name} onChange={(event) => setField('first_name', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                        <Grid item xs={12} md={6}>
                                            <TextField fullWidth label="Last Name" value={formData.last_name} onChange={(event) => setField('last_name', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                        <Grid item xs={12} md={6}>
                                            <TextField
                                                fullWidth
                                                select
                                                label="Avatar Provider"
                                                value={formData.avatar_provider}
                                                onChange={(event) => setField('avatar_provider', event.target.value)}
                                                sx={fieldSx}
                                            >
                                                {avatarOptions.map((option) => (
                                                    <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
                                                ))}
                                            </TextField>
                                        </Grid>
                                        <Grid item xs={12} md={6}>
                                            <TextField fullWidth label="Avatar URL" value={formData.avatar_url} onChange={(event) => setField('avatar_url', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                        <Grid item xs={12}>
                                            <TextField fullWidth label="Custom Avatar Override URL" value={formData.custom_avatar_url} onChange={(event) => setField('custom_avatar_url', event.target.value)} sx={fieldSx} />
                                        </Grid>
                                    </Grid>
                                </Paper>
                            </motion.div>

                            <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.16 }}>
                                <Paper sx={{ ...panelSx, p: 3.25 }}>
                                    <SectionTitle
                                        eyebrow="Providers"
                                        title="Linked Accounts"
                                        description="External sign-in connections currently attached to this account."
                                    />
                                    <Stack spacing={1.4}>
                                        {user?.linked_accounts?.length ? user.linked_accounts.map((provider) => (
                                            <LinkedProviderRow
                                                key={provider.provider}
                                                provider={provider}
                                                unlinking={busyKey === `unlink-${provider.provider}`}
                                                onUnlink={() => unlinkProvider(provider.provider)}
                                            />
                                        )) : (
                                            <Box sx={{ px: 2.2, py: 2, borderRadius: 3, border: '1px dashed rgba(255,255,255,0.12)', color: 'text.secondary' }}>
                                                No linked providers for this account.
                                            </Box>
                                        )}
                                    </Stack>
                                </Paper>
                            </motion.div>
                            </Stack>
                        </Grid>

                        <Grid item xs={12} xl={5}>
                            <Stack spacing={3}>
                                <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.12 }}>
                                    <Paper sx={{ ...panelSx, p: 3.25 }}>
                                        <SectionTitle
                                            eyebrow="Access"
                                            title="Security and Runtime Flags"
                                            description="Permission state, password rotation, suspension state, and low-level account flags."
                                        />
                                        <Grid container spacing={2}>
                                            <Grid item xs={12}>
                                                <TextField
                                                    fullWidth
                                                    type="password"
                                                    label="Rotate Password"
                                                    placeholder="Leave empty to keep current password"
                                                    value={formData.password}
                                                    onChange={(event) => setField('password', event.target.value)}
                                                    sx={fieldSx}
                                                />
                                            </Grid>
                                            <Grid item xs={12}>
                                                <TextField
                                                    fullWidth
                                                    type="number"
                                                    label="Coins"
                                                    value={formData.coins}
                                                    onChange={(event) => setField('coins', event.target.value)}
                                                    sx={fieldSx}
                                                />
                                            </Grid>
                                            <Grid item xs={12}>
                                                <Box sx={{ px: 2.2, py: 1.75, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(255,255,255,0.02)' }}>
                                                    <FormControlLabel
                                                        control={<Switch checked={formData.is_admin} onChange={(event) => setField('is_admin', event.target.checked)} />}
                                                        label="Administrator access"
                                                    />
                                                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', pl: 4.6 }}>
                                                        Grants access to the full admin surface.
                                                    </Typography>
                                                </Box>
                                            </Grid>
                                            <Grid item xs={12}>
                                                <Box sx={{ px: 2.2, py: 1.75, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(255,255,255,0.02)' }}>
                                                    <FormControlLabel
                                                        control={<Switch checked={formData.is_suspended} onChange={(event) => setField('is_suspended', event.target.checked)} disabled={isSelf} />}
                                                        label={isSelf ? 'Self-suspension is blocked' : 'Suspend account'}
                                                    />
                                                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', pl: 4.6 }}>
                                                        Suspension revokes active tokens and blocks authenticated routes.
                                                    </Typography>
                                                </Box>
                                            </Grid>
                                        </Grid>
                                    </Paper>
                                </motion.div>

                                <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.2 }}>
                                    <Paper sx={{ ...panelSx, p: 3.25 }}>
                                            <SectionTitle
                                                eyebrow="AI"
                                                title="Daily Quota Control"
                                                description="Override the default limit or clear only today's consumed usage."
                                            />
                                            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2.5 }}>
                                                Remaining today: <strong>{user?.ai_quota_remaining ?? 0}</strong> of <strong>{user?.ai_quota_limit ?? 0}</strong>. Used: {user?.ai_quota_used_today ?? 0}.
                                            </Typography>
                                            <TextField
                                                fullWidth
                                                type="number"
                                                label="Daily Quota Override"
                                                placeholder="Leave empty for default"
                                                value={quotaInput}
                                                onChange={(event) => setQuotaInput(event.target.value)}
                                                sx={{ ...fieldSx, mb: 2 }}
                                            />
                                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}>
                                                <Button
                                                    fullWidth
                                                    variant="contained"
                                                    onClick={saveQuota}
                                                    disabled={busyKey === 'quota-save'}
                                                    sx={{ borderRadius: 999 }}
                                                >
                                                    {busyKey === 'quota-save' ? <CircularProgress size={16} color="inherit" /> : 'Save Override'}
                                                </Button>
                                                <Button
                                                    fullWidth
                                                    variant="outlined"
                                                    onClick={resetQuota}
                                                    disabled={busyKey === 'quota-reset'}
                                                    startIcon={busyKey === 'quota-reset' ? <CircularProgress size={14} color="inherit" /> : <ResetQuotaIcon />}
                                                    sx={{ borderRadius: 999 }}
                                                >
                                                    Reset Today
                                                </Button>
                                            </Stack>
                                    </Paper>
                                </motion.div>

                                <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.22 }}>
                                    <Paper sx={{ ...panelSx, p: 3.25 }}>
                                            <SectionTitle
                                                eyebrow="Authentication"
                                                title="Two-Factor State"
                                                description="Admin-side visibility and reset control for account 2FA."
                                            />
                                            <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" sx={{ mb: 2.5 }}>
                                                <Chip
                                                    size="small"
                                                    label={user?.two_factor_enabled ? '2FA Enabled' : '2FA Disabled'}
                                                    color={user?.two_factor_enabled ? 'info' : 'default'}
                                                    variant="outlined"
                                                />
                                                {user?.is_suspended && <Chip size="small" label="User Suspended" color="warning" variant="outlined" />}
                                            </Stack>
                                            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2.5 }}>
                                                Disable 2FA only when recovery or admin intervention is necessary.
                                            </Typography>
                                            <Button
                                                variant="outlined"
                                                color="warning"
                                                onClick={disableTwoFactor}
                                                disabled={!user?.two_factor_enabled || busyKey === 'disable-2fa'}
                                                startIcon={busyKey === 'disable-2fa' ? <CircularProgress size={14} color="inherit" /> : <Disable2FaIcon />}
                                                sx={{ borderRadius: 999 }}
                                            >
                                                Disable 2FA
                                            </Button>
                                    </Paper>
                                </motion.div>

                                <motion.div {...pageSectionMotion} transition={{ duration: 0.22, delay: 0.24 }}>
                                    <Paper
                                        sx={{
                                            ...panelSx,
                                            p: 3.25,
                                            borderColor: 'rgba(239,68,68,0.18)',
                                            background: 'linear-gradient(180deg, rgba(46,17,20,0.94) 0%, rgba(20,12,15,0.98) 100%)',
                                        }}
                                    >
                                        <SectionTitle
                                            eyebrow="Danger"
                                            title="Destructive Actions"
                                            description="Deleting the user removes the account and linked providers. Self-delete remains blocked."
                                            action={
                                                <Button
                                                    color="error"
                                                    variant="contained"
                                                    startIcon={<DeleteIcon />}
                                                    disabled={isSelf}
                                                    onClick={() => setDeleteDialogOpen(true)}
                                                    sx={{ borderRadius: 999 }}
                                                >
                                                    {isSelf ? 'Cannot Delete Self' : 'Delete User'}
                                                </Button>
                                            }
                                        />
                                    </Paper>
                                </motion.div>
                            </Stack>
                        </Grid>
                    </Grid>
                </Stack>
            </Box>

            <Dialog open={deleteDialogOpen} onClose={() => setDeleteDialogOpen(false)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ fontWeight: 800 }}>Delete User?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Delete <strong>{user?.username}</strong>? This permanently removes the account and all linked providers.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setDeleteDialogOpen(false)} disabled={busyKey === 'delete'}>Cancel</Button>
                    <Button onClick={handleDelete} color="error" variant="contained" disabled={busyKey === 'delete'}>
                        {busyKey === 'delete' ? <CircularProgress size={18} color="inherit" /> : 'Delete'}
                    </Button>
                </DialogActions>
            </Dialog>

            <Snackbar
                open={snackbar.open}
                autoHideDuration={4000}
                onClose={() => setSnackbar((current) => ({ ...current, open: false }))}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
            >
                <Alert severity={snackbar.severity} variant="filled" onClose={() => setSnackbar((current) => ({ ...current, open: false }))}>
                    {snackbar.message}
                </Alert>
            </Snackbar>
        </Box>
    );
};

export default AdminUserEdit;
