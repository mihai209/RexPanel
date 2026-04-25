import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    Chip,
    FormControlLabel,
    Grid,
    MenuItem,
    Paper,
    Stack,
    Tab,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import {
    NotificationsActive as NotificationsIcon,
    Replay as ReplayIcon,
    Science as ScienceIcon,
    SettingsEthernet as SettingsIcon,
} from '@mui/icons-material';
import { useSearchParams } from 'react-router-dom';
import client from '../../api/client';

const TAB_ORDER = ['send', 'settings', 'logs', 'test'];

const formatDateTime = (value) => {
    if (!value) {
        return 'N/A';
    }

    return new Date(value).toLocaleString();
};

const AdminNotifications = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [users, setUsers] = useState([]);
    const [recentNotifications, setRecentNotifications] = useState([]);
    const [recentLogs, setRecentLogs] = useState([]);
    const [settings, setSettings] = useState(null);
    const [maskedTargets, setMaskedTargets] = useState({});
    const [lastFailedLog, setLastFailedLog] = useState(null);
    const [logs, setLogs] = useState([]);
    const [pagination, setPagination] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [filters, setFilters] = useState({ channel: 'all', status: 'all', page: 1 });
    const [form, setForm] = useState({
        target_mode: 'single',
        user_id: '',
        user_ids: [],
        title: '',
        message: '',
        severity: 'info',
        category: 'general',
        link_url: '',
        send_browser: false,
        send_email: false,
    });
    const [settingsForm, setSettingsForm] = useState({
        browser_enabled: true,
        resend_enabled: false,
        sender_name: '',
        reply_to: '',
        resend_api_key: '',
        resend_from_email: '',
        resend_from_name: '',
    });
    const [testForm, setTestForm] = useState({
        channel: 'discord',
        title: 'RA-panel test notification',
        message: 'This is a manual notification test from the admin center.',
        webhook_url: '',
        sender_name: '',
        reply_to: '',
        discord_webhook: '',
        telegram_bot_token: '',
        telegram_chat_id: '',
        resend_api_key: '',
        resend_from_email: '',
        resend_from_name: '',
        browser_enabled: true,
        resend_enabled: false,
    });

    const activeTab = TAB_ORDER.includes(searchParams.get('tab')) ? searchParams.get('tab') : 'send';

    const hydrateFromPayload = (data) => {
        setUsers(data.users ?? []);
        setRecentNotifications(data.recent_notifications ?? []);
        setRecentLogs(data.recent_logs ?? []);
        setSettings(data.settings ?? null);
        setMaskedTargets(data.test_center?.masked_targets ?? {});
        setLastFailedLog(data.test_center?.last_failed_log ?? null);
        setLogs(data.logs_payload?.logs?.data ?? []);
        setPagination(data.logs_payload?.logs ?? null);

        const nextSettings = data.settings ?? null;
        setForm((current) => ({
            ...current,
            send_browser: nextSettings?.delivery?.browserEnabled ?? false,
            send_email: (nextSettings?.delivery?.resendEnabled ?? false) && (nextSettings?.channels?.resendConfigured ?? false) ? current.send_email : false,
        }));
        setSettingsForm({
            browser_enabled: nextSettings?.delivery?.browserEnabled ?? true,
            resend_enabled: nextSettings?.delivery?.resendEnabled ?? false,
            sender_name: nextSettings?.delivery?.senderName ?? '',
            reply_to: nextSettings?.delivery?.replyTo ?? '',
            resend_api_key: '',
            resend_from_email: nextSettings?.resend?.fromEmail ?? '',
            resend_from_name: nextSettings?.resend?.fromName ?? '',
        });
        setTestForm((current) => ({
            ...current,
            browser_enabled: nextSettings?.delivery?.browserEnabled ?? true,
            resend_enabled: nextSettings?.delivery?.resendEnabled ?? false,
            sender_name: nextSettings?.delivery?.senderName ?? '',
            reply_to: nextSettings?.delivery?.replyTo ?? '',
            resend_from_email: nextSettings?.resend?.fromEmail ?? '',
            resend_from_name: nextSettings?.resend?.fromName ?? '',
        }));
    };

    const load = async () => {
        try {
            const response = await client.get('/v1/admin/notifications');
            hydrateFromPayload(response.data);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to load notifications admin surface.');
        }
    };

    const loadLogs = async (nextFilters) => {
        try {
            const response = await client.get('/v1/admin/notifications/logs', { params: nextFilters });
            setLogs(response.data.logs?.data ?? []);
            setPagination(response.data.logs ?? null);
            setLastFailedLog(response.data.last_failed_log ?? null);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to load delivery logs.');
        }
    };

    useEffect(() => {
        load();
    }, []);

    const tabs = useMemo(() => ([
        { key: 'send', label: 'Send' },
        { key: 'settings', label: 'Delivery Settings' },
        { key: 'logs', label: 'Logs' },
        { key: 'test', label: 'Test Center' },
    ]), []);

    const resendConfigured = Boolean(settings?.channels?.resendConfigured);
    const resendEnabled = Boolean(settings?.delivery?.resendEnabled);
    const browserEnabled = Boolean(settings?.delivery?.browserEnabled);

    const onTabChange = (_, nextTab) => {
        setSearchParams((current) => {
            const updated = new URLSearchParams(current);
            updated.set('tab', nextTab);
            return updated;
        });
    };

    const submitNotification = async (event) => {
        event.preventDefault();
        setMessage('');
        setError('');

        try {
            const payload = {
                ...form,
                user_id: form.target_mode === 'single' ? Number(form.user_id) : null,
                user_ids: form.target_mode === 'selected' ? form.user_ids.map(Number) : [],
            };
            const response = await client.post('/v1/admin/notifications', payload);
            setMessage(`Notifications sent to ${response.data.recipientsCount ?? response.data.recipients_count ?? 0} user(s).`);
            setForm((current) => ({ ...current, title: '', message: '', link_url: '' }));
            await load();
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to send notifications.');
        }
    };

    const saveSettings = async (event) => {
        event.preventDefault();
        setMessage('');
        setError('');

        try {
            const response = await client.put('/v1/admin/notifications/settings', settingsForm);
            setSettings(response.data.settings ?? null);
            setMessage('Notification delivery settings updated.');
            await load();
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to save notification delivery settings.');
        }
    };

    const submitTest = async (event) => {
        event.preventDefault();
        setMessage('');
        setError('');

        try {
            await client.post('/v1/admin/notifications-test/send', testForm);
            setMessage('Notification test executed.');
            await load();
            await loadLogs(filters);
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to execute notification test.');
        }
    };

    const retryLog = async (logId) => {
        setMessage('');
        setError('');

        try {
            await client.post(`/v1/admin/notifications/logs/${logId}/retry`);
            setMessage('Delivery log retried.');
            await loadLogs(filters);
            await load();
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to retry the selected delivery log.');
        }
    };

    const retryLastFailed = async () => {
        setMessage('');
        setError('');

        try {
            await client.post('/v1/admin/notifications/logs/retry-last-failed');
            setMessage('Last failed delivery retried.');
            await loadLogs(filters);
            await load();
        } catch (requestError) {
            setError(requestError.response?.data?.message || 'Failed to retry the last failed delivery log.');
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

    const changeLogFilter = async (key, value) => {
        const nextFilters = { ...filters, [key]: value, page: key === 'page' ? value : 1 };
        setFilters(nextFilters);
        await loadLogs(nextFilters);
    };

    return (
        <Box>
            <Box
                sx={{
                    mb: 4,
                    p: { xs: 3, md: 4 },
                    borderRadius: 3,
                    border: '1px solid rgba(255,255,255,0.06)',
                    background: 'linear-gradient(135deg, rgba(20,28,48,0.98) 0%, rgba(12,18,32,0.96) 100%)',
                }}
            >
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between">
                    <Box>
                        <Chip
                            icon={<NotificationsIcon sx={{ fontSize: 16 }} />}
                            label="Unified Notifications Control"
                            size="small"
                            sx={{ mb: 2, bgcolor: 'rgba(54,211,153,0.12)', color: '#7ce7bf', fontWeight: 700 }}
                        />
                        <Typography variant="h4" sx={{ fontWeight: 800, mb: 1 }}>
                            Notifications now live in one admin surface
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 720, lineHeight: 1.8 }}>
                            Send user notifications, manage delivery settings, inspect delivery logs, and run external transport tests without splitting the workflow across multiple admin pages.
                        </Typography>
                    </Box>
                    <Stack direction={{ xs: 'row', md: 'column' }} spacing={1} alignItems={{ xs: 'flex-start', md: 'flex-end' }}>
                        <Chip label={browserEnabled ? 'Browser Enabled' : 'Browser Disabled'} sx={{ bgcolor: browserEnabled ? 'rgba(54,211,153,0.12)' : 'rgba(255,255,255,0.08)', color: browserEnabled ? '#7ce7bf' : '#a8b0bb', fontWeight: 700 }} />
                        <Chip label={resendEnabled && resendConfigured ? 'Resend Ready' : 'Resend Not Ready'} sx={{ bgcolor: resendEnabled && resendConfigured ? 'rgba(139,178,255,0.15)' : 'rgba(255,255,255,0.08)', color: resendEnabled && resendConfigured ? '#9fc0ff' : '#a8b0bb', fontWeight: 700 }} />
                    </Stack>
                </Stack>
            </Box>

            {message ? <Alert severity="success" sx={{ mb: 3 }}>{message}</Alert> : null}
            {error ? <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert> : null}

            <Paper elevation={0} sx={{ mb: 3, borderRadius: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                <Tabs value={activeTab} onChange={onTabChange} variant="scrollable" scrollButtons="auto" sx={{ px: 1 }}>
                    {tabs.map((tab) => (
                        <Tab key={tab.key} value={tab.key} label={tab.label} sx={{ textTransform: 'none', fontWeight: 700, minHeight: 72 }} />
                    ))}
                </Tabs>
            </Paper>

            {activeTab === 'send' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={7}>
                        <Paper component="form" onSubmit={submitNotification} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack spacing={2.5}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                    Send Notification
                                </Typography>
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                                    <TextField select fullWidth label="Target Mode" value={form.target_mode} onChange={(event) => setForm((current) => ({ ...current, target_mode: event.target.value }))}>
                                        <MenuItem value="single">Single user</MenuItem>
                                        <MenuItem value="selected">Selected users</MenuItem>
                                        <MenuItem value="all">All users</MenuItem>
                                    </TextField>
                                    {form.target_mode === 'single' ? (
                                        <TextField select fullWidth label="User" value={form.user_id} onChange={(event) => setForm((current) => ({ ...current, user_id: event.target.value }))}>
                                            {users.map((user) => (
                                                <MenuItem key={user.id} value={user.id}>{user.username} ({user.email})</MenuItem>
                                            ))}
                                        </TextField>
                                    ) : null}
                                </Stack>
                                {form.target_mode === 'selected' ? (
                                    <TextField select fullWidth label="Selected Users" SelectProps={{ multiple: true }} value={form.user_ids} onChange={(event) => setForm((current) => ({ ...current, user_ids: event.target.value }))}>
                                        {users.map((user) => (
                                            <MenuItem key={user.id} value={user.id}>{user.username} ({user.email})</MenuItem>
                                        ))}
                                    </TextField>
                                ) : null}
                                <TextField fullWidth label="Title" value={form.title} onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} />
                                <TextField fullWidth multiline minRows={5} label="Message" value={form.message} onChange={(event) => setForm((current) => ({ ...current, message: event.target.value }))} />
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                                    <TextField select fullWidth label="Severity" value={form.severity} onChange={(event) => setForm((current) => ({ ...current, severity: event.target.value }))}>
                                        <MenuItem value="info">Info</MenuItem>
                                        <MenuItem value="success">Success</MenuItem>
                                        <MenuItem value="warning">Warning</MenuItem>
                                        <MenuItem value="danger">Danger</MenuItem>
                                    </TextField>
                                    <TextField fullWidth label="Category" value={form.category} onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))} />
                                </Stack>
                                <TextField fullWidth label="Optional Link" value={form.link_url} onChange={(event) => setForm((current) => ({ ...current, link_url: event.target.value }))} />
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                                    <FormControlLabel control={<Checkbox checked={form.send_browser} disabled={!browserEnabled} onChange={(event) => setForm((current) => ({ ...current, send_browser: event.target.checked }))} />} label="Browser eligible" />
                                    <FormControlLabel control={<Checkbox checked={form.send_email} disabled={!resendEnabled || !resendConfigured} onChange={(event) => setForm((current) => ({ ...current, send_email: event.target.checked }))} />} label="Email with Resend" />
                                </Stack>
                                {!resendConfigured ? <Alert severity="info">Configure Resend in the delivery settings tab before enabling email delivery.</Alert> : null}
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <Button type="submit" variant="contained">Send Notification</Button>
                                </Box>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={5}>
                        <Stack spacing={3}>
                            <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                                    Recent Deliveries
                                </Typography>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Channel</TableCell>
                                            <TableCell>Status</TableCell>
                                            <TableCell>Target</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {recentLogs.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={3} sx={{ color: 'text.secondary' }}>No delivery logs yet.</TableCell>
                                            </TableRow>
                                        ) : recentLogs.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell>{log.channel}</TableCell>
                                                <TableCell>{log.status}</TableCell>
                                                <TableCell>{log.target || '—'}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Paper>
                            <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                                    Recent Notifications
                                </Typography>
                                <Stack spacing={1.5}>
                                    {recentNotifications.length === 0 ? (
                                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                            No notifications have been created yet.
                                        </Typography>
                                    ) : recentNotifications.slice(0, 8).map((notification) => (
                                        <Box key={notification.id} sx={{ p: 1.5, borderRadius: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{notification.title}</Typography>
                                            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                                {notification.user?.username ?? 'Unknown user'} · {notification.severity}
                                            </Typography>
                                        </Box>
                                    ))}
                                </Stack>
                            </Paper>
                        </Stack>
                    </Grid>
                </Grid>
            ) : null}

            {activeTab === 'settings' ? (
                <Paper component="form" onSubmit={saveSettings} sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                    <Stack spacing={2.5}>
                        <Stack direction="row" spacing={1} alignItems="center">
                            <SettingsIcon />
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                Delivery Settings
                            </Typography>
                        </Stack>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                            These settings drive browser delivery, Resend-based email delivery, and the saved transport targets used by the test center.
                        </Typography>
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                            <FormControlLabel control={<Checkbox checked={settingsForm.browser_enabled} onChange={(event) => setSettingsForm((current) => ({ ...current, browser_enabled: event.target.checked }))} />} label="Enable browser delivery" />
                            <FormControlLabel control={<Checkbox checked={settingsForm.resend_enabled} onChange={(event) => setSettingsForm((current) => ({ ...current, resend_enabled: event.target.checked }))} />} label="Enable Resend delivery" />
                        </Stack>
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                            <TextField fullWidth label="Sender Name" value={settingsForm.sender_name} onChange={(event) => setSettingsForm((current) => ({ ...current, sender_name: event.target.value }))} />
                            <TextField fullWidth label="Reply-To" value={settingsForm.reply_to} onChange={(event) => setSettingsForm((current) => ({ ...current, reply_to: event.target.value }))} />
                        </Stack>
                        <TextField fullWidth type="password" label="Resend API Key" placeholder={settings?.resend?.apiKey || 're_xxxxxxxxx'} value={settingsForm.resend_api_key} onChange={(event) => setSettingsForm((current) => ({ ...current, resend_api_key: event.target.value }))} helperText="Leave blank to keep the current API key." />
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                            <TextField fullWidth label="From Email" value={settingsForm.resend_from_email} onChange={(event) => setSettingsForm((current) => ({ ...current, resend_from_email: event.target.value }))} />
                            <TextField fullWidth label="From Name" value={settingsForm.resend_from_name} onChange={(event) => setSettingsForm((current) => ({ ...current, resend_from_name: event.target.value }))} />
                        </Stack>
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <Button type="submit" variant="contained">Save Delivery Settings</Button>
                        </Box>
                    </Stack>
                </Paper>
            ) : null}

            {activeTab === 'logs' ? (
                <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between" alignItems={{ xs: 'stretch', md: 'center' }} sx={{ mb: 2 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                            Delivery Logs
                        </Typography>
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                            <TextField select size="small" value={filters.channel} onChange={(event) => changeLogFilter('channel', event.target.value)}>
                                <MenuItem value="all">All channels</MenuItem>
                                <MenuItem value="panel">Panel</MenuItem>
                                <MenuItem value="browser">Browser</MenuItem>
                                <MenuItem value="email">Email</MenuItem>
                                <MenuItem value="discord">Discord</MenuItem>
                                <MenuItem value="telegram">Telegram</MenuItem>
                                <MenuItem value="webhook">Webhook</MenuItem>
                            </TextField>
                            <TextField select size="small" value={filters.status} onChange={(event) => changeLogFilter('status', event.target.value)}>
                                <MenuItem value="all">All statuses</MenuItem>
                                <MenuItem value="sent">Sent</MenuItem>
                                <MenuItem value="failed">Failed</MenuItem>
                                <MenuItem value="skipped">Skipped</MenuItem>
                            </TextField>
                            <Button variant="outlined" onClick={exportJson}>Export JSON</Button>
                        </Stack>
                    </Stack>
                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} justifyContent="space-between" sx={{ mb: 2 }}>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                            Inspect all transport attempts and retry failures without leaving this page.
                        </Typography>
                        <Button variant="outlined" startIcon={<ReplayIcon />} onClick={retryLastFailed} disabled={!lastFailedLog}>
                            Retry Last Failed
                        </Button>
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
                                    <TableCell>{formatDateTime(log.createdAt || log.created_at)}</TableCell>
                                    <TableCell align="right">
                                        {log.status === 'failed' ? <Button size="small" onClick={() => retryLog(log.id)}>Retry</Button> : null}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {pagination?.last_page > 1 ? (
                        <Stack direction="row" spacing={1} justifyContent="flex-end" sx={{ mt: 2 }}>
                            <Button size="small" variant="outlined" disabled={(pagination.current_page ?? 1) <= 1} onClick={() => changeLogFilter('page', (pagination.current_page ?? 1) - 1)}>
                                Previous
                            </Button>
                            <Chip label={`Page ${pagination.current_page ?? 1} / ${pagination.last_page ?? 1}`} />
                            <Button size="small" variant="outlined" disabled={(pagination.current_page ?? 1) >= (pagination.last_page ?? 1)} onClick={() => changeLogFilter('page', (pagination.current_page ?? 1) + 1)}>
                                Next
                            </Button>
                        </Stack>
                    ) : null}
                </Paper>
            ) : null}

            {activeTab === 'test' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={5}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack component="form" spacing={2} onSubmit={submitTest}>
                                <Stack direction="row" spacing={1} alignItems="center">
                                    <ScienceIcon />
                                    <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                        Test Center
                                    </Typography>
                                </Stack>
                                <TextField select label="Channel" value={testForm.channel} onChange={(event) => setTestForm((current) => ({ ...current, channel: event.target.value }))}>
                                    <MenuItem value="discord">Discord</MenuItem>
                                    <MenuItem value="telegram">Telegram</MenuItem>
                                    <MenuItem value="webhook">Webhook</MenuItem>
                                    <MenuItem value="email">Email Preview</MenuItem>
                                </TextField>
                                {testForm.channel === 'webhook' ? (
                                    <TextField label="Webhook URL" value={testForm.webhook_url} onChange={(event) => setTestForm((current) => ({ ...current, webhook_url: event.target.value }))} />
                                ) : null}
                                <TextField label="Sender Name" value={testForm.sender_name} onChange={(event) => setTestForm((current) => ({ ...current, sender_name: event.target.value }))} />
                                <TextField label="Reply-To" value={testForm.reply_to} onChange={(event) => setTestForm((current) => ({ ...current, reply_to: event.target.value }))} />
                                <TextField label="Discord Webhook" value={testForm.discord_webhook} placeholder={maskedTargets.discord || ''} onChange={(event) => setTestForm((current) => ({ ...current, discord_webhook: event.target.value }))} />
                                <TextField label="Telegram Bot Token" value={testForm.telegram_bot_token} placeholder={settings?.delivery?.telegramBotToken || ''} onChange={(event) => setTestForm((current) => ({ ...current, telegram_bot_token: event.target.value }))} />
                                <TextField label="Telegram Chat ID" value={testForm.telegram_chat_id} placeholder={maskedTargets.telegram || ''} onChange={(event) => setTestForm((current) => ({ ...current, telegram_chat_id: event.target.value }))} />
                                <TextField label="Resend API Key" value={testForm.resend_api_key} placeholder={settings?.resend?.apiKey || ''} onChange={(event) => setTestForm((current) => ({ ...current, resend_api_key: event.target.value }))} />
                                <TextField label="Resend From Email" value={testForm.resend_from_email} onChange={(event) => setTestForm((current) => ({ ...current, resend_from_email: event.target.value }))} />
                                <TextField label="Resend From Name" value={testForm.resend_from_name} onChange={(event) => setTestForm((current) => ({ ...current, resend_from_name: event.target.value }))} />
                                <TextField label="Title" value={testForm.title} onChange={(event) => setTestForm((current) => ({ ...current, title: event.target.value }))} />
                                <TextField multiline minRows={4} label="Message" value={testForm.message} onChange={(event) => setTestForm((current) => ({ ...current, message: event.target.value }))} />
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
                                    <Button type="submit" variant="contained">Run Test</Button>
                                    <Button variant="outlined" startIcon={<ReplayIcon />} onClick={retryLastFailed} disabled={!lastFailedLog}>
                                        Retry Last Failed
                                    </Button>
                                </Stack>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={7}>
                        <Stack spacing={3}>
                            <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                                    Saved Targets
                                </Typography>
                                <Stack spacing={1.25}>
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>Discord: {maskedTargets.discord || 'Not saved'}</Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>Telegram: {maskedTargets.telegram || 'Not saved'}</Typography>
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>Resend: {maskedTargets.resend || 'Not saved'}</Typography>
                                </Stack>
                            </Paper>
                            <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                                    Latest Delivery Attempts
                                </Typography>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Channel</TableCell>
                                            <TableCell>Status</TableCell>
                                            <TableCell>Target</TableCell>
                                            <TableCell>When</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {logs.slice(0, 10).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4} sx={{ color: 'text.secondary' }}>No delivery attempts recorded yet.</TableCell>
                                            </TableRow>
                                        ) : logs.slice(0, 10).map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell>{log.channel}</TableCell>
                                                <TableCell>{log.status}</TableCell>
                                                <TableCell>{log.target || '—'}</TableCell>
                                                <TableCell>{formatDateTime(log.createdAt || log.created_at)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </Paper>
                        </Stack>
                    </Grid>
                </Grid>
            ) : null}
        </Box>
    );
};

export default AdminNotifications;
