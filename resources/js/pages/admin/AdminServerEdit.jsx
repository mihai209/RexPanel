import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Card,
    CardContent,
    Checkbox,
    Chip,
    CircularProgress,
    Divider,
    FormControlLabel,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Select,
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
    ArrowBack as BackIcon,
    DeleteForever as DeleteIcon,
    Hub as NetworkIcon,
    Layers as BuildIcon,
    PlayCircle as StartupIcon,
    Save as SaveIcon,
    Settings as AboutIcon,
    Storage as DatabaseIcon,
    FolderOpen as MountIcon,
} from '@mui/icons-material';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';
import { buildStartupPreview, formatAllocationLabel, normalizeNullableNumber } from './serverFormUtils';

const serverTabs = [
    { key: 'about', label: 'About', icon: <AboutIcon fontSize="small" /> },
    { key: 'build', label: 'Build Configuration', icon: <BuildIcon fontSize="small" /> },
    { key: 'startup', label: 'Startup', icon: <StartupIcon fontSize="small" /> },
    { key: 'databases', label: 'Databases', icon: <DatabaseIcon fontSize="small" /> },
    { key: 'network', label: 'Network', icon: <NetworkIcon fontSize="small" /> },
    { key: 'mounts', label: 'Mounts', icon: <MountIcon fontSize="small" /> },
    { key: 'delete', label: 'Delete', icon: <DeleteIcon fontSize="small" /> },
];

const emptyDbForm = {
    database: '',
    remote: '%',
    database_host_id: '',
};

const tabFromPath = (pathname, serverId) => {
    const prefix = `/admin/servers/${serverId}`;
    const suffix = pathname.startsWith(prefix) ? pathname.slice(prefix.length) : '';
    const segment = suffix.replace(/^\/+/, '').split('/')[0] || 'about';
    return serverTabs.some((tab) => tab.key === segment) ? segment : 'about';
};

const emptyNetworkForm = {
    allocation_id: '',
    notes: '',
};

const defaultForm = {
    name: '',
    description: '',
    external_id: '',
    user_id: '',
    node_id: '',
    allocation_id: '',
    package_id: '',
    image_id: '',
    cpu: 100,
    memory: 1024,
    disk: 5120,
    swap: 0,
    io: 500,
    threads: '',
    oom_disabled: false,
    database_limit: '',
    allocation_limit: '',
    backup_limit: '',
    docker_image: '',
    startup: '',
    variables: {},
    status: 'offline',
    start_on_completion: false,
};

