import React, { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    Pagination,
    Paper,
    Skeleton,
    Stack,
    Typography,
} from '@mui/material';
import {
    NotificationsActive as NotificationsIcon,
    DoneAll as DoneAllIcon,
} from '@mui/icons-material';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';

const AccountNotifications = () => {
    const { updateNotificationUnreadCount } = useAuth();
    const [notifications, setNotifications] = useState([]);
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const fetchNotifications = useCallback(async (page = 1) => {
        setLoading(true);
        setError('');

        try {
            const response = await client.get(`/v1/account/notifications?per_page=12&page=${page}`);
            setNotifications(response.data.notifications ?? []);
            setPagination(response.data.pagination ?? { current_page: page, last_page: 1, total: 0 });
            updateNotificationUnreadCount(response.data.unreadCount ?? response.data.unread_count ?? 0);
        } catch {
            setError('Failed to load notifications.');
        } finally {
            setLoading(false);
        }
    }, [updateNotificationUnreadCount]);

    useEffect(() => {
        fetchNotifications(1);

        const handler = (event) => {
            const payload = event.detail ?? {};

            if (payload.type === 'notification:new') {
                fetchNotifications(pagination.current_page || 1);
            }

            if (payload.type === 'notification:read') {
                setNotifications((previous) => previous.map((entry) => (
                    entry.id === payload.notificationId ? { ...entry, isRead: true, is_read: true } : entry
                )));
            }

            if (payload.type === 'notification:unread_count') {
                updateNotificationUnreadCount(payload.unreadCount ?? payload.unread_count ?? 0);
            }
        };

        window.addEventListener('ra:notification-event', handler);
        return () => window.removeEventListener('ra:notification-event', handler);
    }, [fetchNotifications, pagination.current_page, updateNotificationUnreadCount]);

    const markRead = async (id) => {
        try {
            const response = await client.post(`/v1/account/notifications/${id}/read`);
            updateNotificationUnreadCount(response.data.unreadCount ?? response.data.unread_count ?? 0);
            setNotifications((previous) => previous.map((entry) => (
                entry.id === id ? { ...entry, isRead: true, is_read: true } : entry
            )));
        } catch {
            setError('Failed to mark notification as read.');
        }
    };

    const markAllRead = async () => {
        try {
            await client.post('/v1/account/notifications/read-all');
            updateNotificationUnreadCount(0);
            setNotifications((previous) => previous.map((entry) => ({ ...entry, isRead: true, is_read: true })));
        } catch {
            setError('Failed to mark all notifications as read.');
        }
    };

    return (
        <Box>
            {error ? <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert> : null}

            <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, overflow: 'hidden' }}>
                <Box sx={{ px: 4, py: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 2, borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                    <Box>
                        <Typography variant="subtitle1" sx={{ fontWeight: 800, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                            <NotificationsIcon /> Notification History
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>
                            Persistent account alerts with live unread updates.
                        </Typography>
                    </Box>
                    <Button startIcon={<DoneAllIcon />} onClick={markAllRead}>
                        Mark all read
                    </Button>
                </Box>

                {loading ? (
                    <Stack spacing={2} sx={{ p: 4 }}>
                        {Array.from({ length: 5 }).map((_, index) => (
                            <Skeleton key={index} variant="rounded" height={92} sx={{ bgcolor: 'rgba(255,255,255,0.04)' }} />
                        ))}
                    </Stack>
                ) : notifications.length === 0 ? (
                    <Box sx={{ px: 4, py: 10, textAlign: 'center' }}>
                        <NotificationsIcon sx={{ fontSize: 48, color: 'rgba(255,255,255,0.12)', mb: 1 }} />
                        <Typography variant="body1" sx={{ color: 'text.secondary' }}>
                            No notifications yet.
                        </Typography>
                    </Box>
                ) : (
                    <Stack spacing={2} sx={{ p: 3 }}>
                        {notifications.map((notification) => {
                            const unread = !(notification.isRead ?? notification.is_read);

                            return (
                                <Paper
                                    key={notification.id}
                                    elevation={0}
                                    sx={{
                                        p: 2.5,
                                        bgcolor: unread ? 'rgba(54,211,153,0.06)' : 'rgba(255,255,255,0.02)',
                                        border: unread ? '1px solid rgba(54,211,153,0.22)' : '1px solid rgba(255,255,255,0.05)',
                                        borderRadius: 2,
                                    }}
                                >
                                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between">
                                        <Box sx={{ minWidth: 0 }}>
                                            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                                                <Typography variant="subtitle2" sx={{ fontWeight: 800, color: 'text.primary' }}>
                                                    {notification.title}
                                                </Typography>
                                                <Chip size="small" label={notification.severity} />
                                                {unread ? <Chip size="small" color="success" label="Unread" /> : null}
                                            </Stack>
                                            <Typography variant="body2" sx={{ color: 'text.secondary', whiteSpace: 'pre-wrap', mb: 1.25 }}>
                                                {notification.message}
                                            </Typography>
                                            <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.4)' }}>
                                                {new Date(notification.createdAt || notification.created_at).toLocaleString()}
                                            </Typography>
                                        </Box>

                                        <Stack direction={{ xs: 'row', md: 'column' }} spacing={1} justifyContent="center" alignItems={{ xs: 'flex-start', md: 'flex-end' }}>
                                            {notification.linkUrl || notification.link_url ? (
                                                <Button variant="outlined" href={notification.linkUrl || notification.link_url}>
                                                    Open link
                                                </Button>
                                            ) : null}
                                            {unread ? (
                                                <Button variant="contained" onClick={() => markRead(notification.id)}>
                                                    Mark read
                                                </Button>
                                            ) : null}
                                        </Stack>
                                    </Stack>
                                </Paper>
                            );
                        })}
                    </Stack>
                )}

                {pagination.last_page > 1 ? (
                    <Box sx={{ px: 4, py: 2.5, display: 'flex', justifyContent: 'flex-end', borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                        <Pagination
                            count={pagination.last_page}
                            page={pagination.current_page}
                            onChange={(_, page) => fetchNotifications(page)}
                            size="small"
                        />
                    </Box>
                ) : null}
            </Paper>
        </Box>
    );
};

export default AccountNotifications;
