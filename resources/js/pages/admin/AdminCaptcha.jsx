import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    FormControlLabel,
    Paper,
    Stack,
    Switch,
    Typography,
} from '@mui/material';
import {
    Security as SecurityIcon,
    Save as SaveIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const AdminCaptcha = () => {
    const [enabled, setEnabled] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await client.get('/v1/admin/captcha');
                setEnabled(Boolean(data.enabled));
            } catch (error) {
                setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load captcha settings.' });
            } finally {
                setLoading(false);
            }
        };

        load();
    }, []);

    const save = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const { data } = await client.put('/v1/admin/captcha', { enabled });
            setEnabled(Boolean(data.settings?.enabled));
            setMessage({ type: 'success', text: data.message || 'Captcha settings updated.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save captcha settings.' });
        } finally {
            setSaving(false);
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
                    background: 'linear-gradient(135deg, rgba(27,31,45,0.98) 0%, rgba(14,19,30,0.98) 100%)',
                }}
            >
                <Chip
                    icon={<SecurityIcon sx={{ fontSize: 16 }} />}
                    label="Auth Protection"
                    size="small"
                    sx={{ mb: 2, bgcolor: 'rgba(139,178,255,0.14)', color: '#8bb2ff', fontWeight: 700 }}
                />
                <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1, mb: 1.5 }}>
                    Login and 2FA captcha gate
                </Typography>
                <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 720, lineHeight: 1.8 }}>
                    This ports the CPanel captcha behavior into RA-panel’s SPA auth flow. When enabled, password login and password-based 2FA completion require a server-issued captcha challenge.
                </Typography>
            </Box>

            {message.text ? (
                <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>
                    {message.text}
                </Alert>
            ) : null}

            <Paper elevation={0} sx={{ p: 3, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'rgba(9,14,23,0.8)', maxWidth: 760 }}>
                <Stack spacing={3}>
                    <Stack direction="row" justifyContent="space-between" alignItems="center" spacing={2}>
                        <Box>
                            <Typography variant="h6" sx={{ fontWeight: 800, mb: 0.5 }}>
                                Captcha status
                            </Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Mirrors the old `captchastatus` switch from CPanel, but backed by API challenges instead of server-rendered session HTML.
                            </Typography>
                        </Box>
                        <Chip
                            label={enabled ? 'Enabled' : 'Disabled'}
                            sx={{
                                bgcolor: enabled ? 'rgba(54,211,153,0.12)' : 'rgba(255,255,255,0.05)',
                                color: enabled ? '#7ce7bf' : '#98a4b3',
                                fontWeight: 800,
                            }}
                        />
                    </Stack>

                    <FormControlLabel
                        control={<Switch checked={enabled} onChange={(event) => setEnabled(event.target.checked)} disabled={loading || saving} />}
                        label="Require captcha on login and password 2FA"
                    />

                    <Alert severity="info">
                        OAuth login stays unchanged. This captcha only applies to password sign-in and password 2FA verification, matching the source flow from CPanel.
                    </Alert>

                    <Box>
                        <Button variant="contained" startIcon={<SaveIcon />} onClick={save} disabled={loading || saving}>
                            {saving ? 'Saving...' : 'Save Captcha Settings'}
                        </Button>
                    </Box>
                </Stack>
            </Paper>
        </Box>
    );
};

export default AdminCaptcha;
