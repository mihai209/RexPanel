import React, { useEffect, useState } from 'react';
import {
    Alert,
    Avatar,
    Box,
    Button,
    Chip,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    InputAdornment,
    Pagination,
    Paper,
    Skeleton,
    Snackbar,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
    Tooltip,
    Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    GitHub as GitHubIcon,
    Google as GoogleIcon,
    KeyOff as Disable2FaIcon,
    People as UsersIcon,
    Refresh as ResetQuotaIcon,
    Reddit as RedditIcon,
    Search as SearchIcon,
    Shield as ShieldIcon,
    ShieldOutlined as ShieldOutlinedIcon,
    LinkOff as UnlinkIcon,
    Forum as DiscordIcon,
} from '@mui/icons-material';
import { useNavigate, useSearchParams } from 'react-router-dom';
import client from '../../api/client';
import { useAuth } from '../../context/AuthContext';

const providerMeta = {
    google: { icon: GoogleIcon, color: '#DB4437', label: 'Google' },
    github: { icon: GitHubIcon, color: '#f5f5f5', label: 'GitHub' },
    reddit: { icon: RedditIcon, color: '#FF4500', label: 'Reddit' },
    discord: { icon: DiscordIcon, color: '#5865F2', label: 'Discord' },
};

const ProviderBadge = ({ provider, unlinking, onUnlink }) => {
    const meta = providerMeta[provider.provider] || { icon: ShieldOutlinedIcon, color: '#94a3b8', label: provider.provider };
    const Icon = meta.icon;

    return (
        <Chip
            size="small"
            icon={<Icon sx={{ fontSize: '14px !important', color: `${meta.color} !important` }} />}
            label={meta.label}
            onDelete={onUnlink}
            deleteIcon={unlinking ? <CircularProgress size={12} color="inherit" /> : <UnlinkIcon />}
            sx={{
                bgcolor: 'rgba(255,255,255,0.04)',
                border: '1px solid rgba(255,255,255,0.08)',
                '& .MuiChip-label': { px: 1, fontWeight: 600 },
            }}
        />
    );
};