const AdminServerEdit = () => {
    const { id } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const activeTab = tabFromPath(location.pathname, id);

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [dbSaving, setDbSaving] = useState(false);
    const [networkSaving, setNetworkSaving] = useState(false);
    const [mountSaving, setMountSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    const [users, setUsers] = useState([]);
    const [nodes, setNodes] = useState([]);
    const [packages, setPackages] = useState([]);
    const [images, setImages] = useState([]);
    const [nodeAllocations, setNodeAllocations] = useState([]);
    const [databaseHosts, setDatabaseHosts] = useState([]);
    const [selectedImage, setSelectedImage] = useState(null);
    const [serverMeta, setServerMeta] = useState(null);

    const [databaseState, setDatabaseState] = useState({
        databases: [],
        database_usage: { used: 0, limit: null, remaining: null, is_unlimited: true },
    });
    const [networkState, setNetworkState] = useState({
        allocations: [],
        available_allocations: [],
        primary_allocation: null,
    });
    const [dbForm, setDbForm] = useState(emptyDbForm);
    const [networkForm, setNetworkForm] = useState(emptyNetworkForm);

    const [assignedMounts, setAssignedMounts] = useState([]);
    const [availableMounts, setAvailableMounts] = useState([]);
    const [mountForm, setMountForm] = useState({ mount_id: '', read_only: false });

    const [formData, setFormData] = useState(defaultForm);
    const [allocationNotes, setAllocationNotes] = useState({});

    const shellCardSx = {
        border: '1px solid rgba(255,255,255,0.06)',
        borderRadius: 2,
        bgcolor: 'background.paper',
        boxShadow: 'none',
    };

    const currentAllocation = useMemo(
        () => nodeAllocations.find((allocation) => String(allocation.id) === String(formData.allocation_id))
            || networkState.primary_allocation
            || null,
        [nodeAllocations, networkState.primary_allocation, formData.allocation_id]
    );

    const startupPreview = useMemo(
        () => buildStartupPreview({
            startup: formData.startup,
            variables: formData.variables,
            memory: formData.memory,
            allocation: currentAllocation,
        }),
        [formData.startup, formData.variables, formData.memory, currentAllocation]
    );

    const databaseUsage = databaseState.database_usage || { used: 0, limit: null, remaining: null, is_unlimited: true };
    const databaseCreateBlocked = !databaseUsage.is_unlimited && (databaseUsage.remaining ?? 0) <= 0;
    const connectorActionsAvailable = Boolean(serverMeta?.action_permissions?.connector_actions);
    const connectorReason = serverMeta?.action_permissions?.reason_text;

    const hydrateServer = (server) => {
        setServerMeta(server);
        setSelectedImage(server.image || null);
        setFormData({
            name: server.name || '',
            description: server.description || '',
            external_id: server.external_id || '',
            user_id: server.user_id || '',
            node_id: server.node_id || '',
            allocation_id: server.allocation_id || '',
            package_id: server.image?.package?.id || '',
            image_id: server.image_id || '',
            cpu: server.cpu ?? 100,
            memory: server.memory ?? 1024,
            disk: server.disk ?? 5120,
            swap: server.swap ?? 0,
            io: server.io ?? 500,
            threads: server.threads || '',
            oom_disabled: Boolean(server.oom_disabled),
            database_limit: server.feature_limits?.databases ?? '',
            allocation_limit: server.feature_limits?.allocations ?? '',
            backup_limit: server.feature_limits?.backups ?? '',
            docker_image: server.docker_image || server.image?.docker_image || '',
            startup: server.startup || server.image?.startup || '',
            variables: server.variables || {},
            status: server.status || 'offline',
            start_on_completion: server.status === 'running',
        });
    };

    const refreshServer = async () => {
        const response = await client.get(`/v1/admin/servers/${id}`);
        hydrateServer(response.data);
        return response.data;
    };

    const refreshNetwork = async () => {
        const response = await client.get(`/v1/admin/servers/${id}/network`);
        setNetworkState(response.data);
        setAllocationNotes(Object.fromEntries((response.data.allocations || []).map((allocation) => [allocation.id, allocation.notes || ''])));
        return response.data;
    };

    const refreshDatabases = async () => {
        const response = await client.get(`/v1/admin/servers/${id}/databases`);
        setDatabaseState(response.data);
        setDatabaseHosts(response.data.eligible_hosts || []);
        return response.data;
    };

    const refreshMounts = async () => {
        const response = await client.get(`/v1/admin/servers/${id}/mounts`);
        setAssignedMounts(response.data.assignedMounts || []);
        setAvailableMounts(response.data.availableMounts || []);
    };

    useEffect(() => {
        const boot = async () => {
            try {
                const [serverRes, usersRes, nodesRes, packagesRes, dbHostsRes] = await Promise.all([
                    client.get(`/v1/admin/servers/${id}`),
                    client.get('/v1/admin/users'),
                    client.get('/v1/admin/nodes'),
                    client.get('/v1/admin/packages'),
                    client.get('/v1/admin/databases'),
                ]);

                hydrateServer(serverRes.data);
                setUsers(usersRes.data.data || usersRes.data);
                setNodes(nodesRes.data);
                setPackages(packagesRes.data);
                setDatabaseHosts(dbHostsRes.data || []);

                await Promise.all([refreshDatabases(), refreshNetwork(), refreshMounts()]);
            } catch {
                setMessage({ type: 'error', text: 'Failed to load server details.' });
            } finally {
                setLoading(false);
            }
        };

        boot();
    }, [id]);

    useEffect(() => {
        const fetchImages = async () => {
            if (!formData.package_id) {
                setImages([]);
                return;
            }

            try {
                const response = await client.get('/v1/admin/images', {
                    params: { package_id: formData.package_id },
                });
                setImages(response.data);
            } catch {
                setImages([]);
            }
        };

        fetchImages();
    }, [formData.package_id]);

    useEffect(() => {
        const fetchNodeAllocations = async () => {
            if (!formData.node_id) {
                setNodeAllocations([]);
                return;
            }

            try {
                const response = await client.get(`/v1/admin/nodes/${formData.node_id}/allocations`);
                setNodeAllocations((response.data || []).filter((allocation) => !allocation.server_id || String(allocation.server_id) === String(id)));
            } catch {
                setNodeAllocations([]);
            }
        };

        fetchNodeAllocations();
    }, [formData.node_id, id]);

    const navigateTab = (_, nextTab) => {
        navigate(nextTab === 'about' ? `/admin/servers/${id}` : `/admin/servers/${id}/${nextTab}`);
    };

    const handleImageChange = async (imageId) => {
        setFormData((current) => ({ ...current, image_id: imageId }));

        if (!imageId) {
            setSelectedImage(null);
            return;
        }

        try {
            const response = await client.get(`/v1/admin/images/${imageId}`);
            const image = response.data;
            const defaultVariables = Object.fromEntries(
                (image.image_variables || []).map((variable) => [variable.env_variable, variable.default_value ?? ''])
            );
            setSelectedImage(image);
            setFormData((current) => ({
                ...current,
                image_id: imageId,
                docker_image: image.docker_image || '',
                startup: image.startup || '',
                variables: defaultVariables,
            }));
        } catch {
            setMessage({ type: 'error', text: 'Failed to load image details.' });
        }
    };

    const handleVariableChange = (envKey, value) => {
        setFormData((current) => ({
            ...current,
            variables: {
                ...current.variables,
                [envKey]: value,
            },
        }));
    };

    const saveServer = async ({ reinstall = false } = {}) => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            await client.put(`/v1/admin/servers/${id}`, {
                name: formData.name,
                description: formData.description || null,
                external_id: formData.external_id || null,
                user_id: Number(formData.user_id),
                node_id: Number(formData.node_id),
                image_id: formData.image_id,
                allocation_id: Number(formData.allocation_id),
                cpu: Number(formData.cpu),
                memory: Number(formData.memory),
                disk: Number(formData.disk),
                swap: Number(formData.swap),
                io: Number(formData.io),
                threads: formData.threads || null,
                oom_disabled: formData.oom_disabled,
                docker_image: formData.docker_image,
                startup: formData.startup,
                variables: formData.variables,
                reinstall,
                start_on_completion: formData.start_on_completion,
                status: formData.status,
                limits: {
                    cpu: Number(formData.cpu),
                    memory: Number(formData.memory),
                    disk: Number(formData.disk),
                    swap: Number(formData.swap),
                    io: Number(formData.io),
                    threads: formData.threads || null,
                },
                feature_limits: {
                    databases: normalizeNullableNumber(formData.database_limit),
                    allocations: normalizeNullableNumber(formData.allocation_limit),
                    backups: normalizeNullableNumber(formData.backup_limit),
                },
                allocation: {
                    default: Number(formData.allocation_id),
                    additional: (networkState.allocations || [])
                        .filter((allocation) => !allocation.is_primary)
                        .map((allocation) => Number(allocation.id)),
                },
            });

            await Promise.all([refreshServer(), refreshNetwork(), refreshDatabases()]);
            setMessage({
                type: 'success',
                text: reinstall ? 'Server updated and reinstall dispatched.' : 'Server updated successfully.',
            });
        } catch (error) {
            const apiMessage = error.response?.data?.message;
            const validationMessage = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(' ')
                : null;
            setMessage({ type: 'error', text: apiMessage || validationMessage || 'Failed to update server.' });
        } finally {
            setSaving(false);
        }
    };

    const handleCreateDatabase = async () => {
        setDbSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const response = await client.post(`/v1/admin/servers/${id}/databases`, dbForm);
            setDbForm(emptyDbForm);
            await refreshDatabases();
            setMessage({ type: 'success', text: response.data.message || 'Database created successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Failed to create database.' });
        } finally {
            setDbSaving(false);
        }
    };

    const handleResetDatabasePassword = async (databaseId) => {
        try {
            await client.post(`/v1/admin/servers/${id}/databases/${databaseId}/reset-password`);
            await refreshDatabases();
            setMessage({ type: 'success', text: 'Database password reset successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to reset database password.' });
        }
    };

    const handleDeleteDatabase = async (databaseId) => {
        if (!window.confirm('Delete this database?')) {
            return;
        }

        try {
            await client.delete(`/v1/admin/servers/${id}/databases/${databaseId}`);
            await refreshDatabases();
            setMessage({ type: 'success', text: 'Database deleted successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete database.' });
        }
    };

    const handleAssignAllocation = async () => {
        if (!networkForm.allocation_id) {
            return;
        }

        setNetworkSaving(true);
        try {
            const response = await client.post(`/v1/admin/servers/${id}/allocations`, {
                allocation_id: Number(networkForm.allocation_id),
                notes: networkForm.notes || null,
            });
            setNetworkState(response.data);
            setNetworkForm(emptyNetworkForm);
            await refreshServer();
            setMessage({ type: 'success', text: response.data.message || 'Allocation assigned successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Failed to assign allocation.' });
        } finally {
            setNetworkSaving(false);
        }
    };

    const handleSetPrimary = async (allocationId) => {
        try {
            const response = await client.post(`/v1/admin/servers/${id}/allocations/${allocationId}/primary`);
            setNetworkState(response.data);
            setFormData((current) => ({ ...current, allocation_id: allocationId }));
            await refreshServer();
            setMessage({ type: 'success', text: response.data.message || 'Primary allocation updated.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to set primary allocation.' });
        }
    };

    const handleSaveAllocationNotes = async (allocationId) => {
        try {
            const response = await client.patch(`/v1/admin/servers/${id}/allocations/${allocationId}`, {
                notes: allocationNotes[allocationId] || null,
            });
            setNetworkState(response.data);
            setMessage({ type: 'success', text: response.data.message || 'Allocation updated.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update allocation notes.' });
        }
    };

    const handleRemoveAllocation = async (allocationId) => {
        if (!window.confirm('Unassign this allocation from the server?')) {
            return;
        }

        try {
            const response = await client.delete(`/v1/admin/servers/${id}/allocations/${allocationId}`);
            setNetworkState(response.data);
            await refreshServer();
            setMessage({ type: 'success', text: response.data.message || 'Allocation unassigned.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to remove allocation.' });
        }
    };

    const handleDeleteServer = async () => {
        if (!window.confirm('Delete this server and dispatch connector delete?')) {
            return;
        }

        setDeleting(true);
        try {
            await client.delete(`/v1/admin/servers/${id}`);
            navigate('/admin/servers');
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete server.' });
        } finally {
            setDeleting(false);
        }
    };

    const handleAttachMount = async () => {
        if (!mountForm.mount_id) {
            return;
        }

        setMountSaving(true);
        try {
            const response = await client.post(`/v1/admin/servers/${id}/mounts`, mountForm);
            setAssignedMounts(response.data.assignedMounts || []);
            setAvailableMounts(response.data.availableMounts || []);
            setMountForm({ mount_id: '', read_only: false });
            setMessage({ type: 'success', text: response.data.message || 'Mount attached.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to attach mount.' });
        } finally {
            setMountSaving(false);
        }
    };

    const handleDetachMount = async (mountId) => {
        if (!window.confirm('Detach this mount from the server?')) {
            return;
        }

        try {
            const response = await client.delete(`/v1/admin/servers/${id}/mounts/${mountId}`);
            setAssignedMounts(response.data.assignedMounts || []);
            setAvailableMounts(response.data.availableMounts || []);
            setMessage({ type: 'success', text: response.data.message || 'Mount detached.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to detach mount.' });
        }
    };

    const renderAbout = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={8}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>About This Server</Typography>
                        <TextField fullWidth label="Server Name" value={formData.name} onChange={(event) => setFormData({ ...formData, name: event.target.value })} />
                        <TextField fullWidth multiline minRows={3} label="Description" value={formData.description} onChange={(event) => setFormData({ ...formData, description: event.target.value })} />
                        <TextField fullWidth label="External ID" value={formData.external_id} onChange={(event) => setFormData({ ...formData, external_id: event.target.value })} />
                        <Grid container spacing={2}>
                            <Grid item xs={12} md={6}>
                                <TextField select fullWidth label="Owner" value={formData.user_id} onChange={(event) => setFormData({ ...formData, user_id: event.target.value })}>
                                    {users.map((user) => <MenuItem key={user.id} value={user.id}>{user.username}</MenuItem>)}
                                </TextField>
                            </Grid>
                            <Grid item xs={12} md={6}>
                                <TextField select fullWidth label="Node" value={formData.node_id} onChange={(event) => setFormData({ ...formData, node_id: event.target.value })}>
                                    {nodes.map((node) => <MenuItem key={node.id} value={node.id}>{node.name}</MenuItem>)}
                                </TextField>
                            </Grid>
                        </Grid>
                        <Button variant="contained" startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />} onClick={() => saveServer()} disabled={saving}>
                            Save About
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
            <Grid item xs={12} md={4}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 1.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Server Details</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Status</Typography>
                        <Chip label={formData.status || 'offline'} color={formData.status === 'running' ? 'success' : 'default'} sx={{ width: 'fit-content', textTransform: 'uppercase', fontWeight: 700 }} />
                        <Divider />
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Primary Allocation</Typography>
                        <Typography variant="body1" sx={{ fontFamily: 'monospace' }}>{formatAllocationLabel(networkState.primary_allocation)}</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Template</Typography>
                        <Typography variant="body1">{selectedImage?.package?.name || '—'} / {selectedImage?.name || '—'}</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Database Usage</Typography>
                        <Typography variant="body1">{databaseUsage.used} / {databaseUsage.limit ?? 'Unlimited'}</Typography>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderBuild = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={8}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Build Configuration</Typography>
                        <Grid container spacing={2}>
                            <Grid item xs={12} md={6}><TextField fullWidth label="CPU Limit (%)" type="number" value={formData.cpu} onChange={(event) => setFormData({ ...formData, cpu: Number(event.target.value) })} /></Grid>
                            <Grid item xs={12} md={6}><TextField fullWidth label="Memory (MiB)" type="number" value={formData.memory} onChange={(event) => setFormData({ ...formData, memory: Number(event.target.value) })} /></Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="Disk (MiB)" type="number" value={formData.disk} onChange={(event) => setFormData({ ...formData, disk: Number(event.target.value) })} /></Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="Swap (MiB)" type="number" value={formData.swap} onChange={(event) => setFormData({ ...formData, swap: Number(event.target.value) })} /></Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="IO Weight" type="number" value={formData.io} onChange={(event) => setFormData({ ...formData, io: Number(event.target.value) })} /></Grid>
                            <Grid item xs={12} md={6}><TextField fullWidth label="Threads" value={formData.threads} onChange={(event) => setFormData({ ...formData, threads: event.target.value })} /></Grid>
                            <Grid item xs={12} md={6}>
                                <FormControlLabel
                                    control={<Checkbox checked={formData.oom_disabled} onChange={(event) => setFormData({ ...formData, oom_disabled: event.target.checked })} />}
                                    label="Disable OOM killer"
                                />
                            </Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="Database Limit" type="number" value={formData.database_limit} onChange={(event) => setFormData({ ...formData, database_limit: event.target.value })} helperText="Blank means unlimited" /></Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="Allocation Limit" type="number" value={formData.allocation_limit} onChange={(event) => setFormData({ ...formData, allocation_limit: event.target.value })} helperText="Additional allocations only" /></Grid>
                            <Grid item xs={12} md={4}><TextField fullWidth label="Backup Limit" type="number" value={formData.backup_limit} onChange={(event) => setFormData({ ...formData, backup_limit: event.target.value })} helperText="Stored only" /></Grid>
                        </Grid>
                        <Button variant="contained" startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />} onClick={() => saveServer()} disabled={saving}>
                            Save Build Configuration
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
            <Grid item xs={12} md={4}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 1.5 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Feature Limits</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                            Databases and additional allocations are enforced now. Backup limit is stored for parity until a live backup runtime exists.
                        </Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Databases: {formData.database_limit || 'Unlimited'}</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Extra Allocations: {formData.allocation_limit || 'Unlimited'}</Typography>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>Backups: {formData.backup_limit || 'Unlimited'}</Typography>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderStartup = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={8}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Startup Configuration</Typography>
                        <Grid container spacing={2}>
                            <Grid item xs={12} md={6}>
                                <TextField select fullWidth label="Package" value={formData.package_id} onChange={(event) => setFormData({ ...formData, package_id: event.target.value, image_id: '' })}>
                                    {packages.map((pkg) => <MenuItem key={pkg.id} value={pkg.id}>{pkg.name}</MenuItem>)}
                                </TextField>
                            </Grid>
                            <Grid item xs={12} md={6}>
                                <TextField select fullWidth label="Image" value={formData.image_id} onChange={(event) => handleImageChange(event.target.value)}>
                                    {images.map((image) => <MenuItem key={image.id} value={image.id}>{image.name}</MenuItem>)}
                                </TextField>
                            </Grid>
                        </Grid>
                        <TextField fullWidth label="Docker Image" value={formData.docker_image} onChange={(event) => setFormData({ ...formData, docker_image: event.target.value })} />
                        <TextField fullWidth multiline minRows={4} label="Startup Command" value={formData.startup} onChange={(event) => setFormData({ ...formData, startup: event.target.value })} />
                        <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>Service Variables</Typography>
                        <Grid container spacing={2}>
                            {(selectedImage?.image_variables || selectedImage?.imageVariables || []).map((variable) => (
                                <Grid item xs={12} md={6} key={variable.id}>
                                    <TextField
                                        fullWidth
                                        label={variable.name}
                                        value={formData.variables?.[variable.env_variable] ?? ''}
                                        onChange={(event) => handleVariableChange(variable.env_variable, event.target.value)}
                                        helperText={variable.description || variable.env_variable}
                                    />
                                </Grid>
                            ))}
                            {!((selectedImage?.image_variables || selectedImage?.imageVariables || []).length) && (
                                <Grid item xs={12}>
                                    <Alert severity="info">No configurable variables are available for this image.</Alert>
                                </Grid>
                            )}
                        </Grid>
                    </CardContent>
                </Card>
            </Grid>
            <Grid item xs={12} md={4}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Startup Preview</Typography>
                        <Paper variant="outlined" sx={{ p: 1.5, fontFamily: 'monospace', fontSize: 13, wordBreak: 'break-word', bgcolor: 'transparent' }}>
                            {serverMeta?.startup_preview?.resolved || startupPreview.resolved || 'No startup preview available.'}
                        </Paper>
                        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                            Runtime values include the selected primary allocation and `SERVER_MEMORY`.
                        </Typography>
                        <FormControlLabel
                            control={<Checkbox checked={formData.start_on_completion} onChange={(event) => setFormData({ ...formData, start_on_completion: event.target.checked })} />}
                            label="Start after reinstall"
                        />
                        <Button variant="contained" startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />} onClick={() => saveServer({ reinstall: true })} disabled={saving || !connectorActionsAvailable}>
                            Save & Reinstall
                        </Button>
                        <Button variant="outlined" onClick={() => saveServer()} disabled={saving}>
                            Save Without Reinstall
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderDatabases = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={7}>
                <Card sx={shellCardSx}>
                    <CardContent>
                        <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>Active Databases</Typography>
                            <Chip label={`${databaseUsage.used} / ${databaseUsage.limit ?? 'Unlimited'}`} size="small" />
                        </Stack>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Database</TableCell>
                                    <TableCell>User</TableCell>
                                    <TableCell>Remote</TableCell>
                                    <TableCell>Host</TableCell>
                                    <TableCell align="right">Actions</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {(databaseState.databases || []).map((database) => (
                                    <TableRow key={database.id}>
                                        <TableCell>{database.database}</TableCell>
                                        <TableCell>{database.username}</TableCell>
                                        <TableCell>{database.remote_id}</TableCell>
                                        <TableCell>{database.database_host?.host || database.database_host?.name || '—'}</TableCell>
                                        <TableCell align="right">
                                            <Button size="small" onClick={() => handleResetDatabasePassword(database.id)}>Reset Password</Button>
                                            <Button size="small" color="error" onClick={() => handleDeleteDatabase(database.id)}>Delete</Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {!(databaseState.databases || []).length && (
                                    <TableRow>
                                        <TableCell colSpan={5}>
                                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>No databases assigned to this server.</Typography>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </Grid>
            <Grid item xs={12} md={5}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Create Database</Typography>
                        <Alert severity={databaseCreateBlocked ? 'warning' : 'info'}>
                            {databaseCreateBlocked
                                ? 'Database limit reached for this server.'
                                : `Remaining slots: ${databaseUsage.remaining ?? 'Unlimited'}`}
                        </Alert>
                        <TextField fullWidth label="Database Suffix" value={dbForm.database} onChange={(event) => setDbForm({ ...dbForm, database: event.target.value })} disabled={databaseCreateBlocked} />
                        <TextField fullWidth label="Allowed Remote" value={dbForm.remote} onChange={(event) => setDbForm({ ...dbForm, remote: event.target.value })} disabled={databaseCreateBlocked} />
                        <TextField select fullWidth label="Database Host" value={dbForm.database_host_id} onChange={(event) => setDbForm({ ...dbForm, database_host_id: event.target.value })} disabled={databaseCreateBlocked}>
                            {databaseHosts.map((host) => (
                                <MenuItem key={host.id} value={host.id} disabled={host.available === false}>
                                    {host.name} ({host.host}:{host.port}){host.available === false ? ' - Exhausted' : ''}
                                </MenuItem>
                            ))}
                        </TextField>
                        <Button variant="contained" onClick={handleCreateDatabase} disabled={dbSaving || databaseCreateBlocked || !dbForm.database || !dbForm.database_host_id}>
                            {dbSaving ? 'Creating...' : 'Create Database'}
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderNetwork = () => {
        const assignedAllocations = networkState.allocations || [];
        const availableAllocations = (networkState.available_allocations || []).filter((allocation) => !assignedAllocations.some((entry) => String(entry.id) === String(allocation.id)));

        return (
            <Grid container spacing={3}>
                <Grid item xs={12} md={8}>
                    <Card sx={shellCardSx}>
                        <CardContent>
                            <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                                <Typography variant="h6" sx={{ fontWeight: 800 }}>Assigned Allocations</Typography>
                                <Chip label={`Limit: ${formData.allocation_limit || 'Unlimited'}`} size="small" />
                            </Stack>
                            <Table size="small">
                                <TableHead>
                                    <TableRow>
                                        <TableCell>Endpoint</TableCell>
                                        <TableCell>Notes</TableCell>
                                        <TableCell>Role</TableCell>
                                        <TableCell align="right">Actions</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {assignedAllocations.map((allocation) => (
                                        <TableRow key={allocation.id}>
                                            <TableCell sx={{ fontFamily: 'monospace' }}>{formatAllocationLabel(allocation)}</TableCell>
                                            <TableCell sx={{ minWidth: 240 }}>
                                                <TextField
                                                    fullWidth
                                                    size="small"
                                                    value={allocationNotes[allocation.id] ?? ''}
                                                    onChange={(event) => setAllocationNotes((current) => ({ ...current, [allocation.id]: event.target.value }))}
                                                />
                                            </TableCell>
                                            <TableCell>{allocation.is_primary ? 'Primary' : 'Additional'}</TableCell>
                                            <TableCell align="right">
                                                {!allocation.is_primary && <Button size="small" onClick={() => handleSetPrimary(allocation.id)}>Make Primary</Button>}
                                                <Button size="small" onClick={() => handleSaveAllocationNotes(allocation.id)}>Save Notes</Button>
                                                {!allocation.is_primary && <Button size="small" color="error" onClick={() => handleRemoveAllocation(allocation.id)}>Unassign</Button>}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {!assignedAllocations.length && (
                                        <TableRow>
                                            <TableCell colSpan={4}>
                                                <Typography variant="body2" sx={{ color: 'text.secondary' }}>No allocations assigned.</Typography>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12} md={4}>
                    <Card sx={shellCardSx}>
                        <CardContent sx={{ display: 'grid', gap: 2 }}>
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>Assign Allocation</Typography>
                            <Select
                                fullWidth
                                displayEmpty
                                value={networkForm.allocation_id}
                                onChange={(event) => setNetworkForm({ ...networkForm, allocation_id: event.target.value })}
                            >
                                <MenuItem value="">Select an allocation</MenuItem>
                                {availableAllocations.map((allocation) => (
                                    <MenuItem key={allocation.id} value={allocation.id}>{formatAllocationLabel(allocation)}</MenuItem>
                                ))}
                            </Select>
                            <TextField fullWidth label="Notes" value={networkForm.notes} onChange={(event) => setNetworkForm({ ...networkForm, notes: event.target.value })} />
                            <Button variant="contained" onClick={handleAssignAllocation} disabled={networkSaving || !networkForm.allocation_id}>
                                {networkSaving ? 'Assigning...' : 'Assign Allocation'}
                            </Button>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Primary allocation stays on the server record. Additional allocations reuse `node_allocations.server_id` and can carry notes.
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>
        );
    };

    const renderMounts = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={7}>
                <Card sx={shellCardSx}>
                    <CardContent>
                        <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Assigned Mounts</Typography>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Name</TableCell>
                                    <TableCell>Source</TableCell>
                                    <TableCell>Target</TableCell>
                                    <TableCell>Mode</TableCell>
                                    <TableCell>Node</TableCell>
                                    <TableCell align="right">Action</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {assignedMounts.map((mount) => (
                                    <TableRow key={mount.id}>
                                        <TableCell>{mount.name}</TableCell>
                                        <TableCell sx={{ fontFamily: 'monospace' }}>{mount.sourcePath}</TableCell>
                                        <TableCell sx={{ fontFamily: 'monospace' }}>{mount.targetPath}</TableCell>
                                        <TableCell>{mount.readOnly ? 'Read-Only' : 'Writable'}</TableCell>
                                        <TableCell>{mount.nodeName || 'Any'}</TableCell>
                                        <TableCell align="right">
                                            <Button size="small" color="error" onClick={() => handleDetachMount(mount.id)}>Detach</Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {!assignedMounts.length && (
                                    <TableRow>
                                        <TableCell colSpan={6}>
                                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                                No mounts are assigned to this server yet.
                                            </Typography>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </Grid>
            <Grid item xs={12} md={5}>
                <Card sx={shellCardSx}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800 }}>Attach Mount</Typography>
                        <TextField select fullWidth label="Available Mount" value={mountForm.mount_id} onChange={(event) => setMountForm({ ...mountForm, mount_id: event.target.value })}>
                            {availableMounts.map((mount) => (
                                <MenuItem key={mount.id} value={mount.id}>
                                    {mount.name} ({mount.targetPath})
                                </MenuItem>
                            ))}
                        </TextField>
                        <FormControlLabel
                            control={<Checkbox checked={mountForm.read_only} onChange={(event) => setMountForm({ ...mountForm, read_only: event.target.checked })} />}
                            label="Attach as read-only"
                        />
                        <Button variant="contained" onClick={handleAttachMount} disabled={mountSaving || !mountForm.mount_id}>
                            {mountSaving ? 'Attaching...' : 'Attach Mount'}
                        </Button>
                        <Button variant="outlined" onClick={refreshMounts}>
                            Refresh Mounts
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderDelete = () => (
        <Grid container spacing={3}>
            <Grid item xs={12} md={8}>
                <Card sx={{ ...shellCardSx, borderColor: 'rgba(244,67,54,0.35)' }}>
                    <CardContent sx={{ display: 'grid', gap: 2 }}>
                        <Typography variant="h6" sx={{ fontWeight: 800, color: 'error.main' }}>Delete Server</Typography>
                        <Alert severity="error">
                            This dispatches a connector delete request and removes the server record from the panel. This action is destructive.
                        </Alert>
                        <Button
                            variant="contained"
                            color="error"
                            startIcon={deleting ? <CircularProgress size={18} color="inherit" /> : <DeleteIcon />}
                            onClick={handleDeleteServer}
                            disabled={deleting || !connectorActionsAvailable}
                            sx={{ width: 'fit-content' }}
                        >
                            {deleting ? 'Deleting...' : 'Delete Server'}
                        </Button>
                    </CardContent>
                </Card>
            </Grid>
        </Grid>
    );

    const renderTabContent = () => {
        switch (activeTab) {
            case 'build':
                return renderBuild();
            case 'startup':
                return renderStartup();
            case 'databases':
                return renderDatabases();
            case 'network':
                return renderNetwork();
            case 'mounts':
                return renderMounts();
            case 'delete':
                return renderDelete();
            default:
                return renderAbout();
        }
    };

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
                <CircularProgress />
            </Box>
        );
    }

    return (
        <Box sx={{ maxWidth: 1500, mx: 'auto' }}>
            <Box sx={{ mb: 3, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate('/admin/servers')}>
                    <BackIcon />
                </IconButton>
                <Box>
                    <Typography variant="h4" sx={{ fontWeight: 900 }}>{formData.name}</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        CPanel-style admin flow for build, startup, databases, network, mounts, and deletion.
                    </Typography>
                </Box>
                <Chip label={formData.status || 'offline'} color={formData.status === 'running' ? 'success' : 'default'} sx={{ ml: 'auto', textTransform: 'uppercase', fontWeight: 800 }} />
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}
            {connectorReason ? <Alert severity={connectorActionsAvailable ? 'success' : 'warning'} sx={{ mb: 3 }}>{connectorReason}</Alert> : null}

            <Paper sx={{ mb: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.06)', bgcolor: 'background.paper' }}>
                <Tabs
                    value={activeTab}
                    onChange={navigateTab}
                    variant="scrollable"
                    scrollButtons="auto"
                    sx={{
                        '& .MuiTab-root': {
                            minHeight: 58,
                            fontWeight: 700,
                            textTransform: 'none',
                            alignItems: 'center',
                            gap: 1,
                        },
                    }}
                >
                    {serverTabs.map((tab) => (
                        <Tab
                            key={tab.key}
                            value={tab.key}
                            icon={tab.icon}
                            iconPosition="start"
                            label={tab.label}
                        />
                    ))}
                </Tabs>
            </Paper>

            {renderTabContent()}
        </Box>
    );
};

export default AdminServerEdit;
