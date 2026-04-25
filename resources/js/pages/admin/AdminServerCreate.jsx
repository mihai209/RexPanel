import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    CircularProgress,
    FormHelperText,
    FormControlLabel,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Select,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { ArrowBack as BackIcon, Save as SaveIcon } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';
import { buildStartupPreview, formatAllocationLabel, normalizeNullableNumber } from './serverFormUtils';

const defaultForm = {
    name: '',
    description: '',
    external_id: '',
    user_id: '',
    node_id: '',
    allocation_id: '',
    additional_allocation_ids: [],
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
    start_on_completion: false,
    status: 'offline',
};

const AdminServerCreate = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [formData, setFormData] = useState(defaultForm);
    const [users, setUsers] = useState([]);
    const [nodes, setNodes] = useState([]);
    const [packages, setPackages] = useState([]);
    const [images, setImages] = useState([]);
    const [allocations, setAllocations] = useState([]);
    const [selectedImage, setSelectedImage] = useState(null);
    const selectedNode = useMemo(
        () => nodes.find((node) => String(node.id) === String(formData.node_id)),
        [nodes, formData.node_id]
    );

    useEffect(() => {
        const fetchData = async () => {
            try {
                const [usersRes, nodesRes, packagesRes] = await Promise.all([
                    client.get('/v1/admin/users'),
                    client.get('/v1/admin/nodes'),
                    client.get('/v1/admin/packages'),
                ]);
                setUsers(usersRes.data.data || usersRes.data);
                setNodes(nodesRes.data);
                setPackages(packagesRes.data);
            } catch {
                setMessage({ type: 'error', text: 'Failed to load server creation dependencies.' });
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, []);

    useEffect(() => {
        const fetchImages = async () => {
            if (!formData.package_id) {
                setImages([]);
                setSelectedImage(null);
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
        const fetchAllocations = async () => {
            if (!formData.node_id) {
                setAllocations([]);
                return;
            }

            try {
                const response = await client.get(`/v1/admin/nodes/${formData.node_id}/allocations`);
                const freeAllocations = (response.data || []).filter((allocation) => !allocation.server_id);
                setAllocations(freeAllocations);

                if (!freeAllocations.some((allocation) => String(allocation.id) === String(formData.allocation_id))) {
                    setFormData((current) => ({ ...current, allocation_id: '', additional_allocation_ids: [] }));
                }
            } catch {
                setAllocations([]);
            }
        };

        fetchAllocations();
    }, [formData.node_id]);

    useEffect(() => {
        const fetchImageDetails = async () => {
            if (!formData.image_id) {
                setSelectedImage(null);
                setFormData((current) => ({ ...current, docker_image: '', startup: '', variables: {} }));
                return;
            }

            try {
                const response = await client.get(`/v1/admin/images/${formData.image_id}`);
                const image = response.data;
                const variables = Object.fromEntries(
                    (image.image_variables || []).map((variable) => [variable.env_variable, variable.default_value ?? ''])
                );

                setSelectedImage(image);
                setFormData((current) => ({
                    ...current,
                    docker_image: image.docker_image || '',
                    startup: image.startup || '',
                    variables,
                }));
            } catch {
                setSelectedImage(null);
            }
        };

        fetchImageDetails();
    }, [formData.image_id]);

    const selectedPackage = useMemo(
        () => packages.find((pkg) => String(pkg.id) === String(formData.package_id)),
        [packages, formData.package_id]
    );

    const primaryAllocation = useMemo(
        () => allocations.find((allocation) => String(allocation.id) === String(formData.allocation_id)) || null,
        [allocations, formData.allocation_id]
    );

    const additionalAllocationOptions = useMemo(
        () => allocations.filter((allocation) => String(allocation.id) !== String(formData.allocation_id)),
        [allocations, formData.allocation_id]
    );

    const startupPreview = useMemo(
        () => buildStartupPreview({
            startup: formData.startup,
            variables: formData.variables,
            memory: formData.memory,
            allocation: primaryAllocation,
        }),
        [formData.startup, formData.variables, formData.memory, primaryAllocation]
    );

    const handleSubmit = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const payload = {
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
                    additional: formData.additional_allocation_ids.map((value) => Number(value)),
                },
            };

            const response = await client.post('/v1/admin/servers', payload);
            navigate(`/admin/servers/${response.data.server.id}`);
        } catch (error) {
            const apiMessage = error.response?.data?.message;
            const validationMessage = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(' ')
                : null;
            setMessage({ type: 'error', text: apiMessage || validationMessage || 'Failed to create server.' });
        } finally {
            setSaving(false);
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

    if (loading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}><CircularProgress /></Box>;
    }

    return (
        <Box>
            <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate('/admin/servers')}>
                    <BackIcon />
                </IconButton>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>Create Server</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        CPanel-style flow: choose owner, node, allocation, package, image, then review startup and variables.
                    </Typography>
                </Box>
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <Grid container spacing={3}>
                <Grid item xs={12} lg={8}>
                    <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Box sx={{ display: 'grid', gap: 2 }}>
                            <Typography variant="h6" sx={{ fontWeight: 700 }}>Core Details</Typography>
                            <TextField label="Server Name" value={formData.name} onChange={(event) => setFormData({ ...formData, name: event.target.value })} />
                            <TextField label="Description" multiline minRows={3} value={formData.description} onChange={(event) => setFormData({ ...formData, description: event.target.value })} />
                            <TextField label="External ID" value={formData.external_id} onChange={(event) => setFormData({ ...formData, external_id: event.target.value })} />
                            <TextField select label="Owner" value={formData.user_id} onChange={(event) => setFormData({ ...formData, user_id: event.target.value })}>
                                {users.map((user) => (
                                    <MenuItem key={user.id} value={user.id}>{user.username} ({user.email})</MenuItem>
                                ))}
                            </TextField>

                            <Typography variant="h6" sx={{ fontWeight: 700, mt: 2 }}>Allocation</Typography>
                            {selectedNode?.health?.reason_text ? (
                                <Alert severity={selectedNode?.health?.is_active ? 'success' : 'warning'}>
                                    {selectedNode.health.reason_text}
                                </Alert>
                            ) : null}
                            <Grid container spacing={2}>
                                <Grid item xs={12} md={6}>
                                    <TextField select fullWidth label="Node" value={formData.node_id} onChange={(event) => setFormData({ ...formData, node_id: event.target.value, allocation_id: '' })}>
                                        {nodes.map((node) => (
                                            <MenuItem key={node.id} value={node.id} disabled={!node.health?.is_active}>
                                                {node.name}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                                <Grid item xs={12} md={6}>
                                    <TextField select fullWidth label="Primary Allocation" value={formData.allocation_id} onChange={(event) => setFormData({ ...formData, allocation_id: event.target.value })} disabled={!formData.node_id}>
                                        {allocations.map((allocation) => (
                                            <MenuItem key={allocation.id} value={allocation.id}>
                                                {formatAllocationLabel(allocation)}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                                <Grid item xs={12}>
                                    <Typography variant="body2" sx={{ fontWeight: 700, mb: 1 }}>Additional Allocations</Typography>
                                    <Select
                                        fullWidth
                                        multiple
                                        displayEmpty
                                        value={formData.additional_allocation_ids}
                                        onChange={(event) => setFormData({ ...formData, additional_allocation_ids: event.target.value })}
                                        renderValue={(selected) => (
                                            selected.length
                                                ? selected.map((value) => formatAllocationLabel(additionalAllocationOptions.find((allocation) => String(allocation.id) === String(value)))).join(', ')
                                                : 'No extra allocations'
                                        )}
                                    >
                                        {additionalAllocationOptions.map((allocation) => (
                                            <MenuItem key={allocation.id} value={allocation.id}>
                                                {formatAllocationLabel(allocation)}
                                            </MenuItem>
                                        ))}
                                    </Select>
                                    <FormHelperText>Extra ports are assigned in addition to the primary allocation.</FormHelperText>
                                </Grid>
                            </Grid>

                            <Typography variant="h6" sx={{ fontWeight: 700, mt: 2 }}>Template</Typography>
                            <Grid container spacing={2}>
                                <Grid item xs={12} md={6}>
                                    <TextField select fullWidth label="Package" value={formData.package_id} onChange={(event) => setFormData({ ...formData, package_id: event.target.value, image_id: '' })}>
                                        {packages.map((pkg) => (
                                            <MenuItem key={pkg.id} value={pkg.id}>{pkg.name}</MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                                <Grid item xs={12} md={6}>
                                    <TextField select fullWidth label="Image" value={formData.image_id} onChange={(event) => setFormData({ ...formData, image_id: event.target.value })} disabled={!formData.package_id}>
                                        {images.map((image) => (
                                            <MenuItem key={image.id} value={image.id}>{image.name}</MenuItem>
                                        ))}
                                    </TextField>
                                </Grid>
                            </Grid>

                            <TextField fullWidth label="Docker Image" value={formData.docker_image} onChange={(event) => setFormData({ ...formData, docker_image: event.target.value })} disabled={!selectedImage} />
                            <TextField fullWidth multiline minRows={4} label="Startup Command" value={formData.startup} onChange={(event) => setFormData({ ...formData, startup: event.target.value })} disabled={!selectedImage} />

                            <Typography variant="h6" sx={{ fontWeight: 700, mt: 2 }}>Resource Limits</Typography>
                            <Grid container spacing={2}>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="CPU %" type="number" value={formData.cpu} onChange={(event) => setFormData({ ...formData, cpu: Number(event.target.value) })} /></Grid>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="Memory (MiB)" type="number" value={formData.memory} onChange={(event) => setFormData({ ...formData, memory: Number(event.target.value) })} /></Grid>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="Disk (MiB)" type="number" value={formData.disk} onChange={(event) => setFormData({ ...formData, disk: Number(event.target.value) })} /></Grid>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="IO Weight" type="number" value={formData.io} onChange={(event) => setFormData({ ...formData, io: Number(event.target.value) })} /></Grid>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="Swap (MiB)" type="number" value={formData.swap} onChange={(event) => setFormData({ ...formData, swap: Number(event.target.value) })} /></Grid>
                                <Grid item xs={12} sm={6}><TextField fullWidth label="Threads" value={formData.threads} onChange={(event) => setFormData({ ...formData, threads: event.target.value })} /></Grid>
                                <Grid item xs={12} sm={4}><TextField fullWidth label="Database Limit" type="number" value={formData.database_limit} onChange={(event) => setFormData({ ...formData, database_limit: event.target.value })} helperText="Leave blank for unlimited" /></Grid>
                                <Grid item xs={12} sm={4}><TextField fullWidth label="Allocation Limit" type="number" value={formData.allocation_limit} onChange={(event) => setFormData({ ...formData, allocation_limit: event.target.value })} helperText="Additional allocations only" /></Grid>
                                <Grid item xs={12} sm={4}><TextField fullWidth label="Backup Limit" type="number" value={formData.backup_limit} onChange={(event) => setFormData({ ...formData, backup_limit: event.target.value })} helperText="Stored only" /></Grid>
                            </Grid>
                            <FormControlLabel
                                control={<Checkbox checked={formData.oom_disabled} onChange={(event) => setFormData({ ...formData, oom_disabled: event.target.checked })} />}
                                label="Disable OOM killer"
                            />

                            <Typography variant="h6" sx={{ fontWeight: 700, mt: 2 }}>Environment Variables</Typography>
                            {(selectedImage?.image_variables || []).length ? (
                                <Grid container spacing={2}>
                                    {selectedImage.image_variables.map((variable) => (
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
                                </Grid>
                            ) : (
                                <Alert severity="info">Select an image to load its startup variables.</Alert>
                            )}
                        </Box>
                    </Paper>
                </Grid>

                <Grid item xs={12} lg={4}>
                    <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                        <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Provisioning</Typography>
                        <Stack spacing={1.5} sx={{ mb: 3 }}>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>Package: {selectedPackage?.name || '—'}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>Image: {selectedImage?.name || '—'}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>Primary: {formatAllocationLabel(primaryAllocation)}</Typography>
                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                Extra Ports: {formData.additional_allocation_ids.length}
                            </Typography>
                        </Stack>

                        <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Resolved Startup</Typography>
                        <Paper variant="outlined" sx={{ p: 1.5, mb: 2, fontFamily: 'monospace', fontSize: 13, wordBreak: 'break-word', bgcolor: 'transparent' }}>
                            {startupPreview.resolved || 'Select an image and allocation to preview startup.'}
                        </Paper>
                        <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Runtime Environment</Typography>
                        <Paper variant="outlined" sx={{ p: 1.5, mb: 3, bgcolor: 'transparent', maxHeight: 220, overflow: 'auto' }}>
                            <pre style={{ margin: 0, fontSize: 12 }}>{JSON.stringify(startupPreview.env, null, 2)}</pre>
                        </Paper>

                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={formData.start_on_completion}
                                    onChange={(event) => setFormData({ ...formData, start_on_completion: event.target.checked })}
                                />
                            }
                            label="Start after install"
                            sx={{ mb: 2 }}
                        />

                        <Button
                            fullWidth
                            variant="contained"
                            startIcon={saving ? <CircularProgress size={18} color="inherit" /> : <SaveIcon />}
                            onClick={handleSubmit}
                            disabled={saving || !formData.name || !formData.user_id || !formData.node_id || !formData.allocation_id || !formData.image_id || (selectedNode ? !selectedNode.health?.is_active : false)}
                        >
                            {saving ? 'Creating...' : 'Create Server'}
                        </Button>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default AdminServerCreate;