const AdminUsers = () => {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const { user: currentUser } = useAuth();
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const initialSearch = searchParams.get('search') || '';
    const initialPage = Math.max(parseInt(searchParams.get('page') || '1', 10) || 1, 1);
    const [searchInput, setSearchInput] = useState(initialSearch);
    const [search, setSearch] = useState(initialSearch);
    const [page, setPage] = useState(initialPage);
    const [lastPage, setLastPage] = useState(1);
    const [deleteUser, setDeleteUser] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [busyKey, setBusyKey] = useState('');
    const [snackbar, setSnackbar] = useState({ open: false, severity: 'success', message: '' });

    const showSnackbar = (severity, message) => {
        setSnackbar({ open: true, severity, message });
    };

    const syncUser = (nextUser) => {
        setUsers((current) => current.map((entry) => (entry.id === nextUser.id ? nextUser : entry)));
    };

    const fetchUsers = async (requestedPage = page, requestedSearch = search) => {
        setLoading(true);

        try {
            const response = await client.get('/v1/admin/users', {
                params: {
                    page: requestedPage,
                    search: requestedSearch || undefined,
                },
            });

            setUsers(response.data.data || []);
            setLastPage(response.data.last_page || 1);
        } catch (error) {
            showSnackbar('error', error.response?.data?.message || 'Failed to fetch users.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const nextParams = {};
        const trimmedSearch = search.trim();

        if (trimmedSearch) {
            nextParams.search = trimmedSearch;
        }

        if (page > 1) {
            nextParams.page = String(page);
        }

        setSearchParams(nextParams, { replace: true });
        fetchUsers(page, trimmedSearch);
    }, [page, search, setSearchParams]);

    useEffect(() => {
        const trimmedSearch = searchInput.trim();
        const timeoutId = window.setTimeout(() => {
            setPage(1);
            setSearch(trimmedSearch);
        }, 250);

        return () => window.clearTimeout(timeoutId);
    }, [searchInput]);

    const clearSearch = () => {
        setSearchInput('');
        setSearch('');
        setPage(1);
    };

    const handleDelete = async () => {
        if (!deleteUser) {
            return;
        }

        setDeleting(true);

        try {
            await client.delete(`/v1/admin/users/${deleteUser.id}`);
            setUsers((current) => current.filter((entry) => entry.id !== deleteUser.id));
            setDeleteUser(null);
            showSnackbar('success', 'User deleted successfully.');
        } catch (error) {
            showSnackbar('error', error.response?.data?.message || 'Failed to delete user.');
        } finally {
            setDeleting(false);
        }
    };

    const withBusyAction = async (key, callback) => {
        setBusyKey(key);
        try {
            await callback();
        } catch (error) {
            showSnackbar('error', error.response?.data?.message || 'Action failed.');
        } finally {
            setBusyKey('');
        }
    };

    const handleQuotaReset = async (user) => withBusyAction(`quota-reset-${user.id}`, async () => {
        const response = await client.post(`/v1/admin/users/${user.id}/ai-quota/reset`);
        syncUser(response.data.user);
        showSnackbar('success', response.data.message || 'AI quota reset.');
    });

    const handleDisableTwoFactor = async (user) => withBusyAction(`disable-2fa-${user.id}`, async () => {
        const response = await client.post(`/v1/admin/users/${user.id}/disable-2fa`);
        syncUser(response.data.user);
        showSnackbar('success', response.data.message || 'Two-factor authentication disabled.');
    });

    const handleUnlink = async (user, provider) => withBusyAction(`unlink-${user.id}-${provider}`, async () => {
        const response = await client.post(`/v1/admin/users/${user.id}/unlink/${provider}`);
        syncUser(response.data.user);
        showSnackbar('success', response.data.message || 'Linked account removed.');
    });

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4, gap: 2, flexWrap: 'wrap' }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                        <UsersIcon /> Users
                    </Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Search, review account security state, manage AI quota, and handle linked providers.
                    </Typography>
                </Box>
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/admin/users/create')}>
                    Create User
                </Button>
            </Box>

            <Paper sx={{ p: 2.5, mb: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                    <TextField
                        fullWidth
                        size="small"
                        placeholder="Search by username or email"
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                clearSearch();
                            }
                        }}
                        InputProps={{
                            startAdornment: (
                                <InputAdornment position="start">
                                    <SearchIcon sx={{ color: 'text.secondary', fontSize: 20 }} />
                                </InputAdornment>
                            ),
                        }}
                        sx={{ '& .MuiOutlinedInput-root': { bgcolor: 'rgba(0,0,0,0.2)' } }}
                    />
                    <Button variant="outlined" onClick={clearSearch} sx={{ minWidth: 120 }} disabled={!searchInput && !search}>
                        Clear
                    </Button>
                </Stack>
            </Paper>

            <TableContainer component={Paper} elevation={0} sx={{ borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                <Table>
                    <TableHead>
                        <TableRow sx={{ '& th': { fontWeight: 700, color: 'text.secondary', borderBottom: '1px solid rgba(255,255,255,0.06)' } }}>
                            <TableCell>User</TableCell>
                            <TableCell>Email</TableCell>
                            <TableCell>State</TableCell>
                            <TableCell>Linked</TableCell>
                            <TableCell>AI Quota</TableCell>
                            <TableCell>Created</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {loading ? (
                            [...Array(6)].map((_, index) => (
                                <TableRow key={index}>
                                    <TableCell><Skeleton variant="text" width={180} /></TableCell>
                                    <TableCell><Skeleton variant="text" width={180} /></TableCell>
                                    <TableCell><Skeleton variant="rounded" width={130} height={26} /></TableCell>
                                    <TableCell><Skeleton variant="rounded" width={120} height={26} /></TableCell>
                                    <TableCell><Skeleton variant="text" width={120} /></TableCell>
                                    <TableCell><Skeleton variant="text" width={110} /></TableCell>
                                    <TableCell align="right"><Skeleton variant="rounded" width={160} height={34} sx={{ ml: 'auto' }} /></TableCell>
                                </TableRow>
                            ))
                        ) : users.length > 0 ? (
                            users.map((user) => (
                                <TableRow key={user.id} sx={{ '& td': { borderBottom: '1px solid rgba(255,255,255,0.05)', verticalAlign: 'top' } }}>
                                    <TableCell>
                                        <Stack direction="row" spacing={2} alignItems="center">
                                            <Avatar src={user.avatar_url} sx={{ width: 42, height: 42 }}>
                                                {user.username?.[0]?.toUpperCase()}
                                            </Avatar>
                                            <Box>
                                                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                                                    {user.username}
                                                </Typography>
                                                <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>
                                                    #{user.id} {user.full_name ? `• ${user.full_name}` : ''}
                                                </Typography>
                                                <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>
                                                    {user.servers_count} server{user.servers_count === 1 ? '' : 's'}
                                                </Typography>
                                            </Box>
                                        </Stack>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="body2">{user.email}</Typography>
                                        {user.avatar_provider && (
                                            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                                                Avatar: {user.avatar_provider}
                                            </Typography>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
                                            <Chip
                                                size="small"
                                                label={user.is_admin ? 'Admin' : 'User'}
                                                color={user.is_admin ? 'primary' : 'default'}
                                                variant={user.is_admin ? 'filled' : 'outlined'}
                                            />
                                            {user.is_suspended && <Chip size="small" label="Suspended" color="warning" variant="outlined" />}
                                            <Chip
                                                size="small"
                                                icon={user.two_factor_enabled ? <ShieldIcon sx={{ fontSize: '14px !important' }} /> : <ShieldOutlinedIcon sx={{ fontSize: '14px !important' }} />}
                                                label={user.two_factor_enabled ? '2FA On' : '2FA Off'}
                                                color={user.two_factor_enabled ? 'info' : 'default'}
                                                variant="outlined"
                                            />
                                        </Stack>
                                    </TableCell>
                                    <TableCell>
                                        <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap">
                                            {user.linked_accounts.length > 0 ? user.linked_accounts.map((provider) => (
                                                <Tooltip
                                                    key={`${user.id}-${provider.provider}`}
                                                    title={provider.provider_email || provider.provider_username || provider.provider}
                                                >
                                                    <span>
                                                        <ProviderBadge
                                                            provider={provider}
                                                            unlinking={busyKey === `unlink-${user.id}-${provider.provider}`}
                                                            onUnlink={() => handleUnlink(user, provider.provider)}
                                                        />
                                                    </span>
                                                </Tooltip>
                                            )) : (
                                                <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                                                    No linked providers
                                                </Typography>
                                            )}
                                        </Stack>
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                            {user.ai_quota_remaining} left
                                        </Typography>
                                        <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>
                                            {user.ai_quota_used_today} used / {user.ai_quota_limit} daily
                                        </Typography>
                                        {user.ai_quota_override !== null && (
                                            <Chip size="small" label={`Override ${user.ai_quota_override}`} variant="outlined" sx={{ mt: 0.75 }} />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                                            {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'Unknown'}
                                        </Typography>
                                    </TableCell>
                                    <TableCell align="right">
                                        <Stack direction="row" spacing={1} justifyContent="flex-end" useFlexGap flexWrap="wrap">
                                            <Tooltip title="Edit user">
                                                <IconButton size="small" onClick={() => navigate(`/admin/users/${user.id}`)}>
                                                    <EditIcon fontSize="small" />
                                                </IconButton>
                                            </Tooltip>
                                            <Tooltip title="Reset today's AI quota usage">
                                                <span>
                                                    <IconButton size="small" onClick={() => handleQuotaReset(user)} disabled={busyKey === `quota-reset-${user.id}`}>
                                                        {busyKey === `quota-reset-${user.id}` ? <CircularProgress size={16} /> : <ResetQuotaIcon fontSize="small" />}
                                                    </IconButton>
                                                </span>
                                            </Tooltip>
                                            {user.two_factor_enabled && (
                                                <Tooltip title="Disable 2FA">
                                                    <span>
                                                        <IconButton size="small" onClick={() => handleDisableTwoFactor(user)} disabled={busyKey === `disable-2fa-${user.id}`}>
                                                            {busyKey === `disable-2fa-${user.id}` ? <CircularProgress size={16} /> : <Disable2FaIcon fontSize="small" />}
                                                        </IconButton>
                                                    </span>
                                                </Tooltip>
                                            )}
                                            <Tooltip title={user.id === currentUser?.id ? 'You cannot delete yourself' : 'Delete user'}>
                                                <span>
                                                    <IconButton size="small" color="error" disabled={user.id === currentUser?.id} onClick={() => setDeleteUser(user)}>
                                                        <DeleteIcon fontSize="small" />
                                                    </IconButton>
                                                </span>
                                            </Tooltip>
                                        </Stack>
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={7} align="center" sx={{ py: 8 }}>
                                    <Typography sx={{ color: 'text.secondary' }}>No users found.</Typography>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            {lastPage > 1 && (
                <Box sx={{ mt: 3, display: 'flex', justifyContent: 'center' }}>
                    <Pagination count={lastPage} page={page} onChange={(_, nextPage) => setPage(nextPage)} color="primary" />
                </Box>
            )}

            <Dialog open={Boolean(deleteUser)} onClose={() => !deleting && setDeleteUser(null)} maxWidth="xs" fullWidth>
                <DialogTitle sx={{ fontWeight: 700 }}>Delete User?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Delete <strong>{deleteUser?.username}</strong>? Linked accounts are removed with the user and this cannot be undone.
                    </Typography>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setDeleteUser(null)} disabled={deleting}>Cancel</Button>
                    <Button onClick={handleDelete} color="error" variant="contained" disabled={deleting}>
                        {deleting ? <CircularProgress size={18} color="inherit" /> : 'Delete'}
                    </Button>
                </DialogActions>
            </Dialog>

            <Snackbar open={snackbar.open} autoHideDuration={4000} onClose={() => setSnackbar((current) => ({ ...current, open: false }))} anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}>
                <Alert severity={snackbar.severity} variant="filled" onClose={() => setSnackbar((current) => ({ ...current, open: false }))}>
                    {snackbar.message}
                </Alert>
            </Snackbar>
        </Box>
    );
};

export default AdminUsers;
