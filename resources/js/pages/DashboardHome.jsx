import React, { useState, useEffect } from 'react';
import { 
    Alert,
    Box, 
    Typography, 
    Paper, 
    Stack,
    Chip,
    Skeleton
} from '@mui/material';
import { 
    Dns as ServerIcon, 
    Language as IpIcon,
    Memory as MemoryIcon,
    Storage as DiskIcon,
    Speed as CpuIcon,
    SportsEsports as GameIcon,
    Campaign as CampaignIcon,
    WarningAmber as WarningIcon,
    BuildCircle as BuildIcon,
    Security as SecurityIcon,
} from '@mui/icons-material';
import client from '../api/client';
import { useNavigate } from 'react-router-dom';

const formatDateTime = (value) => value ? new Date(value).toLocaleString() : 'N/A';

const ServerRow = ({ server, onClick }) => (
    <Paper 
        elevation={0}
        sx={{ 
            bgcolor: 'background.paper',
            border: '1px solid rgba(255, 255, 255, 0.04)',
            borderRadius: 1,
            px: 3,
            py: 2.5,
            display: 'flex',
            alignItems: 'center',
            gap: { xs: 2, md: 4 },
            flexWrap: { xs: 'wrap', md: 'nowrap' },
            cursor: 'pointer',
            transition: 'all 0.15s',
            '&:hover': {
                bgcolor: 'rgba(255,255,255,0.02)',
                borderColor: 'rgba(255, 255, 255, 0.08)'
            }
        }}
        onClick={onClick}
    >
        {/* Icon */}
        <Box sx={{ 
            width: 40, 
            height: 40, 
            borderRadius: 1, 
            bgcolor: 'rgba(0,0,0,0.2)', 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'center',
            flexShrink: 0
        }}>
            <ServerIcon sx={{ fontSize: 20, color: 'rgba(255,255,255,0.4)' }} />
        </Box>

        {/* Name + Description */}
        <Box sx={{ flex: '1 1 auto', minWidth: 0 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, color: '#e2e8f0' }}>
                {server.name}
            </Typography>
            {server.description && (
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.35)', display: 'block', mt: 0.25 }}>
                    {server.description}
                </Typography>
            )}
        </Box>

        {/* Stats */}
        <Stack 
            direction="row" 
            spacing={{ xs: 2, md: 3 }} 
            alignItems="center"
            sx={{ flexShrink: 0, display: { xs: 'none', sm: 'flex' } }}
        >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                <IpIcon sx={{ fontSize: 14, color: 'rgba(255,255,255,0.25)' }} />
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'monospace', fontSize: '0.7rem' }}>
                    {server.allocation || '—'}
                </Typography>
            </Box>

            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                <CpuIcon sx={{ fontSize: 14, color: 'rgba(255,255,255,0.25)' }} />
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.7rem' }}>
                    {server.cpu != null ? `${server.cpu} %` : '—'}
                </Typography>
            </Box>

            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                <MemoryIcon sx={{ fontSize: 14, color: server.memory_alert ? '#f87171' : 'rgba(255,255,255,0.25)' }} />
                <Typography variant="caption" sx={{ 
                    color: server.memory_alert ? '#f87171' : 'rgba(255,255,255,0.5)', 
                    fontSize: '0.7rem',
                    fontWeight: server.memory_alert ? 700 : 400 
                }}>
                    {server.memory || '—'}
                </Typography>
            </Box>

            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                <DiskIcon sx={{ fontSize: 14, color: server.disk_alert ? '#f87171' : 'rgba(255,255,255,0.25)' }} />
                <Typography variant="caption" sx={{ 
                    color: server.disk_alert ? '#f87171' : 'rgba(255,255,255,0.5)', 
                    fontSize: '0.7rem',
                    fontWeight: server.disk_alert ? 700 : 400 
                }}>
                    {server.disk || '—'}
                </Typography>
            </Box>
        </Stack>

        {/* Status */}
        <Chip 
            label={server.status || 'Offline'} 
            size="small"
            sx={{ 
                bgcolor: server.status === 'Running' 
                    ? 'rgba(54, 211, 153, 0.12)' 
                    : server.status === 'Starting' 
                        ? 'rgba(251, 191, 36, 0.12)' 
                        : 'rgba(248, 113, 113, 0.12)',
                color: server.status === 'Running' 
                    ? '#36d399' 
                    : server.status === 'Starting' 
                        ? '#fbbf24' 
                        : '#f87171',
                fontWeight: 700,
                fontSize: '0.65rem',
                height: 24,
                letterSpacing: '0.04em',
                textTransform: 'uppercase',
                border: 'none',
                flexShrink: 0
            }}
        />
    </Paper>
);

