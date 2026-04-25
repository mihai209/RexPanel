import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    CircularProgress,
    Grid,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import {
    Memory as CpuIcon,
    Storage as DiskIcon,
    PlayArrow as StartIcon,
    RestartAlt as RestartIcon,
    Stop as StopIcon,
    Dangerous as KillIcon,
    Terminal as ConsoleIcon,
} from '@mui/icons-material';
import { useParams } from 'react-router-dom';
import client from '../../api/client';

const formatBytes = (bytes = 0) => {
    if (bytes >= 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
    }

    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${bytes} B`;
};

const statusColor = (status) => {
    const normalized = String(status || 'offline').toLowerCase();

    if (['running', 'online', 'healthy'].includes(normalized)) {
        return 'success';
    }

    if (['starting', 'restarting', 'installing', 'reinstalling'].includes(normalized)) {
        return 'warning';
    }

    if (['error', 'failed', 'offline', 'stopping'].includes(normalized)) {
        return 'error';
    }

    return 'default';
};

const StatCard = ({ label, value, icon }) => (
    <Card sx={{ height: '100%', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 2, boxShadow: 'none' }}>
        <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
            {icon}
            <Box>
                <Typography variant="caption" sx={{ color: 'text.secondary', textTransform: 'uppercase' }}>{label}</Typography>
                <Typography variant="h6" sx={{ fontWeight: 800 }}>{value}</Typography>
            </Box>
        </CardContent>
    </Card>
);

const UserServerConsolePage = () => {
    const { containerId } = useParams();
    const [loading, setLoading] = useState(true);
    const [server, setServer] = useState(null);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [command, setCommand] = useState('');
    const [powerLoading, setPowerLoading] = useState('');
    const [commandLoading, setCommandLoading] = useState(false);

    const loadServer = async (silent = false) => {
        try {
            const response = await client.get(`/v1/servers/${containerId}`);
            setServer(response.data.server);
            if (!silent) {
                setMessage({ type: '', text: '' });
            }
        } catch (error) {
            if (!silent) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load server.' });
            }
        } finally {
            if (!silent) {
                setLoading(false);
            }
        }
    };

    useEffect(() => {
        loadServer();
        const interval = window.setInterval(() => {
            client.get(`/v1/servers/${containerId}/resources`)
                .then((response) => {
                    setServer((current) => current ? {
                        ...current,
                        status: response.data.status,
                        runtime: {
                            ...current.runtime,
                            resources: response.data.resources,
                            last_resource_at: response.data.last_resource_at,
                        },
                    } : current);
                })
                .catch(() => {});
        }, 5000);

        const handleRealtime = (event) => {
            const payload = event.detail;
            const payloadContainerId = payload?.server?.containerId || payload?.server?.routeId;

            if (payloadContainerId !== containerId) {
                return;
            }

            setServer((current) => {
                if (!current) {
                    return current;
                }

                switch (payload.type) {
                    case 'server:resource-update':
                        return {
                            ...current,
                            status: payload.powerState || current.status,
                            runtime: {
                                ...current.runtime,
                                resources: payload.resource,
                                power_state: payload.powerState || current.runtime?.power_state,
                                last_resource_at: payload.at,
                            },
                        };
                    case 'server:power-state':
                        return {
                            ...current,
                            status: payload.powerState || current.status,
                            runtime: {
                                ...current.runtime,
                                power_state: payload.powerState,
                            },
                        };
                    case 'server:console':
                        return {
                            ...current,
                            runtime: {
                                ...current.runtime,
                                console_output: payload.fullOutput,
                                last_console_at: payload.at,
                            },
                        };
                    case 'server:install-output':
                        return {
                            ...current,
                            runtime: {
                                ...current.runtime,
                                install_output: payload.fullOutput,
                                last_install_output_at: payload.at,
                            },
                        };
                    case 'server:install-state':
                        return {
                            ...current,
                            install_state: {
                                ...current.install_state,
                                state: payload.installState,
                                message: payload.message,
                                is_installing: ['installing', 'reinstalling'].includes(payload.installState),
                                has_install_error: payload.installState === 'failed',
                            },
                            runtime: {
                                ...current.runtime,
                                install_output: payload.installOutput,
                                last_install_output_at: payload.at,
                            },
                        };
                    default:
                        return current;
                }
            });
        };

        window.addEventListener('ra:notification-event', handleRealtime);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener('ra:notification-event', handleRealtime);
        };
    }, [containerId]);

    const resources = server?.runtime?.resources || {};
    const powerButtons = [
        { signal: 'start', label: 'Start', icon: <StartIcon /> },
        { signal: 'restart', label: 'Restart', icon: <RestartIcon /> },
        { signal: 'stop', label: 'Stop', icon: <StopIcon /> },
        { signal: 'kill', label: 'Kill', icon: <KillIcon /> },
    ];

    const installScreen = Boolean(server?.feature_availability?.install_screen);
    const nodeReason = server?.node_health?.reason_text;
    const consoleOutput = installScreen ? server?.runtime?.install_output : server?.runtime?.console_output;

    const limitsSummary = useMemo(() => {
        if (!server?.limits) {
            return '';
        }

        return `${server.limits.cpu}% CPU • ${server.limits.memory} MiB RAM • ${server.limits.disk} MiB Disk`;
    }, [server]);

    const handlePower = async (signal) => {
        setPowerLoading(signal);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post(`/v1/servers/${containerId}/power`, { signal });
            setMessage({ type: 'success', text: response.data.message || 'Power signal sent.' });
            await loadServer(true);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to dispatch power action.' });
        } finally {
            setPowerLoading('');
        }
    };

    const handleCommand = async () => {
        if (!command.trim()) {
            return;
        }

        setCommandLoading(true);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post(`/v1/servers/${containerId}/console`, { command });
            setMessage({ type: 'success', text: response.data.message || 'Console command sent.' });
            setCommand('');
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to send console command.' });
        } finally {
            setCommandLoading(false);
        }
    };

    if (loading) {
        return <Box sx={{ py: 12, display: 'flex', justifyContent: 'center' }}><CircularProgress /></Box>;
    }

    if (!server) {
        return <Alert severity="error">{message.text || 'Server not found.'}</Alert>;
    }

    return (
        <Box sx={{ display: 'grid', gap: 3 }}>
            <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
                <Box>
                    <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                        <Typography variant="h4" sx={{ fontWeight: 900 }}>{server.name}</Typography>
                        <Chip label={server.status || 'offline'} color={statusColor(server.status)} sx={{ textTransform: 'uppercase', fontWeight: 700 }} />
                    </Stack>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        {server.primary_allocation ? `${server.primary_allocation.ip}:${server.primary_allocation.port}` : 'No primary allocation'} • {limitsSummary}
                    </Typography>
                </Box>
                <Stack direction="row" spacing={1} flexWrap="wrap">
                    {powerButtons.map((button) => (
                        <Button
                            key={button.signal}
                            variant={button.signal === 'kill' ? 'outlined' : 'contained'}
                            color={button.signal === 'kill' ? 'error' : 'primary'}
                            startIcon={button.icon}
                            onClick={() => handlePower(button.signal)}
                            disabled={Boolean(powerLoading) || !server.permissions?.can_power}
                        >
                            {powerLoading === button.signal ? 'Working...' : button.label}
                        </Button>
                    ))}
                </Stack>
            </Stack>

            {message.text ? <Alert severity={message.type || 'info'}>{message.text}</Alert> : null}
            {!server.node_health?.is_active ? <Alert severity="warning">{nodeReason}</Alert> : null}

            <Grid container spacing={2}>
                <Grid item xs={12} md={3}>
                    <StatCard label="CPU" value={`${resources.cpu_percent ?? 0}%`} icon={<CpuIcon color="primary" />} />
                </Grid>
                <Grid item xs={12} md={3}>
                    <StatCard label="Memory" value={formatBytes(resources.memory_bytes ?? 0)} icon={<CpuIcon color="secondary" />} />
                </Grid>
                <Grid item xs={12} md={3}>
                    <StatCard label="Disk" value={formatBytes(resources.disk_bytes ?? 0)} icon={<DiskIcon color="info" />} />
                </Grid>
                <Grid item xs={12} md={3}>
                    <StatCard label="Uptime" value={`${resources.uptime_seconds ?? 0}s`} icon={<ConsoleIcon color="success" />} />
                </Grid>
            </Grid>

            {installScreen ? (
                <Alert severity={server.install_state?.has_install_error ? 'error' : 'info'}>
                    {server.install_state?.message || 'The server is still installing. Console controls stay disabled until installation finishes.'}
                </Alert>
            ) : null}

            <Grid container spacing={3}>
                <Grid item xs={12}>
                    <Card sx={{ border: '1px solid rgba(255,255,255,0.06)', borderRadius: 2, boxShadow: 'none' }}>
                        <CardContent sx={{ display: 'grid', gap: 2 }}>
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                {installScreen ? 'Install Output' : 'Console'}
                            </Typography>
                            <Paper
                                variant="outlined"
                                sx={{
                                    minHeight: 360,
                                    maxHeight: 480,
                                    overflow: 'auto',
                                    p: 2,
                                    bgcolor: '#0c1018',
                                    color: '#d7f9e9',
                                    fontFamily: 'monospace',
                                    whiteSpace: 'pre-wrap',
                                    borderRadius: 2,
                                }}
                            >
                                {consoleOutput || 'No output received yet.'}
                            </Paper>
                            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                                <TextField
                                    fullWidth
                                    label="Console Command"
                                    value={command}
                                    onChange={(event) => setCommand(event.target.value)}
                                    disabled={!server.permissions?.can_send_command || installScreen}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' && !event.shiftKey) {
                                            event.preventDefault();
                                            handleCommand();
                                        }
                                    }}
                                />
                                <Button
                                    variant="contained"
                                    onClick={handleCommand}
                                    disabled={commandLoading || !server.permissions?.can_send_command || installScreen}
                                >
                                    {commandLoading ? 'Sending...' : 'Send Command'}
                                </Button>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>
        </Box>
    );
};

export default UserServerConsolePage;
