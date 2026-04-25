import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    Typography,
} from '@mui/material';
import client from '../../api/client';

const AdminIncidents = () => {
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const { data } = await client.get('/v1/admin/incidents');
            setPayload(data);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load incidents.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const runAction = async (method, url, success) => {
        try {
            await client[method](url);
            setMessage({ type: 'success', text: success });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Action failed.' });
        }
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>Incident Center</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Runtime incidents come from policy events. Extension incidents are mirrored here read-only.
                    </Typography>
                </Box>
                <Stack direction="row" spacing={1}>
                    <Button variant="outlined" onClick={() => window.open('/api/v1/admin/incidents/export.json', '_blank')}>Export JSON</Button>
                    <Button variant="outlined" onClick={() => window.open('/api/v1/admin/incidents/export.html', '_blank')}>Export HTML</Button>
                    <Button color="warning" onClick={() => runAction('post', '/v1/admin/incidents/clear-resolved', 'Resolved incidents cleared.')}>Clear Resolved</Button>
                    <Button color="error" variant="contained" onClick={() => runAction('post', '/v1/admin/incidents/clear', 'Runtime incidents cleared.')}>Clear All</Button>
                </Stack>
            </Stack>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 2 }}>{message.text}</Alert>}

            {loading ? <CircularProgress /> : (
                <Stack spacing={3}>
                    <Paper sx={{ p: 2.5 }}>
                        <Stack direction="row" spacing={1}>
                            <Chip label={`Open ${payload?.summary?.open || 0}`} color="warning" />
                            <Chip label={`Resolved ${payload?.summary?.resolved || 0}`} />
                            <Chip label={`Extension Open ${payload?.summary?.extension_open || 0}`} color="info" />
                        </Stack>
                    </Paper>

                    <Paper sx={{ p: 2.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Runtime Incidents</Typography>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Title</TableCell>
                                    <TableCell>Severity</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Created</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {(payload?.runtime?.data || []).map((incident) => (
                                    <TableRow key={incident.id}>
                                        <TableCell>
                                            <Typography variant="body2" sx={{ fontWeight: 700 }}>{incident.title}</Typography>
                                            <Typography variant="caption" sx={{ color: 'text.secondary' }}>{incident.reason || incident.policy_key}</Typography>
                                        </TableCell>
                                        <TableCell>{incident.severity}</TableCell>
                                        <TableCell>{incident.status}</TableCell>
                                        <TableCell>{incident.created_at}</TableCell>
                                        <TableCell align="right">
                                            {incident.status === 'open' ? (
                                                <Button size="small" onClick={() => runAction('post', `/v1/admin/incidents/${incident.id}/resolve`, 'Incident resolved.')}>Resolve</Button>
                                            ) : (
                                                <Button size="small" onClick={() => runAction('post', `/v1/admin/incidents/${incident.id}/reopen`, 'Incident reopened.')}>Reopen</Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Paper>

                    <Paper sx={{ p: 2.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Extension Incidents</Typography>
                        {(payload?.extension_incidents || []).map((incident) => (
                            <Box key={incident.id} sx={{ mb: 1.5 }}>
                                <Typography variant="body2" sx={{ fontWeight: 700 }}>{incident.title}</Typography>
                                <Typography variant="caption" sx={{ color: 'text.secondary' }}>{incident.status} • {incident.severity}</Typography>
                            </Box>
                        ))}
                        {!(payload?.extension_incidents || []).length && (
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>No extension incidents found.</Typography>
                        )}
                    </Paper>
                </Stack>
            )}
        </Box>
    );
};

export default AdminIncidents;
