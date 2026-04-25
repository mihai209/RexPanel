import React, { useState, useEffect, useCallback } from 'react';
import {
    Box, Paper, Typography, Button, CircularProgress, Alert,
    Table, TableBody, TableCell, TableHead, TableRow, Pagination,
    Stack, Dialog, DialogTitle, DialogContent, DialogActions, Skeleton
} from '@mui/material';
import { DeleteSweep as ClearIcon, History as HistoryIcon } from '@mui/icons-material';
import client from '../../api/client';

const ActivityRow = ({ log }) => {
    const date = new Date(log.created_at);
    const metadata = log.metadata || {};
    const details = [
        metadata.coinsDelta ? `${metadata.coinsDelta > 0 ? '+' : ''}${metadata.coinsDelta} coins` : null,
        metadata.period ? `period: ${metadata.period}` : null,
        metadata.dailyStreak ? `streak: ${metadata.dailyStreak}` : null,
        metadata.reason ? `reason: ${metadata.reason}` : null,
        metadata.policyKey ? `policy: ${metadata.policyKey}` : null,
        metadata.threshold ? `threshold: ${metadata.threshold}` : null,
    ].filter(Boolean);

    return (
        <TableRow sx={{ '&:hover': { bgcolor: 'rgba(255,255,255,0.02)' } }}>
            <TableCell sx={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'monospace', fontSize: '0.75rem', width: 60, borderColor: 'rgba(255,255,255,0.04)' }}>
                #{log.id}
            </TableCell>
            <TableCell sx={{ color: '#e2e8f0', fontWeight: 600, borderColor: 'rgba(255,255,255,0.04)' }}>
                {log.action}
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.35)', display: 'block', mt: 0.5 }}>
                    {log.type || 'legacy'}
                </Typography>
                {details.length > 0 && (
                    <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.45)', display: 'block', mt: 0.5 }}>
                        {details.join(' · ')}
                    </Typography>
                )}
            </TableCell>
            <TableCell sx={{ borderColor: 'rgba(255,255,255,0.04)' }}>
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.35)', fontFamily: 'monospace' }}>
                    {log.ip_address ?? '—'}
                </Typography>
                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.25)', display: 'block', mt: 0.25 }}>
                    {date.toLocaleString()}
                </Typography>
            </TableCell>
        </TableRow>
    );
};

const AccountActivity = () => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [page, setPage] = useState(1);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [clearing, setClearing] = useState(false);

    const fetchActivity = useCallback(async (p = 1) => {
        setLoading(true);
        try {
            const res = await client.get(`/v1/account/activity?page=${p}`);
            setData(res.data);
        } catch {
            setError('Failed to load activity log.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchActivity(page); }, [fetchActivity, page]);

    const handleClear = async () => {
        setClearing(true);
        try {
            await client.delete('/v1/account/activity');
            setConfirmOpen(false);
            setPage(1);
            fetchActivity(1);
        } catch {
            setError('Failed to clear activity log.');
        } finally {
            setClearing(false);
        }
    };

    return (
        <Box>
            {error && <Alert severity="error" variant="filled" sx={{ mb: 3, bgcolor: '#ef4444' }}>{error}</Alert>}

            <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, overflow: 'hidden' }}>
                {/* Header */}
                <Box sx={{ px: 4, py: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 800, color: '#e2e8f0', display: 'flex', alignItems: 'center', gap: 1 }}>
                        <HistoryIcon sx={{ fontSize: 20 }} /> Recent Activity
                    </Typography>
                    {data?.data?.length > 0 && (
                        <Button
                            startIcon={<ClearIcon />}
                            onClick={() => setConfirmOpen(true)}
                            size="small"
                            color="error"
                            variant="outlined"
                            sx={{ borderColor: 'rgba(248,113,113,0.3)', '&:hover': { borderColor: '#f87171' } }}
                        >
                            Clear Activity
                        </Button>
                    )}
                </Box>

                {/* Table */}
                {loading ? (
                    <Box sx={{ p: 4 }}>
                        <Stack spacing={2}>
                            {[1, 2, 3, 4, 5].map(i => <Skeleton key={i} variant="rounded" height={48} sx={{ bgcolor: 'rgba(255,255,255,0.04)' }} />)}
                        </Stack>
                    </Box>
                ) : data?.data?.length === 0 ? (
                    <Box sx={{ textAlign: 'center', py: 10 }}>
                        <HistoryIcon sx={{ fontSize: 48, color: 'rgba(255,255,255,0.08)', mb: 1 }} />
                        <Typography variant="body2" sx={{ color: 'rgba(255,255,255,0.25)' }}>
                            No activity recorded yet.
                        </Typography>
                    </Box>
                ) : (
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell sx={{ color: 'rgba(255,255,255,0.3)', fontWeight: 700, fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '0.08em', borderColor: 'rgba(255,255,255,0.05)' }}>ID</TableCell>
                                <TableCell sx={{ color: 'rgba(255,255,255,0.3)', fontWeight: 700, fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '0.08em', borderColor: 'rgba(255,255,255,0.05)' }}>Action / Metadata</TableCell>
                                <TableCell sx={{ color: 'rgba(255,255,255,0.3)', fontWeight: 700, fontSize: '0.7rem', textTransform: 'uppercase', letterSpacing: '0.08em', borderColor: 'rgba(255,255,255,0.05)' }}>IP — Timestamp</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {data?.data?.map(log => <ActivityRow key={log.id} log={log} />)}
                        </TableBody>
                    </Table>
                )}

                {/* Pagination */}
                {data && data.last_page > 1 && (
                    <Box sx={{ px: 4, py: 2.5, display: 'flex', justifyContent: 'flex-end', borderTop: '1px solid rgba(255,255,255,0.04)' }}>
                        <Pagination
                            count={data.last_page}
                            page={page}
                            onChange={(_, p) => setPage(p)}
                            size="small"
                            sx={{
                                '& .MuiPaginationItem-root': { color: 'text.secondary' },
                                '& .Mui-selected': { bgcolor: 'primary.main', color: '#fff' },
                            }}
                        />
                    </Box>
                )}
            </Paper>

            {/* Confirm Dialog */}
            <Dialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                PaperProps={{ sx: { bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.08)', borderRadius: 2 } }}
            >
                <DialogTitle sx={{ fontWeight: 700 }}>Clear Activity Log?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        This will permanently delete all recorded activity. This action cannot be undone.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3 }}>
                    <Button onClick={() => setConfirmOpen(false)} variant="outlined" sx={{ borderColor: 'rgba(255,255,255,0.1)' }}>
                        Cancel
                    </Button>
                    <Button onClick={handleClear} color="error" variant="contained" disabled={clearing}>
                        {clearing ? <CircularProgress size={18} color="inherit" /> : 'Clear All'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AccountActivity;