const EmptyState = () => (
    <Box sx={{ textAlign: 'center', py: 10 }}>
        <GameIcon sx={{ fontSize: 64, color: 'rgba(255,255,255,0.08)', mb: 2 }} />
        <Typography variant="h6" sx={{ color: 'rgba(255,255,255,0.25)', fontWeight: 600, mb: 1 }}>
            No servers found
        </Typography>
        <Typography variant="body2" sx={{ color: 'rgba(255,255,255,0.15)', maxWidth: 400, mx: 'auto' }}>
            There are no servers associated with your account. Contact an administrator to get started.
        </Typography>
    </Box>
);

const ServerListSkeleton = () => (
    <Stack spacing={1.5}>
        {[1, 2, 3].map((i) => (
            <Skeleton key={i} variant="rounded" height={72} sx={{ borderRadius: 1, bgcolor: 'rgba(255,255,255,0.04)' }} />
        ))}
    </Stack>
);

const DashboardHome = () => {
    const navigate = useNavigate();
    const [servers, setServers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [extensions, setExtensions] = useState(null);

    useEffect(() => {
        const loadServers = async () => {
            try {
                const response = await client.get('/v1/servers');
                setServers(response.data || []);
            } catch {
                setServers([]);
            } finally {
                setLoading(false);
            }
        };

        loadServers();
    }, []);

    useEffect(() => {
        const loadExtensions = async () => {
            try {
                const response = await client.get('/v1/account/extensions/status');
                setExtensions(response.data);
            } catch {
                setExtensions(null);
            }
        };

        loadExtensions();
    }, []);

    return (
        <Box>
            {extensions?.announcer ? (
                <Alert
                    severity={extensions.announcer.severity === 'critical' ? 'error' : (extensions.announcer.severity === 'warning' ? 'warning' : 'success')}
                    icon={<CampaignIcon />}
                    sx={{ mb: 3, borderRadius: 2 }}
                >
                    {extensions.announcer.message}
                </Alert>
            ) : null}

            {(extensions?.incidents?.length || extensions?.maintenance?.length || extensions?.security?.length) ? (
                <Paper elevation={0} sx={{ p: 3, mb: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                        Operations Feed
                    </Typography>
                    <Stack spacing={1.5}>
                        {(extensions?.incidents || []).map((entry) => (
                            <Box key={`incident-${entry.id}`} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(248,113,113,0.08)', border: '1px solid rgba(248,113,113,0.12)' }}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <WarningIcon sx={{ fontSize: 18, color: '#f87171' }} /> {entry.title}
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                    Incident · {entry.severity}
                                </Typography>
                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                            </Box>
                        ))}
                        {(extensions?.maintenance || []).map((entry) => (
                            <Box key={`maintenance-${entry.id}`} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(96,165,250,0.08)', border: '1px solid rgba(96,165,250,0.12)' }}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <BuildIcon sx={{ fontSize: 18, color: '#93c5fd' }} /> {entry.title}
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                    Maintenance · {entry.state} · {formatDateTime(entry.startsAt)} to {formatDateTime(entry.endsAt)}
                                </Typography>
                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                            </Box>
                        ))}
                        {(extensions?.security || []).map((entry) => (
                            <Box key={`security-${entry.id}`} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.12)' }}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <SecurityIcon sx={{ fontSize: 18, color: '#fbbf24' }} /> {entry.title}
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                    Security alert · {entry.severity}
                                </Typography>
                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                            </Box>
                        ))}
                    </Stack>
                </Paper>
            ) : null}

            {/* Header */}
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Box />
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.3)', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', fontSize: '0.65rem' }}>
                    Showing your servers
                </Typography>
            </Box>

            {/* Server List */}
            {loading ? (
                <ServerListSkeleton />
            ) : servers.length > 0 ? (
                <Stack spacing={1.5}>
                    {servers.map((server, i) => (
                        <ServerRow
                            key={server.id || i}
                            server={server}
                            onClick={() => navigate(`/server/${server.container_id || server.route_id}`)}
                        />
                    ))}
                </Stack>
            ) : (
                <EmptyState />
            )}
        </Box>
    );
};

export default DashboardHome;
