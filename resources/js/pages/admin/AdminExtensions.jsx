import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    FormControlLabel,
    Grid,
    MenuItem,
    Paper,
    Stack,
    Switch,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import {
    Campaign as CampaignIcon,
    Security as SecurityIcon,
    BuildCircle as BuildIcon,
    NotificationsActive as NotificationsIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const tabs = [
    { key: 'announcer', label: 'Announcer' },
    { key: 'webhooks', label: 'Webhooks' },
    { key: 'incidents', label: 'Incidents' },
    { key: 'maintenance', label: 'Maintenance' },
    { key: 'security', label: 'Security Center' },
];

const formatDateTime = (value) => value ? new Date(value).toLocaleString() : 'N/A';

const AdminExtensions = () => {
    const [activeTab, setActiveTab] = useState('announcer');
    const [payload, setPayload] = useState(null);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [announcerForm, setAnnouncerForm] = useState({ enabled: false, severity: 'normal', message: '' });
    const [webhooksForm, setWebhooksForm] = useState({
        moduleEnabled: false,
        enabled: false,
        discordWebhook: '',
        telegramBotToken: '',
        telegramChatId: '',
        events: {
            incidentCreated: true,
            incidentResolved: true,
            maintenanceScheduled: true,
            maintenanceCompleted: true,
            securityAlertCreated: true,
            securityAlertResolved: true,
        },
    });
    const [incidentForm, setIncidentForm] = useState({ title: '', message: '', severity: 'warning' });
    const [maintenanceForm, setMaintenanceForm] = useState({ title: '', message: '', starts_at: '', ends_at: '' });
    const [securityForm, setSecurityForm] = useState({ title: '', message: '', severity: 'warning' });

    const load = async () => {
        try {
            const { data } = await client.get('/v1/admin/extensions');
            setPayload(data);
            setAnnouncerForm({
                enabled: data.settings?.announcer?.enabled ?? false,
                severity: data.settings?.announcer?.severity ?? 'normal',
                message: data.settings?.announcer?.message ?? '',
            });
            setWebhooksForm({
                moduleEnabled: data.settings?.features?.webhooksEnabled ?? false,
                enabled: data.settings?.webhooks?.enabled ?? false,
                discordWebhook: data.settings?.webhooks?.discordWebhook ?? '',
                telegramBotToken: data.settings?.webhooks?.telegramBotToken ?? '',
                telegramChatId: data.settings?.webhooks?.telegramChatId ?? '',
                events: data.settings?.webhooks?.events ?? {},
            });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load extensions.' });
        }
    };

    useEffect(() => {
        load();
    }, []);

    const stats = useMemo(() => ({
        incidents: payload?.incidents?.filter((entry) => entry.isOpen).length ?? 0,
        maintenance: payload?.maintenance?.filter((entry) => !entry.isCompleted).length ?? 0,
        security: payload?.security?.filter((entry) => entry.isOpen).length ?? 0,
    }), [payload]);

    const updateFeatureToggle = async (endpoint, enabled, successText) => {
        try {
            await client.put(endpoint, { enabled });
            setMessage({ type: 'success', text: successText });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update module settings.' });
        }
    };

    const saveAnnouncer = async () => {
        try {
            await client.put('/v1/admin/extensions/announcer', announcerForm);
            setMessage({ type: 'success', text: 'Announcer settings updated.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save announcer.' });
        }
    };

    const saveWebhooks = async () => {
        try {
            await client.put('/v1/admin/extensions/webhooks', webhooksForm);
            setMessage({ type: 'success', text: 'Extensions webhook settings updated.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save webhook settings.' });
        }
    };

    const runWebhookTest = async () => {
        try {
            const { data } = await client.post('/v1/admin/extensions/webhooks/test');
            setMessage({ type: 'success', text: data.message || 'Extensions webhook test executed.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to run webhook test.' });
        }
    };

    const createIncident = async () => {
        try {
            await client.post('/v1/admin/extensions/incidents', incidentForm);
            setIncidentForm({ title: '', message: '', severity: 'warning' });
            setMessage({ type: 'success', text: 'Incident created.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to create incident.' });
        }
    };

    const toggleIncident = async (id) => {
        try {
            await client.post(`/v1/admin/extensions/incidents/${id}/toggle`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to toggle incident.' });
        }
    };

    const deleteIncident = async (id) => {
        try {
            await client.delete(`/v1/admin/extensions/incidents/${id}`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete incident.' });
        }
    };

    const createMaintenance = async () => {
        try {
            await client.post('/v1/admin/extensions/maintenance', maintenanceForm);
            setMaintenanceForm({ title: '', message: '', starts_at: '', ends_at: '' });
            setMessage({ type: 'success', text: 'Maintenance window created.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to create maintenance window.' });
        }
    };

    const toggleMaintenance = async (id) => {
        try {
            await client.post(`/v1/admin/extensions/maintenance/${id}/toggle-complete`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to toggle maintenance.' });
        }
    };

    const deleteMaintenance = async (id) => {
        try {
            await client.delete(`/v1/admin/extensions/maintenance/${id}`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete maintenance window.' });
        }
    };

    const createSecurity = async () => {
        try {
            await client.post('/v1/admin/extensions/security', securityForm);
            setSecurityForm({ title: '', message: '', severity: 'warning' });
            setMessage({ type: 'success', text: 'Security alert created.' });
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to create security alert.' });
        }
    };

    const toggleSecurity = async (id) => {
        try {
            await client.post(`/v1/admin/extensions/security/${id}/toggle`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to toggle security alert.' });
        }
    };

    const deleteSecurity = async (id) => {
        try {
            await client.delete(`/v1/admin/extensions/security/${id}`);
            await load();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete security alert.' });
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
                    background: 'linear-gradient(135deg, rgba(26,32,54,0.98) 0%, rgba(15,20,36,0.96) 100%)',
                }}
            >
                <Grid container spacing={3} alignItems="center">
                    <Grid item xs={12} md={7}>
                        <Chip icon={<BuildIcon sx={{ fontSize: 16 }} />} label="Full CPanel Extensions Port" size="small" sx={{ mb: 2, bgcolor: 'rgba(139,178,255,0.16)', color: '#a7c3ff', fontWeight: 700 }} />
                        <Typography variant="h4" sx={{ fontWeight: 800, mb: 1 }}>
                            Extensions control center
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#98a4b3', lineHeight: 1.8, maxWidth: 720 }}>
                            Manage announcer broadcasts, extension-backed webhooks, incidents, maintenance windows, and security alerts from one admin surface, with immediate runtime consumers on the main dashboard.
                        </Typography>
                    </Grid>
                    <Grid item xs={12} md={5}>
                        <Stack direction="row" spacing={1} justifyContent={{ xs: 'flex-start', md: 'flex-end' }} flexWrap="wrap">
                            <Chip label={`${stats.incidents} Open Incidents`} sx={{ bgcolor: 'rgba(248,113,113,0.14)', color: '#fca5a5', fontWeight: 700 }} />
                            <Chip label={`${stats.maintenance} Active Windows`} sx={{ bgcolor: 'rgba(96,165,250,0.14)', color: '#93c5fd', fontWeight: 700 }} />
                            <Chip label={`${stats.security} Open Alerts`} sx={{ bgcolor: 'rgba(245,158,11,0.14)', color: '#fcd34d', fontWeight: 700 }} />
                        </Stack>
                    </Grid>
                </Grid>
            </Box>

            {message.text ? <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert> : null}

            <Paper elevation={0} sx={{ mb: 3, borderRadius: 3, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                <Tabs value={activeTab} onChange={(_, value) => setActiveTab(value)} variant="scrollable" scrollButtons="auto" sx={{ px: 1 }}>
                    {tabs.map((tab) => (
                        <Tab key={tab.key} value={tab.key} label={tab.label} sx={{ textTransform: 'none', fontWeight: 700, minHeight: 72 }} />
                    ))}
                </Tabs>
            </Paper>

            {activeTab === 'announcer' ? (
                <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                    <Stack spacing={2.5}>
                        <Stack direction="row" spacing={1} alignItems="center">
                            <CampaignIcon />
                            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                Dashboard announcer
                            </Typography>
                        </Stack>
                        <FormControlLabel control={<Switch checked={announcerForm.enabled} onChange={(event) => setAnnouncerForm((current) => ({ ...current, enabled: event.target.checked }))} />} label="Enable dashboard announcement" />
                        <TextField select label="Severity" value={announcerForm.severity} onChange={(event) => setAnnouncerForm((current) => ({ ...current, severity: event.target.value }))}>
                            <MenuItem value="normal">Normal</MenuItem>
                            <MenuItem value="warning">Warning</MenuItem>
                            <MenuItem value="critical">Critical</MenuItem>
                        </TextField>
                        <TextField multiline minRows={4} label="Message" value={announcerForm.message} onChange={(event) => setAnnouncerForm((current) => ({ ...current, message: event.target.value }))} />
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <Button variant="contained" onClick={saveAnnouncer}>Save Announcer</Button>
                        </Box>
                    </Stack>
                </Paper>
            ) : null}

            {activeTab === 'webhooks' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={6}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack spacing={2}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                                    Extensions webhook configuration
                                </Typography>
                                <FormControlLabel control={<Switch checked={webhooksForm.moduleEnabled} onChange={(event) => setWebhooksForm((current) => ({ ...current, moduleEnabled: event.target.checked }))} />} label="Enable Webhooks module" />
                                <FormControlLabel control={<Switch checked={webhooksForm.enabled} onChange={(event) => setWebhooksForm((current) => ({ ...current, enabled: event.target.checked }))} />} label="Dispatch saved events" />
                                <TextField label="Discord Webhook URL" value={webhooksForm.discordWebhook} onChange={(event) => setWebhooksForm((current) => ({ ...current, discordWebhook: event.target.value }))} />
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                                    <TextField fullWidth label="Telegram Bot Token" value={webhooksForm.telegramBotToken} onChange={(event) => setWebhooksForm((current) => ({ ...current, telegramBotToken: event.target.value }))} />
                                    <TextField fullWidth label="Telegram Chat ID" value={webhooksForm.telegramChatId} onChange={(event) => setWebhooksForm((current) => ({ ...current, telegramChatId: event.target.value }))} />
                                </Stack>
                                <Grid container spacing={1}>
                                    {Object.entries(webhooksForm.events || {}).map(([key, enabled]) => (
                                        <Grid item xs={12} md={6} key={key}>
                                            <FormControlLabel
                                                control={<Switch checked={Boolean(enabled)} onChange={(event) => setWebhooksForm((current) => ({ ...current, events: { ...current.events, [key]: event.target.checked } }))} />}
                                                label={key}
                                            />
                                        </Grid>
                                    ))}
                                </Grid>
                                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} justifyContent="flex-end">
                                    <Button variant="outlined" onClick={runWebhookTest}>Send Test</Button>
                                    <Button variant="contained" onClick={saveWebhooks}>Save Webhooks</Button>
                                </Stack>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={6}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>
                                Recent extension webhook logs
                            </Typography>
                            <Stack spacing={1.5}>
                                {(payload?.webhook_logs || []).length === 0 ? (
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                        No extension webhook logs recorded yet.
                                    </Typography>
                                ) : (payload?.webhook_logs || []).map((log) => (
                                    <Box key={log.id} sx={{ p: 1.75, borderRadius: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <Stack direction="row" justifyContent="space-between" spacing={1}>
                                            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{log.channel} · {log.status}</Typography>
                                            <Typography variant="caption" sx={{ color: 'text.secondary' }}>{formatDateTime(log.createdAt)}</Typography>
                                        </Stack>
                                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                            {log.target || 'No target'}{log.errorText ? ` · ${log.errorText}` : ''}
                                        </Typography>
                                    </Box>
                                ))}
                            </Stack>
                        </Paper>
                    </Grid>
                </Grid>
            ) : null}

            {activeTab === 'incidents' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={5}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack spacing={2}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>Incident module</Typography>
                                <FormControlLabel control={<Switch checked={Boolean(payload?.settings?.features?.incidentsEnabled)} onChange={(event) => updateFeatureToggle('/v1/admin/extensions/incidents/settings', event.target.checked, 'Incidents settings updated.')} />} label="Enable incidents in runtime/public output" />
                                <TextField label="Title" value={incidentForm.title} onChange={(event) => setIncidentForm((current) => ({ ...current, title: event.target.value }))} />
                                <TextField select label="Severity" value={incidentForm.severity} onChange={(event) => setIncidentForm((current) => ({ ...current, severity: event.target.value }))}>
                                    <MenuItem value="normal">Normal</MenuItem>
                                    <MenuItem value="warning">Warning</MenuItem>
                                    <MenuItem value="critical">Critical</MenuItem>
                                </TextField>
                                <TextField multiline minRows={4} label="Message" value={incidentForm.message} onChange={(event) => setIncidentForm((current) => ({ ...current, message: event.target.value }))} />
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <Button variant="contained" onClick={createIncident}>Create Incident</Button>
                                </Box>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={7}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>Saved incidents</Typography>
                            <Stack spacing={1.5}>
                                {(payload?.incidents || []).length === 0 ? (
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>No incidents recorded.</Typography>
                                ) : (payload?.incidents || []).map((entry) => (
                                    <Box key={entry.id} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
                                            <Box>
                                                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{entry.title}</Typography>
                                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                                    {entry.severity} · {entry.status} · {formatDateTime(entry.createdAt)}
                                                </Typography>
                                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                                            </Box>
                                            <Stack direction="row" spacing={1}>
                                                <Button size="small" onClick={() => toggleIncident(entry.id)}>{entry.isOpen ? 'Resolve' : 'Reopen'}</Button>
                                                <Button size="small" color="error" onClick={() => deleteIncident(entry.id)}>Delete</Button>
                                            </Stack>
                                        </Stack>
                                    </Box>
                                ))}
                            </Stack>
                        </Paper>
                    </Grid>
                </Grid>
            ) : null}

            {activeTab === 'maintenance' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={5}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack spacing={2}>
                                <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>Maintenance module</Typography>
                                <FormControlLabel control={<Switch checked={Boolean(payload?.settings?.features?.maintenanceEnabled)} onChange={(event) => updateFeatureToggle('/v1/admin/extensions/maintenance/settings', event.target.checked, 'Maintenance settings updated.')} />} label="Enable maintenance runtime/public output" />
                                <TextField label="Title" value={maintenanceForm.title} onChange={(event) => setMaintenanceForm((current) => ({ ...current, title: event.target.value }))} />
                                <TextField multiline minRows={4} label="Message" value={maintenanceForm.message} onChange={(event) => setMaintenanceForm((current) => ({ ...current, message: event.target.value }))} />
                                <TextField type="datetime-local" InputLabelProps={{ shrink: true }} label="Starts At" value={maintenanceForm.starts_at} onChange={(event) => setMaintenanceForm((current) => ({ ...current, starts_at: event.target.value }))} />
                                <TextField type="datetime-local" InputLabelProps={{ shrink: true }} label="Ends At" value={maintenanceForm.ends_at} onChange={(event) => setMaintenanceForm((current) => ({ ...current, ends_at: event.target.value }))} />
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <Button variant="contained" onClick={createMaintenance}>Create Maintenance Window</Button>
                                </Box>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={7}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>Scheduled windows</Typography>
                            <Stack spacing={1.5}>
                                {(payload?.maintenance || []).length === 0 ? (
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>No maintenance windows recorded.</Typography>
                                ) : (payload?.maintenance || []).map((entry) => (
                                    <Box key={entry.id} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
                                            <Box>
                                                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{entry.title}</Typography>
                                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                                    {entry.state} · {formatDateTime(entry.startsAt)} to {formatDateTime(entry.endsAt)}
                                                </Typography>
                                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                                            </Box>
                                            <Stack direction="row" spacing={1}>
                                                <Button size="small" onClick={() => toggleMaintenance(entry.id)}>{entry.isCompleted ? 'Reopen' : 'Complete'}</Button>
                                                <Button size="small" color="error" onClick={() => deleteMaintenance(entry.id)}>Delete</Button>
                                            </Stack>
                                        </Stack>
                                    </Box>
                                ))}
                            </Stack>
                        </Paper>
                    </Grid>
                </Grid>
            ) : null}

            {activeTab === 'security' ? (
                <Grid container spacing={3}>
                    <Grid item xs={12} lg={5}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Stack spacing={2}>
                                <Stack direction="row" spacing={1} alignItems="center">
                                    <SecurityIcon />
                                    <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>Security Center module</Typography>
                                </Stack>
                                <FormControlLabel control={<Switch checked={Boolean(payload?.settings?.features?.securityEnabled)} onChange={(event) => updateFeatureToggle('/v1/admin/extensions/security/settings', event.target.checked, 'Security settings updated.')} />} label="Enable security alerts in runtime/public output" />
                                <TextField label="Title" value={securityForm.title} onChange={(event) => setSecurityForm((current) => ({ ...current, title: event.target.value }))} />
                                <TextField select label="Severity" value={securityForm.severity} onChange={(event) => setSecurityForm((current) => ({ ...current, severity: event.target.value }))}>
                                    <MenuItem value="normal">Normal</MenuItem>
                                    <MenuItem value="warning">Warning</MenuItem>
                                    <MenuItem value="critical">Critical</MenuItem>
                                </TextField>
                                <TextField multiline minRows={4} label="Message" value={securityForm.message} onChange={(event) => setSecurityForm((current) => ({ ...current, message: event.target.value }))} />
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <Button variant="contained" onClick={createSecurity}>Create Security Alert</Button>
                                </Box>
                            </Stack>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} lg={7}>
                        <Paper sx={{ p: 3, borderRadius: 2.5, bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 2 }}>Security alerts</Typography>
                            <Stack spacing={1.5}>
                                {(payload?.security || []).length === 0 ? (
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>No security alerts recorded.</Typography>
                                ) : (payload?.security || []).map((entry) => (
                                    <Box key={entry.id} sx={{ p: 2, borderRadius: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
                                        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
                                            <Box>
                                                <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <NotificationsIcon sx={{ fontSize: 18 }} /> {entry.title}
                                                </Typography>
                                                <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                                                    {entry.severity} · {entry.status} · {formatDateTime(entry.createdAt)}
                                                </Typography>
                                                {entry.message ? <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>{entry.message}</Typography> : null}
                                            </Box>
                                            <Stack direction="row" spacing={1}>
                                                <Button size="small" onClick={() => toggleSecurity(entry.id)}>{entry.isOpen ? 'Resolve' : 'Reopen'}</Button>
                                                <Button size="small" color="error" onClick={() => deleteSecurity(entry.id)}>Delete</Button>
                                            </Stack>
                                        </Stack>
                                    </Box>
                                ))}
                            </Stack>
                        </Paper>
                    </Grid>
                </Grid>
            ) : null}
        </Box>
    );
};

export default AdminExtensions;
