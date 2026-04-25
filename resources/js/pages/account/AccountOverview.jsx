import React, { useState, useEffect } from 'react';
import {
    Box, Paper, Typography, TextField, Button, Alert, CircularProgress,
    Chip, Stack, Dialog, DialogTitle, DialogContent, DialogActions, Divider
} from '@mui/material';
import {
    AdminPanelSettings as AdminIcon,
    Lock as LockIcon,
    Save as SaveIcon,
    Link as LinkIcon,
    LinkOff as LinkOffIcon,
} from '@mui/icons-material';
import { useAuth } from '../../context/AuthContext';
import client from '../../api/client';
import TwoFactorQrCode from '../../components/TwoFactorQrCode';

const AccountOverview = () => {
    const { user, setUser, logout } = useAuth();

    // Details State
    const [details, setDetails] = useState({ username: '', email: '' });
    const [confirmModalOpen, setConfirmModalOpen] = useState(false);
    const [confirmPassword, setConfirmPassword] = useState('');
    const [detailsLoading, setDetailsLoading] = useState(false);
    const [detailsError, setDetailsError] = useState('');
    const [detailsSuccess, setDetailsSuccess] = useState('');

    // Password State
    const [cpCurrentPassword, setCpCurrentPassword] = useState('');
    const [cpNewPassword, setCpNewPassword] = useState('');
    const [cpConfirmPassword, setCpConfirmPassword] = useState('');
    const [cpLoading, setCpLoading] = useState(false);
    const [cpSuccess, setCpSuccess] = useState('');
    const [cpError, setCpError] = useState('');
    const [providers, setProviders] = useState([]);
    const [providerBusy, setProviderBusy] = useState('');
    const [providerMessage, setProviderMessage] = useState({ type: '', text: '' });
    const [twoFactorSetup, setTwoFactorSetup] = useState({ loading: false, secret: '', otpauthUrl: '', code: '', error: '', success: '' });
    const [twoFactorDisable, setTwoFactorDisable] = useState({ loading: false, password: '', error: '', success: '' });

    useEffect(() => {
        if (user) {
            setDetails({ username: user.username, email: user.email });
        }
    }, [user]);

    useEffect(() => {
        const loadLinkedAccounts = async () => {
            try {
                const { data } = await client.get('/v1/account/linked-accounts');
                setProviders(data.providers || []);
            } catch (err) {
                setProviderMessage({ type: 'error', text: 'Failed to load linked accounts.' });
            }
        };

        const hashParams = new URLSearchParams(window.location.hash.replace(/^#/, ''));
        const oauthStatus = hashParams.get('oauth_status');
        const provider = hashParams.get('provider');
        const oauthError = hashParams.get('oauth_error');

        if (oauthError) {
            setProviderMessage({ type: 'error', text: oauthError });
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        } else if (oauthStatus === 'linked' && provider) {
            setProviderMessage({ type: 'success', text: `${provider.charAt(0).toUpperCase() + provider.slice(1)} linked successfully.` });
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        }

        loadLinkedAccounts();
    }, []);

    const handleDetailsUpdatePrompt = (e) => {
        e.preventDefault();
        setDetailsError('');
        setDetailsSuccess('');
        if (details.username === user.username && details.email === user.email) {
            setDetailsError('No changes detected.');
            return;
        }
        setConfirmPassword('');
        setConfirmModalOpen(true);
    };

    const submitDetailsUpdate = async () => {
        setDetailsError('');
        setDetailsLoading(true);
        try {
            const res = await client.put('/v1/account/details', {
                username: details.username,
                email: details.email,
                password: confirmPassword,
            });
            setDetailsSuccess(res.data.message);
            setUser(res.data.user);
            setConfirmModalOpen(false);
        } catch (err) {
            setDetailsError(err.response?.data?.message ?? 'Failed to update details.');
            setConfirmModalOpen(false);
        } finally {
            setDetailsLoading(false);
        }
    };

    const handlePasswordChange = async (e) => {
        e.preventDefault();
        setCpError('');
        setCpSuccess('');

        if (cpNewPassword !== cpConfirmPassword) {
            setCpError('New passwords do not match.');
            return;
        }

        setCpLoading(true);
        try {
            const res = await client.put('/v1/account/password', {
                current_password: cpCurrentPassword,
                password: cpNewPassword,
                password_confirmation: cpConfirmPassword,
            });
            setCpSuccess(res.data.message);
            setTimeout(() => logout(), 2000);
        } catch (err) {
            setCpError(err.response?.data?.message ?? 'Something went wrong.');
        } finally {
            setCpLoading(false);
        }
    };

    const handleLink = async (provider) => {
        setProviderBusy(provider);
        setProviderMessage({ type: '', text: '' });
        try {
            const { data } = await client.post(`/v1/account/linked-accounts/${provider}/redirect`);
            window.location.href = data.redirect_url;
        } catch (err) {
            setProviderMessage({ type: 'error', text: err.response?.data?.message || 'Failed to start account linking.' });
            setProviderBusy('');
        }
    };

    const handleUnlink = async (provider) => {
        setProviderBusy(provider);
        setProviderMessage({ type: '', text: '' });
        try {
            const { data } = await client.delete(`/v1/account/linked-accounts/${provider}`);
            setProviders((current) => current.map((entry) => entry.key === provider ? { ...entry, linked: false, link: null } : entry));
            setProviderMessage({ type: 'success', text: data.message });
        } catch (err) {
            setProviderMessage({ type: 'error', text: err.response?.data?.message || 'Failed to unlink provider.' });
        } finally {
            setProviderBusy('');
        }
    };

    const startTwoFactorSetup = async () => {
        setTwoFactorSetup((current) => ({ ...current, loading: true, error: '', success: '' }));
        try {
            const { data } = await client.get('/v1/account/2fa/setup');
            setTwoFactorSetup({
                loading: false,
                secret: data.secret || '',
                otpauthUrl: data.otpauth_url || '',
                code: '',
                error: '',
                success: '',
            });
        } catch (err) {
            setTwoFactorSetup((current) => ({
                ...current,
                loading: false,
                error: err.response?.data?.message || 'Failed to initialize 2FA setup.',
            }));
        }
    };

    const enableTwoFactor = async (event) => {
        event.preventDefault();
        setTwoFactorSetup((current) => ({ ...current, loading: true, error: '', success: '' }));

        try {
            const { data } = await client.post('/v1/account/2fa/enable', {
                code: twoFactorSetup.code,
            });
            setUser(data.user);
            setTwoFactorSetup({
                loading: false,
                secret: '',
                otpauthUrl: '',
                code: '',
                error: '',
                success: data.message || 'Two-factor authentication enabled.',
            });
        } catch (err) {
            setTwoFactorSetup((current) => ({
                ...current,
                loading: false,
                error: err.response?.data?.message || 'Failed to enable 2FA.',
            }));
        }
    };

    const disableTwoFactor = async (event) => {
        event.preventDefault();
        setTwoFactorDisable((current) => ({ ...current, loading: true, error: '', success: '' }));

        try {
            const { data } = await client.post('/v1/account/2fa/disable', {
                password: twoFactorDisable.password,
            });
            setUser(data.user);
            setTwoFactorDisable({
                loading: false,
                password: '',
                error: '',
                success: data.message || 'Two-factor authentication disabled.',
            });
            setTwoFactorSetup({ loading: false, secret: '', otpauthUrl: '', code: '', error: '', success: '' });
        } catch (err) {
            setTwoFactorDisable((current) => ({
                ...current,
                loading: false,
                error: err.response?.data?.message || 'Failed to disable 2FA.',
            }));
        }
    };

    return (
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', xl: '1.1fr 0.9fr' }, gap: 3 }}>
            
            <Stack spacing={3}>
                <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, p: 4, display: 'flex', flexDirection: 'column' }}>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 800, color: '#e2e8f0' }}>
                            Your Information
                        </Typography>
                        {user?.is_admin && (
                            <Chip
                                icon={<AdminIcon sx={{ fontSize: 16 }} />}
                                label="Administrator"
                                size="small"
                                sx={{ bgcolor: 'rgba(54,211,153,0.12)', color: '#36d399', fontWeight: 700, fontSize: '0.65rem' }}
                            />
                        )}
                    </Box>

                    {detailsError && <Alert severity="error" variant="filled" sx={{ mb: 3, bgcolor: '#ef4444' }}>{detailsError}</Alert>}
                    {detailsSuccess && <Alert severity="success" sx={{ mb: 3 }}>{detailsSuccess}</Alert>}

                    <form onSubmit={handleDetailsUpdatePrompt} style={{ display: 'flex', flexDirection: 'column', flexGrow: 1 }}>
                        <Stack spacing={3} sx={{ flexGrow: 1 }}>
                            <Box>
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                    Username
                                </Typography>
                                <TextField
                                    type="text"
                                    fullWidth
                                    value={details.username}
                                    onChange={e => setDetails(e.target.value ? { ...details, username: e.target.value } : details)}
                                    required
                                    size="small"
                                />
                            </Box>

                            <Box>
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                    Email Address
                                </Typography>
                                <TextField
                                    type="email"
                                    fullWidth
                                    value={details.email}
                                    onChange={e => setDetails(e.target.value ? { ...details, email: e.target.value } : details)}
                                    required
                                    size="small"
                                />
                            </Box>

                            <Box sx={{ mt: 'auto', pt: 2, borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={detailsLoading || (details.username === user?.username && details.email === user?.email)}
                                    startIcon={detailsLoading ? <CircularProgress size={20} color="inherit" /> : <SaveIcon />}
                                    sx={{ float: 'right' }}
                                >
                                    Save Details
                                </Button>
                            </Box>
                        </Stack>
                    </form>
                </Paper>

                <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, p: 4 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 800, color: '#e2e8f0', mb: 2 }}>
                        Linked Accounts
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2.5 }}>
                        Connect the same third-party providers used on the login page. Existing linked providers remain visible even if an admin disables them later.
                    </Typography>
                    {providerMessage.text && (
                        <Alert severity={providerMessage.type || 'info'} sx={{ mb: 2 }}>
                            {providerMessage.text}
                        </Alert>
                    )}
                    <Stack spacing={1.5}>
                        {providers.map((provider) => (
                            <Paper
                                key={provider.key}
                                elevation={0}
                                sx={{
                                    p: 2,
                                    bgcolor: 'rgba(255,255,255,0.02)',
                                    border: '1px solid rgba(255,255,255,0.05)',
                                    borderRadius: 2,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: 2,
                                }}
                            >
                                <Box>
                                    <Typography variant="body1" sx={{ fontWeight: 700 }}>
                                        {provider.name}
                                    </Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                        {provider.linked
                                            ? (provider.link?.provider_email || provider.link?.provider_username || 'Connected')
                                            : (provider.enabled ? 'Available to connect' : 'Disabled by an administrator')}
                                    </Typography>
                                </Box>
                                {provider.linked ? (
                                    <Button
                                        color="error"
                                        variant="outlined"
                                        startIcon={<LinkOffIcon />}
                                        disabled={providerBusy === provider.key || !provider.can_unlink}
                                        onClick={() => handleUnlink(provider.key)}
                                    >
                                        Unlink
                                    </Button>
                                ) : (
                                    <Button
                                        variant="contained"
                                        startIcon={<LinkIcon />}
                                        disabled={providerBusy === provider.key || !provider.can_link}
                                        onClick={() => handleLink(provider.key)}
                                    >
                                        Connect
                                    </Button>
                                )}
                            </Paper>
                        ))}
                    </Stack>
                </Paper>
            </Stack>

            <Stack spacing={3}>
                <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, p: 4, display: 'flex', flexDirection: 'column', gap: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 800, color: '#e2e8f0', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <LockIcon sx={{ fontSize: 20 }} /> Two-Factor Authentication
                    </Typography>

                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Match the CPanel flow: scan the QR code in your authenticator app, verify one code, then use that code during future logins.
                    </Typography>

                    <Chip
                        label={user?.twoFactorEnabled || user?.two_factor_enabled ? '2FA Active' : '2FA Inactive'}
                        size="small"
                        color={user?.twoFactorEnabled || user?.two_factor_enabled ? 'success' : 'default'}
                        variant={user?.twoFactorEnabled || user?.two_factor_enabled ? 'filled' : 'outlined'}
                        sx={{ alignSelf: 'flex-start' }}
                    />

                    {twoFactorSetup.error && <Alert severity="error" variant="filled" sx={{ bgcolor: '#ef4444' }}>{twoFactorSetup.error}</Alert>}
                    {twoFactorSetup.success && <Alert severity="success">{twoFactorSetup.success}</Alert>}
                    {twoFactorDisable.error && <Alert severity="error" variant="filled" sx={{ bgcolor: '#ef4444' }}>{twoFactorDisable.error}</Alert>}
                    {twoFactorDisable.success && <Alert severity="success">{twoFactorDisable.success}</Alert>}

                    {user?.twoFactorEnabled || user?.two_factor_enabled ? (
                        <form onSubmit={disableTwoFactor}>
                            <Stack spacing={2.5}>
                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                    Disable 2FA only if you are rotating devices or recovering access.
                                </Typography>
                                <Box>
                                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                        Current Password
                                    </Typography>
                                    <TextField
                                        type="password"
                                        fullWidth
                                        value={twoFactorDisable.password}
                                        onChange={e => setTwoFactorDisable((current) => ({ ...current, password: e.target.value }))}
                                        required
                                        size="small"
                                    />
                                </Box>
                                <Box>
                                    <Button type="submit" variant="outlined" color="warning" disabled={twoFactorDisable.loading}>
                                        {twoFactorDisable.loading ? <CircularProgress size={20} color="inherit" /> : 'Disable 2FA'}
                                    </Button>
                                </Box>
                            </Stack>
                        </form>
                    ) : twoFactorSetup.otpauthUrl ? (
                        <form onSubmit={enableTwoFactor}>
                            <Stack spacing={2.5}>
                                <Box sx={{ display: 'flex', justifyContent: 'center' }}>
                                    <TwoFactorQrCode value={twoFactorSetup.otpauthUrl} />
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                        Manual Secret
                                    </Typography>
                                    <TextField fullWidth value={twoFactorSetup.secret} InputProps={{ readOnly: true }} size="small" />
                                </Box>
                                <Box>
                                    <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                        Verify 6-Digit Code
                                    </Typography>
                                    <TextField
                                        fullWidth
                                        value={twoFactorSetup.code}
                                        onChange={e => setTwoFactorSetup((current) => ({ ...current, code: e.target.value.replace(/\D+/g, '').slice(0, 6) }))}
                                        required
                                        size="small"
                                        placeholder="123456"
                                        inputProps={{ inputMode: 'numeric', pattern: '[0-9]*', maxLength: 6 }}
                                    />
                                </Box>
                                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                    <Button type="submit" variant="contained" disabled={twoFactorSetup.loading}>
                                        {twoFactorSetup.loading ? <CircularProgress size={20} color="inherit" /> : 'Enable 2FA'}
                                    </Button>
                                    <Button variant="outlined" disabled={twoFactorSetup.loading} onClick={() => setTwoFactorSetup({ loading: false, secret: '', otpauthUrl: '', code: '', error: '', success: '' })}>
                                        Cancel Setup
                                    </Button>
                                </Stack>
                            </Stack>
                        </form>
                    ) : (
                        <Button variant="contained" onClick={startTwoFactorSetup} disabled={twoFactorSetup.loading} sx={{ alignSelf: 'flex-start' }}>
                            {twoFactorSetup.loading ? <CircularProgress size={20} color="inherit" /> : 'Enable 2FA'}
                        </Button>
                    )}
                </Paper>

                <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, p: 4, display: 'flex', flexDirection: 'column', gap: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 3, color: '#e2e8f0', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <LockIcon sx={{ fontSize: 20 }} /> Update Password
                    </Typography>

                    {cpError && <Alert severity="error" variant="filled" sx={{ mb: 3, bgcolor: '#ef4444' }}>{cpError}</Alert>}
                    {cpSuccess && <Alert severity="success" sx={{ mb: 3 }}>{cpSuccess} Logging out…</Alert>}

                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                        Ensure your account is using a long, random password to stay secure.
                    </Typography>

                    <form onSubmit={handlePasswordChange} style={{ display: 'flex', flexDirection: 'column', flexGrow: 1 }}>
                        <Stack spacing={3} sx={{ flexGrow: 1 }}>
                            <Box>
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                    Current Password
                                </Typography>
                                <TextField
                                    type="password"
                                    fullWidth
                                    value={cpCurrentPassword}
                                    onChange={e => setCpCurrentPassword(e.target.value)}
                                    required
                                    size="small"
                                />
                            </Box>

                            <Box>
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                    New Password
                                </Typography>
                                <TextField
                                    type="password"
                                    fullWidth
                                    value={cpNewPassword}
                                    onChange={e => setCpNewPassword(e.target.value)}
                                    required
                                    size="small"
                                />
                            </Box>

                            <Box>
                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                                    Confirm Password
                                </Typography>
                                <TextField
                                    type="password"
                                    fullWidth
                                    value={cpConfirmPassword}
                                    onChange={e => setCpConfirmPassword(e.target.value)}
                                    required
                                    size="small"
                                />
                            </Box>

                            <Box sx={{ mt: 'auto', pt: 2, borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                                <Button
                                    type="submit"
                                    variant="contained"
                                    color="error"
                                    disabled={cpLoading}
                                    sx={{ float: 'right' }}
                                >
                                    {cpLoading ? <CircularProgress size={20} color="inherit" /> : 'Update Password'}
                                </Button>
                            </Box>
                        </Stack>
                    </form>
                    <Divider sx={{ borderColor: 'rgba(255,255,255,0.05)' }} />
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Keeping at least one usable login method linked prevents lockouts until full recovery flows are added.
                    </Typography>
                </Paper>
            </Stack>

            {/* Confirm Details Update Dialog */}
            <Dialog
                open={confirmModalOpen}
                onClose={() => setConfirmModalOpen(false)}
                PaperProps={{ sx: { bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.08)', borderRadius: 2, minWidth: 350 } }}
            >
                <DialogTitle sx={{ fontWeight: 700 }}>Confirm Changes</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                        Please enter your current password to confirm account details updates.
                    </Typography>
                    <Box>
                        <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block', mb: 1 }}>
                            Current Password
                        </Typography>
                        <TextField
                            type="password"
                            fullWidth
                            value={confirmPassword}
                            onChange={e => setConfirmPassword(e.target.value)}
                            required
                            size="small"
                            autoFocus
                        />
                    </Box>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3 }}>
                    <Button onClick={() => setConfirmModalOpen(false)} variant="outlined" sx={{ borderColor: 'rgba(255,255,255,0.1)' }}>
                        Cancel
                    </Button>
                    <Button onClick={submitDetailsUpdate} color="primary" variant="contained" disabled={!confirmPassword || detailsLoading}>
                        {detailsLoading ? <CircularProgress size={18} color="inherit" /> : 'Confirm'}
                    </Button>
                </DialogActions>
            </Dialog>

        </Box>
    );
};

export default AccountOverview;
