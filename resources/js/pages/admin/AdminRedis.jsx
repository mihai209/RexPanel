import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    Divider,
    FormControlLabel,
    Grid,
    MenuItem,
    Paper,
    Stack,
    Switch,
    TextField,
    Typography,
} from '@mui/material';
import {
    Hub as HubIcon,
    Save as SaveIcon,
    Science as ScienceIcon,
    Storage as StorageIcon,
    WarningAmber as WarningIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const modeOptions = [
    { value: 'host', label: 'Host / Port / DB' },
    { value: 'url', label: 'Redis URL' },
];

const toneForWarning = (level) => {
    if (level === 'error') {
        return 'error';
    }

    if (level === 'warning') {
        return 'warning';
    }

    return 'info';
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
};

const AdminRedis = () => {
    const [payload, setPayload] = useState(null);
    const [configForm, setConfigForm] = useState({
        redisEnabled: false,
        redisRequired: false,
        redisUrl: '',
        redisHost: '127.0.0.1',
        redisPort: 6379,
        redisDb: 0,
        redisUsername: '',
        redisPassword: '',
        redisTls: false,
        redisSessionPrefix: '',
    });
    const [testForm, setTestForm] = useState({
        redisEnabled: true,
        redisUrl: '',
        redisHost: '127.0.0.1',
        redisPort: 6379,
        redisDb: 0,
        redisUsername: '',
        redisPassword: '',
        redisTls: false,
        redisSessionPrefix: '',
    });
    const [mode, setMode] = useState('host');
    const [testMode, setTestMode] = useState('host');
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [testResult, setTestResult] = useState(null);

    const hydrate = (data) => {
        setPayload(data);

        const nextConfig = {
            redisEnabled: Boolean(data?.config?.redisEnabled),
            redisRequired: Boolean(data?.config?.redisRequired),
            redisUrl: data?.config?.redisUrl || '',
            redisHost: data?.config?.redisHost || '127.0.0.1',
            redisPort: Number(data?.config?.redisPort ?? 6379),
            redisDb: Number(data?.config?.redisDb ?? 0),
            redisUsername: data?.config?.redisUsername || '',
            redisPassword: '',
            redisTls: Boolean(data?.config?.redisTls),
            redisSessionPrefix: data?.config?.redisSessionPrefix || '',
        };

        setConfigForm(nextConfig);
        setTestForm((current) => ({
            ...current,
            ...nextConfig,
            redisEnabled: true,
        }));
        setMode(data?.config?.mode || 'host');
        setTestMode(data?.config?.mode || 'host');
        setTestResult(data?.config?.lastTest || null);
    };

    const load = async () => {
        try {
            const { data } = await client.get('/v1/admin/redis');
            hydrate(data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load Redis admin data.' });
        }
    };

    useEffect(() => {
        load();
    }, []);

    const runtimeChips = useMemo(() => ([
        { label: payload?.runtime?.enabled ? 'Enabled' : 'Disabled', color: payload?.runtime?.enabled ? '#7ce7bf' : '#98a4b3' },
        { label: payload?.runtime?.ready ? 'Ready' : 'Not Ready', color: payload?.runtime?.ready ? '#7ce7bf' : '#ff8f8f' },
        { label: payload?.runtime?.source ? `Source: ${payload.runtime.source}` : 'Source: none', color: '#8bb2ff' },
        { label: payload?.runtime?.effectiveMode ? `Mode: ${payload.runtime.effectiveMode}` : 'Mode: unknown', color: '#ffcf6e' },
    ]), [payload]);

    const updateConfigField = (key, value) => {
        setConfigForm((current) => ({ ...current, [key]: value }));
    };

    const updateTestField = (key, value) => {
        setTestForm((current) => ({ ...current, [key]: value }));
    };

    const save = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const requestPayload = {
                ...configForm,
                redisUrl: mode === 'url' ? configForm.redisUrl : '',
            };
            const { data } = await client.put('/v1/admin/redis', requestPayload);
            hydrate(data.data);
            setMessage({ type: 'success', text: data.message || 'Redis profile updated.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save Redis profile.' });
        } finally {
            setSaving(false);
        }
    };

    const testConnection = async () => {
        setTesting(true);
        setMessage({ type: '', text: '' });

        try {
            const requestPayload = {
                ...testForm,
                redisUrl: testMode === 'url' ? testForm.redisUrl : '',
            };
            const { data } = await client.post('/v1/admin/redis/test', requestPayload);
            setTestResult(data);
            setMessage({ type: data.ok ? 'success' : 'error', text: data.ok ? 'Redis test succeeded.' : (data.error || 'Redis test failed.') });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to execute Redis test.' });
        } finally {
            setTesting(false);
        }
    };

    return (
        <Box>
            <Box
                sx={{
                    mb: 4,
                    p: { xs: 3, md: 4 },
                    borderRadius: 3,
                    border: '1px solid rgba(255,255,255,0.06)',
                    background: 'linear-gradient(135deg, rgba(22,34,46,0.98) 0%, rgba(14,20,31,0.98) 100%)',
                    position: 'relative',
                    overflow: 'hidden',
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        background: 'radial-gradient(circle at top right, rgba(76, 175, 80, 0.14), transparent 34%)',
                        pointerEvents: 'none',
                    }}
                />
                <Grid container spacing={3} sx={{ position: 'relative', zIndex: 1 }}>
                    <Grid item xs={12} md={7}>
                        <Chip
                            icon={<StorageIcon sx={{ fontSize: 16 }} />}
                            label="Redis Control"
                            size="small"
                            sx={{ mb: 2, bgcolor: 'rgba(124,231,191,0.12)', color: '#7ce7bf', fontWeight: 700 }}
                        />
                        <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1, mb: 1.5 }}>
                            Panel-managed Redis without lying about Laravel runtime
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 720, lineHeight: 1.8 }}>
                            This page stores a dedicated Redis profile for RA-panel fast paths like quota counters, connector queue dispatch, and UI websocket fan-out.
                            Laravel cache, session, queue, broadcast, and Reverb bootstrap still come from env and framework config.
                        </Typography>
                    </Grid>
                    <Grid item xs={12} md={5}>
                        <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" justifyContent={{ xs: 'flex-start', md: 'flex-end' }}>
                            {runtimeChips.map((item) => (
                                <Chip
                                    key={item.label}
                                    label={item.label}
                                    sx={{ bgcolor: 'rgba(255,255,255,0.04)', color: item.color, fontWeight: 700 }}
                                />
                            ))}
                        </Stack>
                        <Paper
                            elevation={0}
                            sx={{
                                mt: 2,
                                p: 2.5,
                                borderRadius: 2.5,
                                bgcolor: 'rgba(255,255,255,0.03)',
                                border: '1px solid rgba(255,255,255,0.05)',
                            }}
                        >
                            <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
                                Effective endpoint summary
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary', lineHeight: 1.8 }}>
                                {payload?.runtime?.endpointSummary || 'No Redis source is active.'}
                            </Typography>
                        </Paper>
                    </Grid>
                </Grid>
            </Box>

            {message.text ? (
                <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>
                    {message.text}
                </Alert>
            ) : null}

            <Grid container spacing={3}>
                <Grid item xs={12} lg={7}>
                    <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                        <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={2} sx={{ mb: 2 }}>
                            <Box>
                                <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                    Stored Config
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.75 }}>
                                    Passwords stay server-only. Saving a blank password preserves the current stored secret.
                                </Typography>
                            </Box>
                            <Button variant="contained" startIcon={<SaveIcon />} onClick={save} disabled={saving}>
                                {saving ? 'Saving...' : 'Save'}
                            </Button>
                        </Stack>

                        <Grid container spacing={2}>
                            <Grid item xs={12} sm={6}>
                                <FormControlLabel
                                    control={<Switch checked={configForm.redisEnabled} onChange={(event) => updateConfigField('redisEnabled', event.target.checked)} />}
                                    label="Enable panel-managed Redis"
                                />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <FormControlLabel
                                    control={<Switch checked={configForm.redisRequired} onChange={(event) => updateConfigField('redisRequired', event.target.checked)} />}
                                    label="Mark Redis as required"
                                />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <TextField
                                    select
                                    fullWidth
                                    label="Config Mode"
                                    value={mode}
                                    onChange={(event) => setMode(event.target.value)}
                                >
                                    {modeOptions.map((option) => (
                                        <MenuItem key={option.value} value={option.value}>
                                            {option.label}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <TextField
                                    fullWidth
                                    label="Session Prefix"
                                    value={configForm.redisSessionPrefix}
                                    onChange={(event) => updateConfigField('redisSessionPrefix', event.target.value)}
                                    helperText="Stored for explicit panel-managed consumers. Current fast paths keep their existing key names."
                                />
                            </Grid>
                            {mode === 'url' ? (
                                <Grid item xs={12}>
                                    <TextField
                                        fullWidth
                                        label="Redis URL"
                                        value={configForm.redisUrl}
                                        onChange={(event) => updateConfigField('redisUrl', event.target.value)}
                                        helperText="Supports redis:// or rediss://. Saved reads stay masked in the admin API."
                                    />
                                </Grid>
                            ) : (
                                <>
                                    <Grid item xs={12} sm={6}>
                                        <TextField fullWidth label="Host" value={configForm.redisHost} onChange={(event) => updateConfigField('redisHost', event.target.value)} />
                                    </Grid>
                                    <Grid item xs={12} sm={3}>
                                        <TextField fullWidth type="number" label="Port" value={configForm.redisPort} onChange={(event) => updateConfigField('redisPort', Number(event.target.value))} />
                                    </Grid>
                                    <Grid item xs={12} sm={3}>
                                        <TextField fullWidth type="number" label="DB" value={configForm.redisDb} onChange={(event) => updateConfigField('redisDb', Number(event.target.value))} />
                                    </Grid>
                                </>
                            )}
                            <Grid item xs={12} sm={6}>
                                <TextField
                                    fullWidth
                                    label="Username"
                                    value={configForm.redisUsername}
                                    onChange={(event) => updateConfigField('redisUsername', event.target.value)}
                                />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <TextField
                                    fullWidth
                                    type="password"
                                    label={payload?.config?.hasPassword ? 'Password (replace only)' : 'Password'}
                                    value={configForm.redisPassword}
                                    onChange={(event) => updateConfigField('redisPassword', event.target.value)}
                                    helperText={payload?.config?.hasPassword ? 'A password is already stored. Leave blank to keep it.' : 'No password is stored yet.'}
                                />
                            </Grid>
                            <Grid item xs={12}>
                                <FormControlLabel
                                    control={<Switch checked={configForm.redisTls} onChange={(event) => updateConfigField('redisTls', event.target.checked)} />}
                                    label="Force TLS for host/port mode"
                                />
                            </Grid>
                        </Grid>

                        <Divider sx={{ my: 3, borderColor: 'rgba(255,255,255,0.06)' }} />

                        <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
                            Current stored state
                        </Typography>
                        <Stack spacing={0.75}>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Endpoint: <strong>{payload?.config?.endpointSummary || 'Not configured'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Password saved: <strong>{payload?.config?.hasPassword ? 'Yes' : 'No'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Last test: <strong>{formatDateTime(payload?.config?.lastTest?.testedAt)}</strong>
                            </Typography>
                        </Stack>
                    </Paper>
                </Grid>

                <Grid item xs={12} lg={5}>
                    <Stack spacing={3}>
                        <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                            <Stack direction="row" alignItems="center" spacing={1.5} sx={{ mb: 2 }}>
                                <HubIcon color="primary" />
                                <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                    Runtime Status
                                </Typography>
                            </Stack>
                            <Stack spacing={1}>
                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                    Ready: <strong>{payload?.runtime?.ready ? 'Yes' : 'No'}</strong>
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                    Source: <strong>{payload?.runtime?.source || 'none'}</strong>
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                    Mode: <strong>{payload?.runtime?.effectiveMode || 'disabled'}</strong>
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                    Framework endpoint: <strong>{payload?.framework?.endpointSummary || 'N/A'}</strong>
                                </Typography>
                            </Stack>
                            {payload?.runtime?.lastError ? (
                                <Alert severity="warning" sx={{ mt: 2 }}>
                                    {payload.runtime.lastError}
                                </Alert>
                            ) : null}
                        </Paper>

                        <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                            <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={2} sx={{ mb: 2 }}>
                                <Box>
                                    <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                        Test Connection
                                    </Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.75 }}>
                                        Runs a short-lived client against the submitted profile without mutating Laravel config.
                                    </Typography>
                                </Box>
                                <Button variant="outlined" startIcon={<ScienceIcon />} onClick={testConnection} disabled={testing}>
                                    {testing ? 'Testing...' : 'Test'}
                                </Button>
                            </Stack>
                            <Grid container spacing={2}>
                                <Grid item xs={12}>
                                    <TextField select fullWidth label="Test Mode" value={testMode} onChange={(event) => setTestMode(event.target.value)}>
                                        {modeOptions.map((option) => (
                                            <MenuItem key={option.value} value={option.value}>
                                                {option.label}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                                {testMode === 'url' ? (
                                    <Grid item xs={12}>
                                        <TextField fullWidth label="Redis URL" value={testForm.redisUrl} onChange={(event) => updateTestField('redisUrl', event.target.value)} />
                                    </Grid>
                                ) : (
                                    <>
                                        <Grid item xs={12} sm={6}>
                                            <TextField fullWidth label="Host" value={testForm.redisHost} onChange={(event) => updateTestField('redisHost', event.target.value)} />
                                        </Grid>
                                        <Grid item xs={12} sm={3}>
                                            <TextField fullWidth type="number" label="Port" value={testForm.redisPort} onChange={(event) => updateTestField('redisPort', Number(event.target.value))} />
                                        </Grid>
                                        <Grid item xs={12} sm={3}>
                                            <TextField fullWidth type="number" label="DB" value={testForm.redisDb} onChange={(event) => updateTestField('redisDb', Number(event.target.value))} />
                                        </Grid>
                                    </>
                                )}
                                <Grid item xs={12} sm={6}>
                                    <TextField fullWidth label="Username" value={testForm.redisUsername} onChange={(event) => updateTestField('redisUsername', event.target.value)} />
                                </Grid>
                                <Grid item xs={12} sm={6}>
                                    <TextField
                                        fullWidth
                                        type="password"
                                        label="Password"
                                        value={testForm.redisPassword}
                                        onChange={(event) => updateTestField('redisPassword', event.target.value)}
                                        helperText="Leave blank to reuse the stored password when one exists."
                                    />
                                </Grid>
                                <Grid item xs={12}>
                                    <FormControlLabel control={<Switch checked={testForm.redisTls} onChange={(event) => updateTestField('redisTls', event.target.checked)} />} label="TLS" />
                                </Grid>
                            </Grid>
                            {testResult ? (
                                <Alert severity={testResult.ok ? 'success' : 'error'} sx={{ mt: 2 }}>
                                    {testResult.ok ? `Connection succeeded at ${formatDateTime(testResult.testedAt)}.` : (testResult.error || 'Connection failed.')}
                                </Alert>
                            ) : null}
                        </Paper>
                    </Stack>
                </Grid>

                <Grid item xs={12}>
                    <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>
                            Redis Usage in RA-panel
                        </Typography>
                        <Grid container spacing={2}>
                            {(payload?.usage || []).map((item) => (
                                <Grid item xs={12} md={6} xl={3} key={item.key}>
                                    <Paper elevation={0} sx={{ p: 2.25, height: '100%', borderRadius: 2.5, bgcolor: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <Stack direction="row" justifyContent="space-between" spacing={1} sx={{ mb: 1.5 }}>
                                            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                                                {item.label}
                                            </Typography>
                                            <Chip
                                                size="small"
                                                label={item.active ? (item.ready ? 'Ready' : 'Not Ready') : 'Inactive'}
                                                sx={{
                                                    bgcolor: item.active ? (item.ready ? 'rgba(54,211,153,0.12)' : 'rgba(255,171,64,0.12)') : 'rgba(255,255,255,0.05)',
                                                    color: item.active ? (item.ready ? '#7ce7bf' : '#ffcf6e') : '#98a4b3',
                                                    fontWeight: 700,
                                                }}
                                            />
                                        </Stack>
                                        <Typography variant="body2" sx={{ color: 'text.secondary', lineHeight: 1.8 }}>
                                            Source: <strong>{item.source}</strong><br />
                                            Required: <strong>{item.required ? 'Yes' : 'No'}</strong><br />
                                            Degraded: <strong>{item.degraded ? 'Yes' : 'No'}</strong>
                                        </Typography>
                                        {item.lastError ? (
                                            <Alert severity="warning" sx={{ mt: 1.5 }}>
                                                {item.lastError}
                                            </Alert>
                                        ) : null}
                                    </Paper>
                                </Grid>
                            ))}
                        </Grid>
                    </Paper>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>
                            Framework Drivers
                        </Typography>
                        <Stack spacing={1}>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Session: <strong>{payload?.framework?.session?.driver || 'n/a'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Cache: <strong>{payload?.framework?.cache?.driver || 'n/a'}</strong> via <strong>{payload?.framework?.cache?.store || 'n/a'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Queue: <strong>{payload?.framework?.queue?.driver || 'n/a'}</strong> via <strong>{payload?.framework?.queue?.connection || 'n/a'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Broadcast: <strong>{payload?.framework?.broadcast?.driver || 'n/a'}</strong>
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Reverb scaling: <strong>{payload?.framework?.broadcast?.reverbScalingEnabled ? 'Enabled' : 'Disabled'}</strong>
                            </Typography>
                        </Stack>
                    </Paper>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)' }}>
                        <Stack direction="row" alignItems="center" spacing={1.5} sx={{ mb: 2 }}>
                            <WarningIcon sx={{ color: '#ffcf6e' }} />
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                Safety Notes
                            </Typography>
                        </Stack>
                        <Stack spacing={1.5}>
                            {(payload?.warnings || []).map((warning, index) => (
                                <Alert key={`${warning.text}-${index}`} severity={toneForWarning(warning.level)}>
                                    {warning.text}
                                </Alert>
                            ))}
                        </Stack>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default AdminRedis;
