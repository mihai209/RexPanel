import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Badge,
    Box,
    Button,
    CircularProgress,
    Divider,
    IconButton,
    List,
    ListItem,
    ListItemButton,
    ListItemText,
    Menu,
    Stack,
    Tooltip,
    Typography,
} from '@mui/material';
import {
    NotificationsOutlined as NotificationsIcon,
    NotificationsActive as NotificationsActiveIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import { useAuth } from '../context/AuthContext';

const formatTime = (value) => {
    if (!value) {
        return 'Just now';
    }

    return new Date(value).toLocaleString();
};

const NotificationBell = () => {
    const { user, updateNotificationUnreadCount } = useAuth();
    const navigate = useNavigate();
    const [anchorEl, setAnchorEl] = useState(null);
    const [loading, setLoading] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [hasLoaded, setHasLoaded] = useState(false);
    const mountedRef = useRef(true);

    const unreadCount = Number(user?.notificationUnreadCount ?? user?.notification_unread_count ?? 0);
    const open = Boolean(anchorEl);

    const loadRecent = useCallback(async () => {
        setLoading(true);

        try {
            const response = await client.get('/v1/account/notifications/recent?limit=8');
            if (!mountedRef.current) {
                return;
            }

            setNotifications(response.data.notifications ?? []);
            setHasLoaded(true);
            updateNotificationUnreadCount(response.data.unreadCount ?? response.data.unread_count ?? 0);
        } catch {
            if (mountedRef.current) {
                setNotifications([]);
            }
        } finally {
            if (mountedRef.current) {
                setLoading(false);
            }
        }
    }, [updateNotificationUnreadCount]);

    useEffect(() => {
        mountedRef.current = true;

        const handler = (event) => {
            const payload = event.detail ?? {};

            if (payload.type === 'notification:new' && payload.notification) {
                setNotifications((previous) => {
                    if (!hasLoaded && !open) {
                        return previous;
                    }
                    const next = [payload.notification, ...previous.filter((entry) => entry.id !== payload.notification.id)];
                    return next.slice(0, 8);
                });
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

        return () => {
            mountedRef.current = false;
            window.removeEventListener('ra:notification-event', handler);
        };
    }, [hasLoaded, open, updateNotificationUnreadCount]);

    useEffect(() => {
        if (!open || hasLoaded || loading) {
            return;
        }

        loadRecent();
    }, [open, hasLoaded, loading, loadRecent]);

    const handleMarkRead = async (notificationId) => {
        try {
            const response = await client.post(`/v1/account/notifications/${notificationId}/read`);
            const unread = response.data.unreadCount ?? response.data.unread_count ?? 0;
            updateNotificationUnreadCount(unread);
            setNotifications((previous) => previous.map((entry) => (
                entry.id === notificationId ? { ...entry, isRead: true, is_read: true } : entry
            )));
        } catch {
            // API remains authoritative. Keep local UI unchanged on error.
        }
    };

    const handleMarkAllRead = async () => {
        try {
            await client.post('/v1/account/notifications/read-all');
            updateNotificationUnreadCount(0);
            setNotifications((previous) => previous.map((entry) => ({ ...entry, isRead: true, is_read: true })));
        } catch {
            // No local fallback needed.
        }
    };

    const emptyState = useMemo(() => (
        <Box sx={{ px: 3, py: 5, textAlign: 'center' }}>
            <NotificationsIcon sx={{ fontSize: 40, color: 'rgba(255,255,255,0.18)', mb: 1 }} />
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                No notifications yet.
            </Typography>
        </Box>
    ), []);

    return (
        <>
            <Tooltip title="Notifications">
                <IconButton
                    onClick={(event) => {
                        setAnchorEl(event.currentTarget);
                    }}
                    sx={{ color: 'rgba(255,255,255,0.7)' }}
                >
                    <Badge badgeContent={unreadCount > 99 ? '99+' : unreadCount} color="error">
                        {unreadCount > 0 ? <NotificationsActiveIcon /> : <NotificationsIcon />}
                    </Badge>
                </IconButton>
            </Tooltip>

            <Menu
                anchorEl={anchorEl}
                open={open}
                onClose={() => setAnchorEl(null)}
                PaperProps={{
                    sx: {
                        width: 380,
                        maxWidth: 'calc(100vw - 24px)',
                        bgcolor: 'background.paper',
                        border: '1px solid rgba(255,255,255,0.08)',
                        mt: 1,
                        borderRadius: 2,
                    },
                }}
            >
                <Box sx={{ px: 2.5, py: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 2 }}>
                    <Box>
                        <Typography variant="subtitle2" sx={{ fontWeight: 800, color: 'text.primary' }}>
                            Notifications
                        </Typography>
                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                            {unreadCount} unread
                        </Typography>
                    </Box>
                    <Button size="small" onClick={handleMarkAllRead} disabled={unreadCount < 1}>
                        Mark all read
                    </Button>
                </Box>
                <Divider />

                {loading ? (
                    <Stack sx={{ px: 3, py: 5, alignItems: 'center' }} spacing={1}>
                        <CircularProgress size={24} />
                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                            Loading notifications...
                        </Typography>
                    </Stack>
                ) : notifications.length === 0 ? emptyState : (
                    <List sx={{ py: 0, maxHeight: 420, overflowY: 'auto' }}>
                        {notifications.map((notification) => (
                            <ListItem
                                key={notification.id}
                                disablePadding
                                secondaryAction={!(notification.isRead ?? notification.is_read) ? (
                                    <Button size="small" onClick={() => handleMarkRead(notification.id)}>
                                        Read
                                    </Button>
                                ) : null}
                            >
                                <ListItemButton
                                    onClick={() => {
                                        setAnchorEl(null);
                                        if (notification.linkUrl || notification.link_url) {
                                            navigate(notification.linkUrl || notification.link_url);
                                            return;
                                        }
                                        navigate('/account/notifications');
                                    }}
                                    sx={{
                                        py: 1.5,
                                        alignItems: 'flex-start',
                                        bgcolor: !(notification.isRead ?? notification.is_read) ? 'rgba(54,211,153,0.06)' : 'transparent',
                                        borderLeft: !(notification.isRead ?? notification.is_read) ? '2px solid #36d399' : '2px solid transparent',
                                    }}
                                >
                                    <ListItemText
                                        primary={notification.title}
                                        secondary={(
                                            <Stack spacing={0.5} sx={{ mt: 0.5 }}>
                                                <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', whiteSpace: 'pre-wrap' }}>
                                                    {notification.message}
                                                </Typography>
                                                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.35)' }}>
                                                    {formatTime(notification.createdAt || notification.created_at)}
                                                </Typography>
                                            </Stack>
                                        )}
                                        primaryTypographyProps={{
                                            fontSize: '0.9rem',
                                            fontWeight: !(notification.isRead ?? notification.is_read) ? 700 : 600,
                                            color: 'text.primary',
                                        }}
                                    />
                                </ListItemButton>
                            </ListItem>
                        ))}
                    </List>
                )}

                <Divider />
                <Box sx={{ px: 2, py: 1.5 }}>
                    <Button
                        fullWidth
                        variant="text"
                        onClick={() => {
                            setAnchorEl(null);
                            navigate('/account/notifications');
                        }}
                    >
                        View all notifications
                    </Button>
                </Box>
            </Menu>
        </>
    );
};

export default NotificationBell;
