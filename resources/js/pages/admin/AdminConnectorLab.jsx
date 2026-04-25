import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Grid,
    Paper,
    Stack,
    Typography,
} from '@mui/material';
import client from '../../api/client';

const AdminConnectorLab = () => {
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const { data } = await client.get('/v1/admin/connector-lab');
            setPayload(data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load connector lab.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const runDiagnostics = async (nodeId) => {
        try {
            await client.post(`/v1/admin/nodes/${nodeId}/diagnostics/run`);
            setMessage({ type: 'success', text: 'Diagnostics request queued.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to queue diagnostics.' });
        }
    };

    return (
        <Box>
            <Typography variant="h5" sx={{ fontWeight: 800, mb: 0.5 }}>Connector Lab</Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 3 }}>
                Nodes are treated as connectors here. Diagnostics are persisted from the connector runtime.
            </Typography>
            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 2 }}>{message.text}</Alert>}

            {loading ? <CircularProgress /> : (
                <Grid container spacing={2}>
                    {(payload?.connectors || []).map((connector) => (
                        <Grid item xs={12} md={6} lg={4} key={connector.id}>
                            <Paper sx={{ p: 2.5, height: '100%' }}>
                                <Stack direction="row" justifyContent="space-between" alignItems="flex-start" sx={{ mb: 1.5 }}>
                                    <Box>
                                        <Typography variant="h6" sx={{ fontWeight: 800 }}>{connector.name}</Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>{connector.fqdn}</Typography>
                                    </Box>
                                    <Chip label={connector.health.status} color={connector.health.status === 'healthy' ? 'success' : connector.health.status === 'offline' ? 'error' : 'warning'} size="small" />
                                </Stack>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>
                                    {(connector.location?.short_name || connector.location?.name || 'Unknown location')} • {connector.server_count} servers • {connector.allocation_count} allocations
                                </Typography>
                                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
                                    Failed checks: {connector.triage.failed_checks} • Warnings: {connector.triage.warning_checks}
                                </Typography>
                                <Stack spacing={0.75} sx={{ mb: 2 }}>
                                    {(connector.compatibility_checks || []).map((check) => (
                                        <Typography key={check.key} variant="caption" sx={{ color: 'text.secondary' }}>
                                            {check.label}: {check.status}
                                        </Typography>
                                    ))}
                                </Stack>
                                <Button variant="contained" fullWidth onClick={() => runDiagnostics(connector.id)}>Run Diagnostics Now</Button>
                            </Paper>
                        </Grid>
                    ))}
                </Grid>
            )}
        </Box>
    );
};

export default AdminConnectorLab;
