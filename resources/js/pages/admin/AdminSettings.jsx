import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
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
    Launch as LaunchIcon,
    Save as SaveIcon,
    SettingsSuggest as SettingsSuggestIcon,
    Tune as TuneIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import { useAppSettings } from '../../context/AppSettingsContext';

const fallbackTabOrder = [
    'branding',
    'panel',
    'feature_toggles',
    'crash_policy',
    'connector_runtime',
    'connector_security',
    'rewards_economy',
    'auth_providers',
];

const stateVisuals = {
    active: { label: 'Active Now', color: '#36d399', background: 'rgba(54, 211, 153, 0.12)' },
    stored: { label: 'Stored Only', color: '#ffbf5f', background: 'rgba(255, 191, 95, 0.12)' },
    external: { label: 'Managed Elsewhere', color: '#8bb2ff', background: 'rgba(139, 178, 255, 0.14)' },
};

const multiLineFields = new Set(['connectorApiTrustedProxies']);

const AdminSettings = () => {
    const navigate = useNavigate();
    const { setSettings } = useAppSettings();
    const [payload, setPayload] = useState(null);
    const [form, setForm] = useState({});
    const [activeTab, setActiveTab] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await client.get('/v1/admin/settings');
                setPayload(data);
                setForm(data.settings || {});
            } catch (error) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load panel settings.' });
            }
        };

        load();
    }, []);

    const tabs = useMemo(() => {
        if (Array.isArray(payload?.tabs) && payload.tabs.length > 0) {
            return payload.tabs;
        }

        return fallbackTabOrder.map((key) => ({
            key,
            title: payload?.sections?.[key]?.title || key,
            state: payload?.sections?.[key]?.state || 'stored',
            stateCounts: payload?.sections?.[key]?.stateCounts || {},
        }));
    }, [payload]);

    useEffect(() => {
        if (!tabs.length) {
            return;
        }

        if (!activeTab || !tabs.some((tab) => tab.key === activeTab)) {
            setActiveTab(tabs[0].key);
        }
    }, [activeTab, tabs]);

    const stats = useMemo(() => {
        const sections = Object.values(payload?.sections || {});

        return {
            active: sections.filter((section) => section.state === 'active').length,
            stored: sections.filter((section) => section.state === 'stored').length,
            external: sections.filter((section) => section.state === 'external').length,
        };
    }, [payload]);

    const currentSection = activeTab ? payload?.sections?.[activeTab] : null;

    const updateField = (key, value) => {
        setForm((current) => ({
            ...current,
            [key]: value,
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const { data } = await client.put('/v1/admin/settings', form);
            setPayload(data.data);
            setForm(data.data.settings || {});
            setSettings((current) => ({
                ...current,
                brandName: data.data.settings?.brandName || current.brandName,
                faviconUrl: data.data.settings?.faviconUrl || current.faviconUrl,
            }));
            setMessage({ type: 'success', text: data.message });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save panel settings.' });
        } finally {
            setSaving(false);
        }
    };

    const renderField = (field) => {
        const value = form[field.key];
        const stateMeta = stateVisuals[field.state] || stateVisuals.stored;
        const helperText = field.state === 'stored'
            ? `${field.help} This remains persisted for compatibility, but RA-panel does not enforce the full CPanel behavior yet.`
            : field.help;

        return (
            <Paper
                key={field.key}
                elevation={0}
                sx={{
                    p: 2.25,
                    borderRadius: 2.5,
                    bgcolor: 'rgba(255,255,255,0.02)',
                    border: '1px solid rgba(255,255,255,0.05)',
                    height: '100%',
                    display: 'flex',
                    flexDirection: 'column',
                }}
            >
                <Stack direction="row" justifyContent="space-between" alignItems="flex-start" spacing={2} sx={{ mb: 2 }}>
                    <Box sx={{ pr: 1 }}>
                        <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 0.75 }}>
                            {field.label}
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', lineHeight: 1.7 }}>
                            {helperText}
                        </Typography>
                    </Box>
                    <Chip
                        label={stateMeta.label}
                        size="small"
                        sx={{ bgcolor: stateMeta.background, color: stateMeta.color, fontWeight: 700 }}
                    />
                </Stack>

                {field.type === 'boolean' ? (
                    <Box sx={{ mt: 'auto' }}>
                        <Switch
                            checked={Boolean(value)}
                            onChange={(event) => updateField(field.key, event.target.checked)}
                        />
                        <Typography variant="body2" sx={{ mt: 0.5, color: 'text.secondary', fontWeight: 600 }}>
                            {Boolean(value) ? 'Enabled' : 'Disabled'}
                        </Typography>
                    </Box>
                ) : (
                    <TextField
                        value={value ?? ''}
                        onChange={(event) => updateField(field.key, event.target.value)}
                        type={field.type === 'integer' ? 'number' : 'text'}
                        select={field.type === 'select'}
                        fullWidth
                        multiline={field.type !== 'select' && multiLineFields.has(field.key)}
                        minRows={field.type !== 'select' && multiLineFields.has(field.key) ? 3 : undefined}
                        InputLabelProps={field.type === 'integer' ? { shrink: true } : undefined}
                        inputProps={field.type === 'integer' ? { min: field.min, max: field.max } : undefined}
                        sx={{
                            mt: 'auto',
                            '& .MuiOutlinedInput-root': {
                                alignItems: multiLineFields.has(field.key) ? 'flex-start' : 'center',
                            },
                        }}
                    >
                        {(field.options || []).map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                )}
            </Paper>
        );
    };

    return (
        <Box>
            <Box
                sx={{
                    mb: 4,
                    p: { xs: 3, md: 4 },
                    borderRadius: 3,
                    border: '1px solid rgba(255,255,255,0.06)',
                    background: 'linear-gradient(135deg, rgba(24,30,52,0.96) 0%, rgba(14,19,34,0.96) 100%)',
                    overflow: 'hidden',
                    position: 'relative',
                }}
            >
                <Box
                    sx={{
                        position: 'absolute',
                        inset: 0,
                        background: 'radial-gradient(circle at top right, rgba(54,211,153,0.14), transparent 34%)',
                        pointerEvents: 'none',
                    }}
                />
                <Grid container spacing={3} sx={{ position: 'relative', zIndex: 1 }}>
                    <Grid item xs={12} md={7}>
                        <Chip
                            icon={<SettingsSuggestIcon sx={{ fontSize: 16 }} />}
                            label="System Control"
                            size="small"
                            sx={{ mb: 2, bgcolor: 'rgba(54,211,153,0.12)', color: '#7ce7bf', fontWeight: 700 }}
                        />
                        <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1, mb: 1.5 }}>
                            Panel settings with clear runtime boundaries
                        </Typography>
                        <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 680, lineHeight: 1.8 }}>
                            This follows the fuller CPanel settings surface, but RA-panel only marks settings active when they already reach a real runtime or connector-facing output. The rest stay stored for compatibility and future rollout.
                        </Typography>
                    </Grid>
                    <Grid item xs={12} md={5}>
                        <Grid container spacing={1.5}>
                            {[
                                { label: 'Active', value: stats.active },
                                { label: 'Stored', value: stats.stored },
                                { label: 'Elsewhere', value: stats.external },
                            ].map((item) => (
                                <Grid item xs={4} key={item.label}>
                                    <Paper
                                        elevation={0}
                                        sx={{
                                            p: 2,
                                            height: '100%',
                                            bgcolor: 'rgba(255,255,255,0.03)',
                                            border: '1px solid rgba(255,255,255,0.05)',
                                            borderRadius: 2.5,
                                        }}
                                    >
                                        <Typography variant="caption" sx={{ color: '#8d98a8', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 700 }}>
                                            {item.label}
                                        </Typography>
                                        <Typography variant="h4" sx={{ mt: 1, fontWeight: 800 }}>
                                            {item.value}
                                        </Typography>
                                    </Paper>
                                </Grid>
                            ))}
                        </Grid>
                    </Grid>
                </Grid>
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <Paper
                elevation={0}
                sx={{
                    mb: 3,
                    borderRadius: 3,
                    bgcolor: 'background.paper',
                    border: '1px solid rgba(255,255,255,0.05)',
                    overflow: 'hidden',
                }}
            >
                <Tabs
                    value={activeTab}
                    onChange={(_, value) => setActiveTab(value)}
                    variant="scrollable"
                    scrollButtons="auto"
                    sx={{
                        px: 1,
                        '& .MuiTab-root': {
                            alignItems: 'flex-start',
                            textTransform: 'none',
                            minHeight: 84,
                            minWidth: 180,
                            py: 2,
                        },
                    }}
                >
                    {tabs.map((tab) => {
                        const visual = stateVisuals[tab.state] || stateVisuals.stored;

                        return (
                            <Tab
                                key={tab.key}
                                value={tab.key}
                                label={(
                                    <Stack spacing={0.75} alignItems="flex-start">
                                        <Typography variant="subtitle2" sx={{ fontWeight: 800, color: 'text.primary' }}>
                                            {tab.title}
                                        </Typography>
                                        <Stack direction="row" spacing={0.75} alignItems="center" sx={{ flexWrap: 'wrap' }}>
                                            <Chip
                                                label={visual.label}
                                                size="small"
                                                sx={{ bgcolor: visual.background, color: visual.color, fontWeight: 700 }}
                                            />
                                            {tab.stateCounts?.active > 0 && (
                                                <Typography variant="caption" sx={{ color: '#7ce7bf', fontWeight: 700 }}>
                                                    {tab.stateCounts.active} active
                                                </Typography>
                                            )}
                                            {tab.stateCounts?.stored > 0 && (
                                                <Typography variant="caption" sx={{ color: '#ffbf5f', fontWeight: 700 }}>
                                                    {tab.stateCounts.stored} stored
                                                </Typography>
                                            )}
                                        </Stack>
                                    </Stack>
                                )}
                            />
                        );
                    })}
                </Tabs>
            </Paper>

            {currentSection && (
                <Paper
                    elevation={0}
                    sx={{
                        p: { xs: 3, md: 3.5 },
                        borderRadius: 3,
                        bgcolor: 'background.paper',
                        border: '1px solid rgba(255,255,255,0.05)',
                    }}
                >
                    <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" alignItems={{ xs: 'flex-start', md: 'center' }} spacing={2} sx={{ mb: 3 }}>
                        <Box>
                            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1.25, flexWrap: 'wrap' }}>
                                <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                    {currentSection.title}
                                </Typography>
                                <Chip
                                    label={(stateVisuals[currentSection.state] || stateVisuals.stored).label}
                                    size="small"
                                    sx={{
                                        bgcolor: (stateVisuals[currentSection.state] || stateVisuals.stored).background,
                                        color: (stateVisuals[currentSection.state] || stateVisuals.stored).color,
                                        fontWeight: 700,
                                    }}
                                />
                            </Stack>
                            <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 760, lineHeight: 1.8 }}>
                                {currentSection.description}
                            </Typography>
                        </Box>
                        <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                            {currentSection.stateCounts?.active > 0 && (
                                <Chip label={`${currentSection.stateCounts.active} Active`} size="small" sx={{ bgcolor: 'rgba(54, 211, 153, 0.12)', color: '#36d399', fontWeight: 700 }} />
                            )}
                            {currentSection.stateCounts?.stored > 0 && (
                                <Chip label={`${currentSection.stateCounts.stored} Stored`} size="small" sx={{ bgcolor: 'rgba(255, 191, 95, 0.12)', color: '#ffbf5f', fontWeight: 700 }} />
                            )}
                            {currentSection.stateCounts?.external > 0 && (
                                <Chip label={`${currentSection.stateCounts.external} Elsewhere`} size="small" sx={{ bgcolor: 'rgba(139, 178, 255, 0.14)', color: '#8bb2ff', fontWeight: 700 }} />
                            )}
                        </Stack>
                    </Stack>

                    {activeTab === 'auth_providers' ? (
                        <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" alignItems={{ xs: 'flex-start', md: 'center' }} spacing={2}>
                            <Box>
                                <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 740, lineHeight: 1.8 }}>
                                    {currentSection.cta?.help}
                                </Typography>
                            </Box>
                            <Button
                                variant="outlined"
                                endIcon={<LaunchIcon />}
                                onClick={() => navigate(currentSection.cta?.path || '/admin/auth-providers')}
                                sx={{ borderColor: 'rgba(255,255,255,0.12)' }}
                            >
                                {currentSection.cta?.label || 'Open'}
                            </Button>
                        </Stack>
                    ) : (
                        <Grid container spacing={2}>
                            {Object.values(currentSection.fields || {}).map((field) => (
                                <Grid
                                    item
                                    xs={12}
                                    md={field.type === 'boolean' ? 6 : field.type === 'select' ? 6 : 4}
                                    key={field.key}
                                >
                                    {renderField(field)}
                                </Grid>
                            ))}
                        </Grid>
                    )}
                </Paper>
            )}

            <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end' }}>
                <Button
                    variant="contained"
                    size="large"
                    startIcon={<SaveIcon />}
                    endIcon={<TuneIcon />}
                    onClick={handleSave}
                    disabled={saving || !payload}
                >
                    Save Settings
                </Button>
            </Box>
        </Box>
    );
};

export default AdminSettings;
