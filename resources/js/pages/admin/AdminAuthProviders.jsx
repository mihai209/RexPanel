import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    Dialog,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    Grid,
    IconButton,
    InputAdornment,
    Paper,
    Stack,
    Switch,
    TextField,
    Typography,
} from '@mui/material';
import {
    Shield as ShieldIcon,
    AutoAwesome as AutoAwesomeIcon,
    OpenInNew as OpenInNewIcon,
    InfoOutlined as InfoIcon,
    Chat as DiscordIcon,
    GitHub as GitHubIcon,
    Reddit as RedditIcon,
    Google as GoogleIcon,
    Close as CloseIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const emptyProvider = {
    enabled: false,
    register_enabled: true,
    client_id: '',
    client_secret: '',
    client_id_configured: false,
    client_secret_configured: false,
    callback_url: '',
};

const providerVisuals = {
    discord: { icon: DiscordIcon, accent: '#5865F2', surface: 'rgba(88, 101, 242, 0.12)' },
    google: { icon: GoogleIcon, accent: '#EA4335', surface: 'rgba(234, 67, 53, 0.12)' },
    github: { icon: GitHubIcon, accent: '#d0d8e5', surface: 'rgba(208, 216, 229, 0.12)' },
    reddit: { icon: RedditIcon, accent: '#FF4500', surface: 'rgba(255, 69, 0, 0.12)' },
};

const providerTutorials = {
    discord: {
        title: 'Discord setup tutorial',
        steps: [
            'Open the Discord Developer Portal and create a new application.',
            'Go to OAuth2, copy the Client ID, generate a Client Secret, and paste both values here.',
            'Add the callback URL shown in RA-panel to the Redirects list exactly as displayed.',
            'Enable the provider, save the settings, then test the login flow from the public login page.',
        ],
        errors: [
            'Invalid OAuth2 redirect_uri: the redirect URL in Discord does not match the callback URL shown here.',
            'OAuth client not configured: Client ID or Client Secret is missing or pasted incorrectly.',
            'Access denied or application not available: the Discord app is private or restricted for the current account.',
        ],
    },
    google: {
        title: 'Google setup tutorial',
        steps: [
            'Open Google Cloud Console and create or select a project.',
            'Enable the Google Identity or OAuth consent flow, configure the consent screen, then create OAuth client credentials for a Web application.',
            'Copy the Client ID and Client Secret into RA-panel.',
            'Add the callback URL shown here to Authorized redirect URIs and publish the consent screen if needed.',
        ],
        errors: [
            'Error 400 redirect_uri_mismatch: the authorized redirect URI in Google Cloud does not exactly match the callback URL here.',
            'This app is blocked or unverified: the consent screen is still in testing or the user is not added as a test user.',
            'Invalid client: the wrong client type was created or the credentials were copied incorrectly.',
        ],
    },
    github: {
        title: 'GitHub setup tutorial',
        steps: [
            'Open GitHub Developer Settings and create a new OAuth App.',
            'Set the Homepage URL to your panel URL and the Authorization callback URL to the callback URL shown here.',
            'Copy the Client ID and generate a Client Secret, then store both values in RA-panel.',
            'Enable the provider and validate the login flow using a GitHub account with a visible email if possible.',
        ],
        errors: [
            'The redirect_uri is not associated with this application: the callback URL in GitHub does not match the one configured here.',
            'Email missing after login: the GitHub account has no public email and no retrievable verified email.',
            'Bad verification code: the callback was reused or the OAuth app configuration changed mid-flow.',
        ],
    },
    reddit: {
        title: 'Reddit setup tutorial',
        steps: [
            'Open Reddit app preferences and create a new web application.',
            'Set the redirect URI to the callback URL shown in RA-panel.',
            'Copy the app ID as Client ID and the secret value as Client Secret.',
            'Save the provider in RA-panel, enable it, then test login with a Reddit account.',
        ],
        errors: [
            'invalid_grant or redirect mismatch: the Reddit redirect URI does not match exactly.',
            'Invalid client authentication: the app ID or secret is wrong, incomplete, or pasted into the wrong field.',
            'Access denied by user: the user cancelled the Reddit consent screen during the flow.',
        ],
    },
};

const SectionLabel = ({ children }) => (
    <Typography
        variant="caption"
        sx={{
            color: 'text.secondary',
            fontWeight: 800,
            textTransform: 'uppercase',
            letterSpacing: '0.08em',
            display: 'block',
            mb: 1,
        }}
    >
        {children}
    </Typography>
);

const AdminAuthProviders = () => {
    const [form, setForm] = useState({
        standard_enabled: true,
        providers: {},
    });
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [tutorialProvider, setTutorialProvider] = useState(null);

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await client.get('/v1/admin/auth-providers');
                setForm({
                    standard_enabled: Boolean(data.standard_enabled),
                    providers: data.providers || {},
                });
            } catch (error) {
                setMessage({ type: 'error', text: 'Failed to load authentication provider settings.' });
            }
        };

        load();
    }, []);

    const stats = useMemo(() => {
        const providers = Object.values(form.providers || {});
        return {
            total: providers.length,
            enabled: providers.filter((provider) => provider.enabled).length,
            ready: providers.filter((provider) => provider.client_id_configured && provider.client_secret_configured).length,
        };
    }, [form.providers]);

    const updateProvider = (provider, field, value) => {
        setForm((current) => ({
            ...current,
            providers: {
                ...current.providers,
                [provider]: {
                    ...(current.providers[provider] || emptyProvider),
                    [field]: value,
                },
            },
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });
        try {
            const { data } = await client.put('/v1/admin/auth-providers', form);
            setForm({
                standard_enabled: Boolean(data.settings.standard_enabled),
                providers: data.settings.providers || {},
            });
            setMessage({ type: 'success', text: data.message });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save authentication providers.' });
        } finally {
            setSaving(false);
        }
    };

    const tutorial = tutorialProvider ? providerTutorials[tutorialProvider] : null;

    return (
        <Box>
            <Box
                sx={{
                    mb: 4,
                    p: { xs: 3, md: 4 },
                    borderRadius: 3,
                    border: '1px solid rgba(255,255,255,0.06)',
                    background: 'linear-gradient(135deg, rgba(24,30,52,0.96) 0%, rgba(14,19,34,0.96) 100%)',
                    overflow: 'hidden',
                    position: 'relative',
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        background: 'radial-gradient(circle at top right, rgba(71,133,255,0.22), transparent 36%)',
                        pointerEvents: 'none',
                    }}
                />
                <Grid container spacing={3} sx={{ position: 'relative', zIndex: 1 }}>
                    <Grid item xs={12} md={7}>
                        <Chip
                            icon={<ShieldIcon sx={{ fontSize: 16 }} />}
                            label="Identity Control"
                            size="small"
                            sx={{ mb: 2, bgcolor: 'rgba(71,133,255,0.14)', color: '#9fc0ff', fontWeight: 700 }}
                        />
                        <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1, mb: 1.5 }}>
                            Authentication providers, without exposing secrets
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 620, lineHeight: 1.8 }}>
                            Rotate provider credentials safely, control whether auto-linking is allowed, and use inline tutorials for setup and common OAuth failures. Secrets stay stored server-side and never come back to the browser.
                        </Typography>
                    </Grid>
                    <Grid item xs={12} md={5}>
                        <Grid container spacing={1.5}>
                            {[
                                { label: 'Providers', value: stats.total },
                                { label: 'Enabled', value: stats.enabled },
                                { label: 'Configured', value: stats.ready },
                            ].map((item) => (
                                <Grid item xs={4} key={item.label}>
                                    <Paper
                                        elevation={0}
                                        sx={{
                                            p: 2,
                                            height: '100%',
                                            bgcolor: 'rgba(255,255,255,0.03)',
                                            border: '1px solid rgba(255,255,255,0.05)',
                                            borderRadius: 2.5,
                                        }}
                                    >
                                        <Typography variant="caption" sx={{ color: '#8d98a8', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 700 }}>
                                            {item.label}
                                        </Typography>
                                        <Typography variant="h4" sx={{ mt: 1, fontWeight: 800 }}>
                                            {item.value}
                                        </Typography>
                                    </Paper>
                                </Grid>
                            ))}
                        </Grid>
                    </Grid>
                </Grid>
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <Paper
                sx={{
                    p: 3,
                    mb: 3,
                    bgcolor: 'background.paper',
                    border: '1px solid rgba(255,255,255,0.05)',
                    borderRadius: 3,
                }}
            >
                <SectionLabel>Core Login</SectionLabel>
                <Box sx={{ display: 'flex', flexDirection: { xs: 'column', md: 'row' }, alignItems: { xs: 'flex-start', md: 'center' }, justifyContent: 'space-between', gap: 2 }}>
                    <Box>
                        <Typography variant="h6" sx={{ fontWeight: 700, mb: 0.5 }}>
                            Standard email and password login
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 700 }}>
                            Keep this enabled if you want a direct fallback login path alongside Google, Discord, GitHub, or Reddit.
                        </Typography>
                    </Box>
                    <FormControlLabel
                        control={
                            <Switch
                                checked={form.standard_enabled}
                                onChange={(event) => setForm((current) => ({ ...current, standard_enabled: event.target.checked }))}
                            />
                        }
                        label="Enabled"
                    />
                </Box>
            </Paper>

            <Grid container spacing={3}>
                {Object.entries(form.providers).map(([provider, config]) => {
                    const visual = providerVisuals[provider] || providerVisuals.google;
                    const Icon = visual.icon;

                    return (
                        <Grid item xs={12} lg={6} key={provider}>
                            <Paper
                                sx={{
                                    p: 3,
                                    height: '100%',
                                    bgcolor: 'background.paper',
                                    border: '1px solid rgba(255,255,255,0.05)',
                                    borderRadius: 3,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 2.5,
                                }}
                            >
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', gap: 2, alignItems: 'flex-start' }}>
                                    <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                                        <Box
                                            sx={{
                                                width: 52,
                                                height: 52,
                                                borderRadius: 2.5,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                bgcolor: visual.surface,
                                                color: visual.accent,
                                            }}
                                        >
                                            <Icon />
                                        </Box>
                                        <Box>
                                            <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                                {config.name}
                                            </Typography>
                                            <Stack direction="row" spacing={1} sx={{ mt: 0.75, flexWrap: 'wrap' }}>
                                                <Chip size="small" label={config.enabled ? 'Enabled' : 'Disabled'} sx={{ bgcolor: config.enabled ? 'rgba(54,211,153,0.12)' : 'rgba(255,255,255,0.06)', color: config.enabled ? '#36d399' : '#9aa4b2', fontWeight: 700 }} />
                                                <Chip size="small" label={config.client_id_configured && config.client_secret_configured ? 'Configured' : 'Missing credentials'} sx={{ bgcolor: config.client_id_configured && config.client_secret_configured ? 'rgba(71,133,255,0.12)' : 'rgba(255,184,77,0.12)', color: config.client_id_configured && config.client_secret_configured ? '#8bb2ff' : '#ffbf5f', fontWeight: 700 }} />
                                            </Stack>
                                        </Box>
                                    </Box>
                                    <Button
                                        variant="outlined"
                                        size="small"
                                        startIcon={<InfoIcon />}
                                        onClick={() => setTutorialProvider(provider)}
                                        sx={{ borderColor: 'rgba(255,255,255,0.1)' }}
                                    >
                                        Tutorial
                                    </Button>
                                </Box>

                                <Box
                                    sx={{
                                        display: 'grid',
                                        gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                        gap: 1.5,
                                    }}
                                >
                                    <Paper elevation={0} sx={{ p: 2, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <SectionLabel>Access</SectionLabel>
                                        <FormControlLabel
                                            control={<Switch checked={Boolean(config.enabled)} onChange={(event) => updateProvider(provider, 'enabled', event.target.checked)} />}
                                            label="Provider enabled"
                                        />
                                    </Paper>
                                    <Paper elevation={0} sx={{ p: 2, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <SectionLabel>Registration</SectionLabel>
                                        <FormControlLabel
                                            control={<Switch checked={Boolean(config.register_enabled)} onChange={(event) => updateProvider(provider, 'register_enabled', event.target.checked)} />}
                                            label="Allow autolink / creation"
                                        />
                                    </Paper>
                                </Box>

                                <Box sx={{ display: 'grid', gap: 2 }}>
                                    <Box>
                                        <SectionLabel>Credential Rotation</SectionLabel>
                                        <TextField
                                            label="Replace Client ID"
                                            value={config.client_id || ''}
                                            onChange={(event) => updateProvider(provider, 'client_id', event.target.value)}
                                            fullWidth
                                            placeholder={config.client_id_configured ? 'Stored securely. Enter a new value to rotate.' : 'Paste a client ID'}
                                            helperText={config.client_id_configured ? 'Current client ID is stored securely and is not shown.' : 'No client ID stored yet.'}
                                            InputProps={{
                                                startAdornment: config.client_id_configured ? (
                                                    <InputAdornment position="start">Stored</InputAdornment>
                                                ) : undefined,
                                            }}
                                        />
                                    </Box>

                                    <Box>
                                        <TextField
                                            label="Replace Client Secret"
                                            value={config.client_secret || ''}
                                            onChange={(event) => updateProvider(provider, 'client_secret', event.target.value)}
                                            fullWidth
                                            type="password"
                                            placeholder={config.client_secret_configured ? 'Stored securely. Enter a new value to rotate.' : 'Paste a client secret'}
                                            helperText={config.client_secret_configured ? 'Current client secret is stored securely and is never returned to the browser.' : 'No client secret stored yet.'}
                                            InputProps={{
                                                startAdornment: config.client_secret_configured ? (
                                                    <InputAdornment position="start">Stored</InputAdornment>
                                                ) : undefined,
                                            }}
                                        />
                                    </Box>
                                </Box>

                                <Paper
                                    elevation={0}
                                    sx={{
                                        p: 2,
                                        borderRadius: 2.5,
                                        bgcolor: 'rgba(255,255,255,0.02)',
                                        border: '1px solid rgba(255,255,255,0.05)',
                                    }}
                                >
                                    <SectionLabel>Callback URL</SectionLabel>
                                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>
                                        Copy this exact URL into the provider dashboard redirect settings.
                                    </Typography>
                                    <TextField
                                        value={config.callback_url || ''}
                                        fullWidth
                                        InputProps={{
                                            readOnly: true,
                                            endAdornment: (
                                                <InputAdornment position="end">
                                                    <IconButton
                                                        size="small"
                                                        onClick={() => window.open(config.callback_url, '_blank', 'noopener,noreferrer')}
                                                    >
                                                        <OpenInNewIcon fontSize="small" />
                                                    </IconButton>
                                                </InputAdornment>
                                            ),
                                        }}
                                    />
                                </Paper>
                            </Paper>
                        </Grid>
                    );
                })}
            </Grid>

            <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end' }}>
                <Button
                    variant="contained"
                    size="large"
                    onClick={handleSave}
                    disabled={saving}
                    startIcon={<AutoAwesomeIcon />}
                >
                    Save Providers
                </Button>
            </Box>

            <Dialog
                open={Boolean(tutorial)}
                onClose={() => setTutorialProvider(null)}
                fullWidth
                maxWidth="md"
                PaperProps={{
                    sx: {
                        borderRadius: 3,
                        border: '1px solid rgba(255,255,255,0.08)',
                        backgroundImage: 'none',
                    },
                }}
            >
                <DialogTitle sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, pb: 1.5 }}>
                    <Box>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>
                            {tutorial?.title}
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                            Use these steps when configuring the provider and validating the OAuth flow.
                        </Typography>
                    </Box>
                    <IconButton onClick={() => setTutorialProvider(null)}>
                        <CloseIcon />
                    </IconButton>
                </DialogTitle>
                <DialogContent sx={{ pb: 3 }}>
                    <Grid container spacing={3}>
                        <Grid item xs={12} md={6}>
                            <Paper elevation={0} sx={{ p: 2.5, borderRadius: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', height: '100%' }}>
                                <SectionLabel>Setup Steps</SectionLabel>
                                <Stack spacing={1.5}>
                                    {(tutorial?.steps || []).map((step, index) => (
                                        <Box key={step} sx={{ display: 'flex', gap: 1.5, alignItems: 'flex-start' }}>
                                            <Chip label={index + 1} size="small" sx={{ minWidth: 28, bgcolor: 'rgba(71,133,255,0.14)', color: '#9fc0ff', fontWeight: 700 }} />
                                            <Typography variant="body2" sx={{ color: 'text.secondary', lineHeight: 1.7 }}>
                                                {step}
                                            </Typography>
                                        </Box>
                                    ))}
                                </Stack>
                            </Paper>
                        </Grid>
                        <Grid item xs={12} md={6}>
                            <Paper elevation={0} sx={{ p: 2.5, borderRadius: 2.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', height: '100%' }}>
                                <SectionLabel>Common Errors</SectionLabel>
                                <Stack spacing={1.5}>
                                    {(tutorial?.errors || []).map((error) => (
                                        <Box key={error} sx={{ display: 'flex', gap: 1.5, alignItems: 'flex-start' }}>
                                            <InfoIcon sx={{ mt: '2px', fontSize: 18, color: '#ffbf5f' }} />
                                            <Typography variant="body2" sx={{ color: 'text.secondary', lineHeight: 1.7 }}>
                                                {error}
                                            </Typography>
                                        </Box>
                                    ))}
                                </Stack>
                            </Paper>
                        </Grid>
                    </Grid>
                </DialogContent>
            </Dialog>
        </Box>
    );
};

export default AdminAuthProviders;
