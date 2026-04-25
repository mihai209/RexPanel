import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Grid,
    MenuItem,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import {
    Science as ScienceIcon,
    Replay as ReplayIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const AdminNotificationsTest = () => {
    const [config, setConfig] = useState(null);
    const [logs, setLogs] = useState([]);
    const [filters, setFilters] = useState({ channel: 'all', status: 'all' });
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        browser_enabled: true,
        resend_enabled: false,
        sender_name: '',
        reply_to: '',
        discord_webhook: '',
        telegram_bot_token: '',
        telegram_chat_id: '',
        resend_api_key: '',
        resend_from_email: '',
        resend_from_name: '',
        channel: 'discord',
        title: 'RA-panel test notification',
        message: 'This is a manual notification test from the admin center.',
        webhook_url: '',
    });

    const load = async (activeFilters = filters) => {
        try {
            const [configResponse, logsResponse] = await Promise.all([
                client.get('/v1/admin/notifications-test/config'),
                client.get('/v1/admin/notifications/logs', { params: activeFilters }),
            ]);
            setConfig(configResponse.data);
            setLogs(logsResponse.data.logs?.data ?? []);

            const settings = configResponse.data.settings ?? {};
            setForm((previous) => ({
                ...previous,
                browser_enabled: settings.delivery?.browserEnabled ?? previous.browser_enabled,
                resend_enabled: settings.delivery?.resendEnabled ?? previous.resend_enabled,
                sender_name: settings.delivery?.senderName ?? '',
                reply_to: settings.delivery?.replyTo ?? '',
                resend_from_email: settings.resend?.fromEmail ?? '',
                resend_from_name: settings.resend?.fromName ?? '',
            }));
        } catch {
            setError('Failed to load notifications test center.');
        }
    };

    useEffect(() => {
        load(filters);
    }, []);

    const submitTest = async (event) => {
        event.preventDefault();
        setMessage('');
        setError('');

        try {
            await client.post('/v1/admin/notifications-test/send', form);
            setMessage('Notification test executed.');
            await load(filters);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to execute notification test.');
        }
    };

    const retryLog = async (logId) => {
        try {
            await client.post(`/v1/admin/notifications/logs/${logId}/retry`);
            setMessage('Delivery log retried.');
            await load(filters);
        } catch {
            setError('Failed to retry the selected delivery log.');
        }
    };

    const retryLastFailed = async () => {
        try {
            await client.post('/v1/admin/notifications/logs/retry-last-failed');
            setMessage('Last failed log retried.');
            await load(filters);
        } catch {
            setError('Failed to retry the last failed delivery log.');
        }
    };

    const exportJson = () => {
        const blob = new Blob([JSON.stringify(logs, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'notification-delivery-logs.json';
        anchor.click();
        URL.revokeObjectURL(url);
    };

    const lastFailedId = useMemo(() => config?.last_failed_log?.id ?? null, [config]);

    return (
        <Box>
            <Typography variant="h5" sx={{ fontWeight: 800, mb: 1, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                <ScienceIcon /> Notifications Test
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 4 }}>
                Configure external delivery targets, run probes, inspect logs, and retry failures.
            </Typography>

            {message ? <Alert severity="success" sx={{ mb: 3 }}>{message}</Alert> : null}
            {error ? <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert> : null}

            <Grid container spacing={3}>
                <Grid item xs={12} lg={5}>
                    <Paper sx={{ p: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                        <Stack component="form" spacing={2} onSubmit={submitTest}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                Delivery Settings + Test Probe
                            </Typography>
                            <TextField select label="Channel" value={form.channel} onChange={(event) => setForm((previous) => ({ ...previous, channel: event.target.value }))}>
                                <MenuItem value="discord">Discord</MenuItem>
                                <MenuItem value="telegram">Telegram</MenuItem>
                                <MenuItem value="webhook">Webhook</MenuItem>
                                <MenuItem value="email">Email Preview</MenuItem>
                            </TextField>
                            {form.channel === 'webhook' ? (
                                <TextField label="Webhook URL" value={form.webhook_url} onChange={(event) => setForm((previous) => ({ ...previous, webhook_url: event.target.value }))} />
                            ) : null}
                            <TextField label="Sender Name" value={form.sender_name} onChange={(event) => setForm((previous) => ({ ...previous, sender_name: event.target.value }))} />
                            <TextField label="Reply-To" value={form.reply_to} onChange={(event) => setForm((previous) => ({ ...previous, reply_to: event.target.value }))} />
                            <TextField label="Discord Webhook" value={form.discord_webhook} onChange={(event) => setForm((previous) => ({ ...previous, discord_webhook: event.target.value }))} placeholder={config?.masked_targets?.discord || ''} />
                            <TextField label="Telegram Bot Token" value={form.telegram_bot_token} onChange={(event) => setForm((previous) => ({ ...previous, telegram_bot_token: event.target.value }))} placeholder={config?.settings?.delivery?.telegramBotToken || ''} />
                            <TextField label="Telegram Chat ID" value={form.telegram_chat_id} onChange={(event) => setForm((previous) => ({ ...previous, telegram_chat_id: event.target.value }))} placeholder={config?.masked_targets?.telegram || ''} />
                            <TextField label="Resend API Key" value={form.resend_api_key} onChange={(event) => setForm((previous) => ({ ...previous, resend_api_key: event.target.value }))} placeholder={config?.settings?.resend?.apiKey || ''} />
                            <TextField label="Resend From Email" value={form.resend_from_email} onChange={(event) => setForm((previous) => ({ ...previous, resend_from_email: event.target.value }))} />
                            <TextField label="Resend From Name" value={form.resend_from_name} onChange={(event) => setForm((previous) => ({ ...previous, resend_from_name: event.target.value }))} />
                            <TextField label="Title" value={form.title} onChange={(event) => setForm((previous) => ({ ...previous, title: event.target.value }))} />
                            <TextField
                                multiline
                                minRows={4}
                                label="Message"
                                value={form.message}
                                onChange={(event) => setForm((previous) => ({ ...previous, message: event.target.value }))}
                            />
                            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                                <Button type="submit" variant="contained">Run Test</Button>
                                <Button variant="outlined" startIcon={<ReplayIcon />} onClick={retryLastFailed} disabled={!lastFailedId}>
                                    Retry Last Failed
                                </Button>
                            </Stack>
                        </Stack>
                    </Paper>
                </Grid>

                <Grid item xs={12} lg={7}>
                    <Paper sx={{ p: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2 }}>
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ xs: 'stretch', md: 'center' }} sx={{ mb: 2 }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                Delivery Logs
                            </Typography>
                            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                                <TextField
                                    select
                                    size="small"
                                    value={filters.channel}
                                    onChange={async (event) => {
                                        const next = { ...filters, channel: event.target.value };
                                        setFilters(next);
                                        await load(next);
                                    }}
                                >
                                    <MenuItem value="all">All channels</MenuItem>
                                    <MenuItem value="panel">Panel</MenuItem>
                                    <MenuItem value="browser">Browser</MenuItem>
                                    <MenuItem value="email">Email</MenuItem>
                                    <MenuItem value="discord">Discord</MenuItem>
                                    <MenuItem value="telegram">Telegram</MenuItem>
                                    <MenuItem value="webhook">Webhook</MenuItem>
                                </TextField>
                                <TextField
                                    select
                                    size="small"
                                    value={filters.status}
                                    onChange={async (event) => {
                                        const next = { ...filters, status: event.target.value };
                                        setFilters(next);
                                        await load(next);
                                    }}
                                >
                                    <MenuItem value="all">All statuses</MenuItem>
                                    <MenuItem value="sent">Sent</MenuItem>
                                    <MenuItem value="failed">Failed</MenuItem>
                                    <MenuItem value="skipped">Skipped</MenuItem>
                                </TextField>
                                <Button variant="outlined" onClick={exportJson}>Export JSON</Button>
                            </Stack>
                        </Stack>

                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Channel</TableCell>
                                    <TableCell>Status</TableCell>
                                    <TableCell>Target</TableCell>
                                    <TableCell>When</TableCell>
                                    <TableCell align="right">Action</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {logs.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} sx={{ color: 'text.secondary' }}>
                                            No delivery logs matched the current filters.
                                        </TableCell>
                                    </TableRow>
                                ) : logs.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell>{log.channel}</TableCell>
                                        <TableCell>{log.status}</TableCell>
                                        <TableCell>{log.target || '—'}</TableCell>
                                        <TableCell>{new Date(log.createdAt || log.created_at).toLocaleString()}</TableCell>
                                        <TableCell align="right">
                                            {log.status === 'failed' ? (
                                                <Button size="small" onClick={() => retryLog(log.id)}>Retry</Button>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default AdminNotificationsTest;
