import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import client from '../api/client';

const AuthContext = createContext();

const getHashParams = () => {
    const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    return {
        token: params.get('oauth_token'),
        status: params.get('oauth_status'),
        provider: params.get('provider'),
        error: params.get('oauth_error'),
        twoFactorToken: params.get('two_factor_token'),
    };
};

export const AuthProvider = ({ children }) => {
    const hashAuth = getHashParams();
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(hashAuth.token || localStorage.getItem('ra_token'));
    const [loading, setLoading] = useState(true);
    const [suspensionMessage, setSuspensionMessage] = useState('');

    useEffect(() => {
        if (hashAuth.token) {
            localStorage.setItem('ra_token', hashAuth.token);
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        }
    }, []);

    useEffect(() => {
        if (token) {
            fetchUser();
        } else {
            setLoading(false);
        }
    }, [token]);

    useEffect(() => {
        const handleSuspended = (event) => {
            setSuspensionMessage(event.detail?.message || 'Your account has been suspended by an administrator.');
            setToken(null);
            setUser(null);
            setLoading(false);
        };

        window.addEventListener('ra:account-suspended', handleSuspended);

        return () => {
            window.removeEventListener('ra:account-suspended', handleSuspended);
        };
    }, []);

    useEffect(() => {
        if (!token) {
            return undefined;
        }

        const websocketConfig = window.RA_PANEL_BOOTSTRAP?.uiWebsocket || {};
        const scheme = websocketConfig.scheme || (window.location.protocol === 'https:' ? 'wss' : 'ws');
        const host = websocketConfig.host || window.location.hostname;
        const port = websocketConfig.port || 8082;
        let socket = null;
        let reconnectTimeout = null;
        let closing = false;

        const dispatchNotificationEvent = (payload) => {
            window.dispatchEvent(new CustomEvent('ra:notification-event', { detail: payload }));
        };

        const updateUnread = (value) => {
            setUser((current) => {
                if (!current) {
                    return current;
                }

                return {
                    ...current,
                    notification_unread_count: Number(value ?? 0),
                    notificationUnreadCount: Number(value ?? 0),
                };
            });
        };

        const connect = () => {
            socket = new WebSocket(`${scheme}://${host}:${port}`);

            socket.addEventListener('open', () => {
                socket.send(JSON.stringify({ type: 'auth', token }));
            });

            socket.addEventListener('message', (event) => {
                try {
                    const payload = JSON.parse(event.data);

                    if (payload.type === 'auth_success') {
                        updateUnread(payload.unreadCount ?? 0);
                        dispatchNotificationEvent(payload);
                        return;
                    }

                    if (payload.type === 'notification:unread_count') {
                        updateUnread(payload.unreadCount ?? payload.unread_count ?? 0);
                    }

                    dispatchNotificationEvent(payload);
                } catch {
                    // Ignore malformed websocket payloads.
                }
            });

            socket.addEventListener('close', () => {
                if (!closing) {
                    reconnectTimeout = window.setTimeout(connect, 5000);
                }
            });
        };

        connect();

        return () => {
            closing = true;
            if (reconnectTimeout) {
                window.clearTimeout(reconnectTimeout);
            }
            if (socket && socket.readyState < 2) {
                socket.close();
            }
        };
    }, [token]);

    const fetchUser = useCallback(async ({ silent = false } = {}) => {
        try {
            const response = await client.get('/v1/auth/me');
            setUser(response.data);
            if (response.data?.is_suspended || response.data?.isSuspended) {
                localStorage.removeItem('ra_token');
                setToken(null);
                setUser(null);
                setSuspensionMessage('Your account has been suspended by an administrator.');
            }
        } catch (error) {
            localStorage.removeItem('ra_token');
            setToken(null);
            if ((error.response?.status === 423 || error.response?.data?.code === 'ACCOUNT_SUSPENDED') && !silent) {
                setSuspensionMessage(error.response?.data?.message || 'Your account has been suspended by an administrator.');
            }
        } finally {
            if (!silent) {
                setLoading(false);
            }
        }
    }, []);

    const acceptAuthentication = (payload) => {
        const newToken = payload?.token;
        const newUser = payload?.user;

        if (!newToken || !newUser) {
            return { success: false, message: 'Authentication response was incomplete.' };
        }

        localStorage.setItem('ra_token', newToken);
        setToken(newToken);
        setUser(newUser);
        setSuspensionMessage('');

        return { success: true };
    };

    const updateNotificationUnreadCount = useCallback((count) => {
        setUser((current) => {
            if (!current) {
                return current;
            }

            return {
                ...current,
                notification_unread_count: Number(count ?? 0),
                notificationUnreadCount: Number(count ?? 0),
            };
        });
    }, []);

    const login = async (loginValue, password, captcha = {}) => {
        try {
            const response = await client.post('/login', {
                login: loginValue,
                email: loginValue,
                username: loginValue,
                password,
                device_name: 'webapp',
                captcha: captcha.value || '',
                captcha_token: captcha.token || '',
            });

            if (response.data?.two_factor_required) {
                return {
                    success: false,
                    twoFactorRequired: true,
                    twoFactorToken: response.data.two_factor_token,
                    message: response.data.message || 'Two-factor authentication is required.',
                };
            }

            return acceptAuthentication(response.data);
        } catch (error) {
            return { 
                success: false, 
                message: error.response?.data?.message || 'Login failed' 
            };
        }
    };

    const completeTwoFactorLogin = async (challengeToken, code, captcha = {}) => {
        try {
            const response = await client.post('/v1/auth/login/2fa', {
                two_factor_token: challengeToken,
                code,
                captcha: captcha.value || '',
                captcha_token: captcha.token || '',
            });

            return acceptAuthentication(response.data);
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Two-factor verification failed.',
            };
        }
    };

    const logout = async () => {
        try {
            await client.post('/v1/auth/logout');
        } finally {
            localStorage.removeItem('ra_token');
            setToken(null);
            setUser(null);
        }
    };

    return (
        <AuthContext.Provider value={{ user, setUser, token, loading, login, completeTwoFactorLogin, logout, isAuthenticated: !!token, suspensionMessage, setSuspensionMessage, hashAuth, fetchUser, updateNotificationUnreadCount }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);
