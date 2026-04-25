import React, { useMemo, useState } from 'react';
import {
    Box, 
    TextField, 
    Button, 
    Typography, 
    Container, 
    Alert,
    CircularProgress,
    Fade,
    Link,
    Paper,
    Divider,
    Stack,
    IconButton,
} from '@mui/material';
import {
    Chat as DiscordIcon,
    GitHub as GitHubIcon,
    Reddit as RedditIcon,
    Google as GoogleIcon,
    Refresh as RefreshIcon,
} from '@mui/icons-material';
import { useAuth } from '../context/AuthContext';
import { useAppSettings } from '../context/AppSettingsContext';
import client from '../api/client';

const providerTheme = {
    discord: { icon: DiscordIcon, bg: '#5865F2', color: '#ffffff', border: 'transparent' },
    google: { icon: GoogleIcon, bg: '#ffffff', color: '#1f2023', border: '#e5e7eb' },
    github: { icon: GitHubIcon, bg: '#24292e', color: '#ffffff', border: 'transparent' },
    reddit: { icon: RedditIcon, bg: '#FF4500', color: '#ffffff', border: 'transparent' },
};

const AuthPage = () => {
    const [loginValue, setLoginValue] = useState('');
    const [password, setPassword] = useState('');
    const [twoFactorCode, setTwoFactorCode] = useState('');
    const [twoFactorChallenge, setTwoFactorChallenge] = useState(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [loading, setLoading] = useState(false);
    const [providers, setProviders] = useState([]);
    const [standardEnabled, setStandardEnabled] = useState(true);
    const [captchaEnabled, setCaptchaEnabled] = useState(false);
    const [captchaSvg, setCaptchaSvg] = useState('');
    const [captchaToken, setCaptchaToken] = useState('');
    const [captchaValue, setCaptchaValue] = useState('');
    const [captchaLoading, setCaptchaLoading] = useState(false);
    const { login, completeTwoFactorLogin, suspensionMessage, setSuspensionMessage } = useAuth();
    const { settings } = useAppSettings();
    const hasProviders = providers.length > 0;

    const providerButtons = useMemo(() => providers.map((provider) => ({
        ...provider,
        visual: providerTheme[provider.icon_key] || { icon: GoogleIcon, bg: 'transparent', color: 'inherit', border: 'rgba(255,255,255,0.1)' },
    })), [providers]);

    const refreshCaptcha = async () => {
        setCaptchaLoading(true);
        try {
            const { data } = await client.get('/v1/auth/captcha');
            setCaptchaEnabled(Boolean(data.enabled));
            setCaptchaSvg(data.svg || '');
            setCaptchaToken(data.token || '');
            setCaptchaValue('');
        } catch {
            setCaptchaSvg('');
            setCaptchaToken('');
        } finally {
            setCaptchaLoading(false);
        }
    };

    React.useEffect(() => {
        const loadProviders = async () => {
            try {
                const { data } = await client.get('/v1/auth/providers');
                setProviders((data.providers || []).filter((provider) => provider.enabled && provider.configured));
                setStandardEnabled(Boolean(data.standard_enabled));
                setCaptchaEnabled(Boolean(data.captcha_enabled));
                if (data.captcha_enabled) {
                    await refreshCaptcha();
                }
            } catch (loadError) {
                setProviders([]);
            }
        };

        const hashParams = new URLSearchParams(window.location.hash.replace(/^#/, ''));
        const oauthError = hashParams.get('oauth_error');
        const oauthStatus = hashParams.get('oauth_status');
        const provider = hashParams.get('provider');
        const storedSuspensionMessage = sessionStorage.getItem('ra_suspension_message');

        if (storedSuspensionMessage) {
            setError(storedSuspensionMessage);
            sessionStorage.removeItem('ra_suspension_message');
        } else if (suspensionMessage) {
            setError(suspensionMessage);
            setSuspensionMessage('');
        }

        if (oauthError) {
            setError(oauthError);
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        } else if (oauthStatus) {
            const providerLabel = provider ? provider.charAt(0).toUpperCase() + provider.slice(1) : 'OAuth';
            const messages = {
                logged_in: `${providerLabel} login completed.`,
                registered: `Your account was created with ${providerLabel}.`,
                linked_existing: `${providerLabel} was connected to your existing account.`,
            };
            if (oauthStatus === '2fa_required' && hashParams.get('two_factor_token')) {
                setTwoFactorChallenge({
                    token: hashParams.get('two_factor_token'),
                    provider: providerLabel,
                    message: `Enter the 6-digit code from your authenticator app to finish ${providerLabel} login.`,
                });
                setNotice(`Continue ${providerLabel} sign-in with your authenticator code.`);
            } else {
                setNotice(messages[oauthStatus] || 'Authentication completed.');
            }
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        }

        loadProviders();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setNotice('');
        setLoading(true);

        const result = await login(loginValue, password, {
            token: captchaToken,
            value: captchaValue,
        });

        if (result.twoFactorRequired) {
            setTwoFactorChallenge({
                token: result.twoFactorToken,
                provider: 'Password',
                message: result.message || 'Enter your 6-digit authentication code to continue.',
            });
            setNotice('Password verified. Enter your authenticator code to finish signing in.');
            setPassword('');
            if (captchaEnabled) {
                await refreshCaptcha();
            }
        } else if (!result.success) {
            setError(result.message);
            if (captchaEnabled) {
                await refreshCaptcha();
            }
        }
        
        setLoading(false);
    };

    const handleTwoFactorSubmit = async (event) => {
        event.preventDefault();
        setError('');
        setNotice('');
        setLoading(true);

        const result = await completeTwoFactorLogin(twoFactorChallenge?.token, twoFactorCode, {
            token: captchaToken,
            value: captchaValue,
        });

        if (!result.success) {
            setError(result.message);
            if (captchaEnabled) {
                await refreshCaptcha();
            }
        }

        setLoading(false);
    };

    const resetTwoFactorChallenge = () => {
        setTwoFactorChallenge(null);
        setTwoFactorCode('');
        setNotice('');
        setError('');
    };

    return (
        <Box 
            sx={{ 
                minHeight: '100vh', 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'center',
                backgroundColor: 'background.default',
                position: 'relative'
            }}
        >
            <Fade in={true} timeout={800}>
                <Container maxWidth="md" sx={{ position: 'relative', zIndex: 1, py: 4 }}>
                    
                    <Box sx={{ textAlign: 'center', mb: 3 }}>
                        <Typography variant="h4" sx={{ fontWeight: 700, color: '#e2e8f0', letterSpacing: '-0.01em' }}>
                            {settings.brandName} Login
                        </Typography>
                    </Box>

                    {error && (
                        <Alert 
                            severity="error" 
                            variant="filled"
                            sx={{ 
                                mb: 3, 
                                borderRadius: 1, 
                                bgcolor: '#ef4444', 
                                color: '#fff',
                                fontWeight: 600,
                                '& .MuiAlert-icon': { color: '#fff' }
                            }}
                        >
                            {error}
                        </Alert>
                    )}

                    {notice && (
                        <Alert severity="success" sx={{ mb: 3, borderRadius: 1 }}>
                            {notice}
                        </Alert>
                    )}

                    <Paper 
                        elevation={0}
                        sx={{ 
                            display: 'flex', 
                            flexDirection: { xs: 'column', md: 'row' },
                            bgcolor: 'background.paper', 
                            borderRadius: 2, 
                            border: '1px solid rgba(255, 255, 255, 0.05)',
                            overflow: 'hidden',
                            boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
                            minHeight: { md: 540 }
                        }}
                    >
                        <Box 
                            sx={{ 
                                flex: 1, 
                                display: 'flex', 
                                alignItems: 'center', 
                                justifyContent: 'center',
                                p: { xs: 3, md: 4 },
                                bgcolor: '#1f2023',
                                borderRight: { md: '1px solid rgba(255,255,255,0.05)' }
                            }}
                        >
                            <Box sx={{ width: '100%', maxWidth: 340, textAlign: 'center' }}>
                                <Box 
                                    component="img"
                                    src="/mascot.png"
                                    alt="Mascot"
                                    sx={{ 
                                        maxWidth: '100%', 
                                        height: 'auto', 
                                        maxHeight: { xs: 220, md: 320 },
                                        objectFit: 'contain',
                                        minHeight: 180,
                                        width: '100%',
                                        mb: 3,
                                    }}
                                    onError={(e) => {
                                        e.target.style.display = 'none';
                                        e.target.nextSibling.style.display = 'flex';
                                    }}
                                />
                                <Box 
                                    sx={{ 
                                        display: 'none', 
                                        width: '100%', 
                                        height: 250, 
                                        alignItems: 'center', 
                                        justifyContent: 'center',
                                        border: '2px dashed rgba(255,255,255,0.1)',
                                        borderRadius: 2,
                                        mb: 3,
                                    }}
                                >
                                    <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.3)', fontWeight: 600 }}>
                                        /mascot.png Placeholder
                                    </Typography>
                                </Box>
                                <Typography variant="h5" sx={{ fontWeight: 700, color: '#ffffff', mb: 1 }}>
                                    RA-panel access for your servers
                                </Typography>
                                <Typography variant="body2" sx={{ color: '#9ca3af', lineHeight: 1.7 }}>
                                    Sign in with your password or continue with a linked provider. Social sign-in follows the same linking logic used in CPanel.
                                </Typography>
                            </Box>
                        </Box>

                        <Box sx={{ flex: 1.2, p: { xs: 4, md: 6 }, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                            {twoFactorChallenge ? (
                                <form onSubmit={handleTwoFactorSubmit}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                        <Box>
                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                Verification
                                            </Typography>
                                            <Alert severity="info" sx={{ borderRadius: 1.5 }}>
                                                {twoFactorChallenge.message}
                                            </Alert>
                                        </Box>

                                        <Box>
                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                6-Digit Code
                                            </Typography>
                                            <TextField
                                                type="text"
                                                fullWidth
                                                variant="outlined"
                                                value={twoFactorCode}
                                                onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D+/g, '').slice(0, 6))}
                                                required
                                                size="medium"
                                                placeholder="123456"
                                                inputProps={{ inputMode: 'numeric', pattern: '[0-9]*', maxLength: 6 }}
                                            />
                                        </Box>

                                        {captchaEnabled && (
                                            <Box>
                                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                    Captcha Verification
                                                </Typography>
                                                <Stack direction="row" spacing={1.25} alignItems="stretch" sx={{ mb: 1.25 }}>
                                                    <Box
                                                        sx={{
                                                            flex: 1,
                                                            minHeight: 58,
                                                            px: 1.5,
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            borderRadius: 1.5,
                                                            border: '1px solid rgba(255,255,255,0.08)',
                                                            bgcolor: '#121722',
                                                        }}
                                                    >
                                                        {captchaSvg ? <Box sx={{ lineHeight: 0 }} dangerouslySetInnerHTML={{ __html: captchaSvg }} /> : <Typography variant="caption">Loading challenge…</Typography>}
                                                    </Box>
                                                    <IconButton onClick={refreshCaptcha} disabled={captchaLoading || loading} sx={{ borderRadius: 1.5, border: '1px solid rgba(255,255,255,0.08)' }}>
                                                        <RefreshIcon />
                                                    </IconButton>
                                                </Stack>
                                                <TextField
                                                    type="text"
                                                    fullWidth
                                                    variant="outlined"
                                                    value={captchaValue}
                                                    onChange={(e) => setCaptchaValue(e.target.value)}
                                                    required
                                                    size="medium"
                                                    placeholder="Enter captcha code"
                                                />
                                            </Box>
                                        )}

                                        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                            <Button 
                                                type="submit" 
                                                variant="contained" 
                                                fullWidth 
                                                size="large"
                                                disabled={loading}
                                                sx={{ 
                                                    py: 1.5, 
                                                    fontSize: '0.95rem',
                                                    mt: 1,
                                                    bgcolor: 'primary.main',
                                                    color: '#fff',
                                                    fontWeight: 700,
                                                    textTransform: 'uppercase',
                                                    letterSpacing: '0.05em',
                                                    '&:hover': { bgcolor: 'primary.dark' }
                                                }}
                                            >
                                                {loading ? <CircularProgress size={24} color="inherit" /> : 'Verify and Login'}
                                            </Button>
                                            <Button variant="outlined" fullWidth size="large" onClick={resetTwoFactorChallenge} disabled={loading} sx={{ mt: 1 }}>
                                                Back
                                            </Button>
                                        </Stack>
                                    </Box>
                                </form>
                            ) : standardEnabled && (
                                <form onSubmit={handleSubmit}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                        <Box>
                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                Username or Email
                                            </Typography>
                                            <TextField
                                                type="text"
                                                fullWidth
                                                variant="outlined"
                                                value={loginValue}
                                                onChange={(e) => setLoginValue(e.target.value)}
                                                required
                                                size="medium"
                                                placeholder="user@example.com or username"
                                            />
                                        </Box>

                                        <Box>
                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                Password
                                            </Typography>
                                            <TextField
                                                type="password"
                                                fullWidth
                                                variant="outlined"
                                                value={password}
                                                onChange={(e) => setPassword(e.target.value)}
                                                required
                                                size="medium"
                                            />
                                        </Box>

                                        {captchaEnabled && (
                                            <Box>
                                                <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 1 }}>
                                                    Captcha Verification
                                                </Typography>
                                                <Stack direction="row" spacing={1.25} alignItems="stretch" sx={{ mb: 1.25 }}>
                                                    <Box
                                                        sx={{
                                                            flex: 1,
                                                            minHeight: 58,
                                                            px: 1.5,
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            borderRadius: 1.5,
                                                            border: '1px solid rgba(255,255,255,0.08)',
                                                            bgcolor: '#121722',
                                                        }}
                                                    >
                                                        {captchaSvg ? <Box sx={{ lineHeight: 0 }} dangerouslySetInnerHTML={{ __html: captchaSvg }} /> : <Typography variant="caption">Loading challenge…</Typography>}
                                                    </Box>
                                                    <IconButton onClick={refreshCaptcha} disabled={captchaLoading || loading} sx={{ borderRadius: 1.5, border: '1px solid rgba(255,255,255,0.08)' }}>
                                                        <RefreshIcon />
                                                    </IconButton>
                                                </Stack>
                                                <TextField
                                                    type="text"
                                                    fullWidth
                                                    variant="outlined"
                                                    value={captchaValue}
                                                    onChange={(e) => setCaptchaValue(e.target.value)}
                                                    required
                                                    size="medium"
                                                    placeholder="Enter captcha code"
                                                />
                                            </Box>
                                        )}

                                        <Button 
                                            type="submit" 
                                            variant="contained" 
                                            fullWidth 
                                            size="large"
                                            disabled={loading}
                                            sx={{ 
                                                py: 1.5, 
                                                fontSize: '0.95rem',
                                                mt: 1,
                                                bgcolor: 'primary.main',
                                                color: '#fff',
                                                fontWeight: 700,
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.05em',
                                                '&:hover': { bgcolor: 'primary.dark' }
                                            }}
                                        >
                                            {loading ? <CircularProgress size={24} color="inherit" /> : 'Login'}
                                        </Button>

                                        <Box sx={{ textAlign: 'center', mt: 2 }}>
                                            <Link href="#" underline="none" sx={{ color: 'text.secondary', fontSize: '0.8rem', fontWeight: 600, '&:hover': { color: 'text.primary' } }}>
                                                Forgot Password?
                                            </Link>
                                        </Box>
                                    </Box>
                                </form>
                            )}

                            {hasProviders && !twoFactorChallenge && (
                                <Box sx={{ mt: standardEnabled ? 4 : 0 }}>
                                    {standardEnabled ? (
                                        <Divider sx={{ mb: 2.5, color: 'text.secondary', '&::before, &::after': { borderColor: 'rgba(255,255,255,0.08)' } }}>
                                            <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                                                Or continue with
                                            </Typography>
                                        </Divider>
                                    ) : (
                                        <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', mb: 2 }}>
                                            Continue with
                                        </Typography>
                                    )}
                                    <Box sx={{ display: 'grid', gap: 1.5 }}>
                                        {providerButtons.map((provider) => {
                                            const Icon = provider.visual.icon;

                                            return (
                                            <Button
                                                key={provider.key}
                                                fullWidth
                                                size="large"
                                                onClick={() => {
                                                    window.location.href = provider.login_url;
                                                }}
                                                sx={{
                                                    py: 1.2,
                                                    justifyContent: 'flex-start',
                                                    gap: 1.5,
                                                    color: provider.visual.color,
                                                    backgroundColor: provider.visual.bg,
                                                    border: `1px solid ${provider.visual.border}`,
                                                    fontWeight: 700,
                                                    textTransform: 'none',
                                                    '&:hover': {
                                                        backgroundColor: provider.visual.bg,
                                                        opacity: 0.92,
                                                    },
                                                }}
                                            >
                                                <Icon fontSize="small" />
                                                Continue with {provider.name}
                                            </Button>
                                        )})}
                                    </Box>
                                </Box>
                            )}

                            {!standardEnabled && providers.length === 0 && (
                                <Alert severity="warning" sx={{ mt: 2 }}>
                                    No authentication methods are currently enabled.
                                </Alert>
                            )}
                        </Box>
                    </Paper>

                    <Typography variant="body2" sx={{ textAlign: 'center', mt: 4, color: 'text.secondary', fontSize: '0.75rem', opacity: 0.6 }}>
                        © 2015 - 2026 {settings.brandName} Software
                    </Typography>
                </Container>
            </Fade>
        </Box>
    );
};

export default AuthPage;
