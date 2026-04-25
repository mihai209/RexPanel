import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
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

const AdminServiceHealthChecks = () => {
    const [history, setHistory] = useState([]);
    const [latest, setLatest] = useState([]);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        setLoading(true);
        try {
            const [historyRes, latestRes] = await Promise.all([
                client.get('/v1/admin/service-health-checks'),
                client.get('/v1/admin/service-health-checks/latest'),
            ]);
            setHistory(historyRes.data.data || []);
            setLatest(latestRes.data.data || []);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load health checks.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const runChecks = async () => {
        try {
            await client.post('/v1/admin/service-health-checks/run');
            setMessage({ type: 'success', text: 'Manual service health checks completed.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to run health checks.' });
        }
    };

    return (
        <Box>
            <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>Service Health Checks</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Manual runs create one node-level health row per node. Server-level telemetry remains connector-driven.
                    </Typography>
                </Box>
                <Button variant="contained" onClick={runChecks}>Run Checks Now</Button>
            </Stack>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 2 }}>{message.text}</Alert>}

            {loading ? <CircularProgress /> : (
                <Stack spacing={3}>
                    <Paper sx={{ p: 2.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Latest by Node</Typography>
                        {(latest || []).map((entry) => (
                            <Typography key={entry.id} variant="body2" sx={{ color: 'text.secondary', mb: 0.75 }}>
                                {entry.node?.name || 'Unknown'} • {entry.status} • {entry.checked_at}
                            </Typography>
                        ))}
                        {!latest.length && <Typography variant="body2" sx={{ color: 'text.secondary' }}>No node-level health history yet.</Typography>}
                    </Paper>
                    <Paper sx={{ p: 2.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>History</Typography>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Node</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Checked At</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {history.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell>{entry.node?.name || 'Unknown'}</TableCell>
                                        <TableCell>{entry.status}</TableCell>
                                        <TableCell>{entry.checked_at}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Paper>
                </Stack>
            )}
        </Box>
    );
};

export default AdminServiceHealthChecks;
