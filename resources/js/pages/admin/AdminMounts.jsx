import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import {
    AddCircle as AddIcon,
    DeleteForever as DeleteIcon,
    FolderOpen as MountsIcon,
} from '@mui/icons-material';
import client from '../../api/client';

const defaultForm = {
    name: '',
    description: '',
    source_path: '',
    target_path: '',
    node_id: '',
    read_only: false,
};

const AdminMounts = () => {
    const [mounts, setMounts] = useState([]);
    const [nodes, setNodes] = useState([]);
    const [form, setForm] = useState(defaultForm);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });

    const load = async () => {
        try {
            const [mountsResponse, nodesResponse] = await Promise.all([
                client.get('/v1/admin/mounts'),
                client.get('/v1/admin/nodes'),
            ]);

            setMounts(mountsResponse.data.mounts || []);
            setNodes(nodesResponse.data || []);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to load mounts.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, []);

    const createMount = async () => {
        setSaving(true);
        setMessage({ type: '', text: '' });

        try {
            const { data } = await client.post('/v1/admin/mounts', {
                ...form,
                node_id: form.node_id || null,
            });
            setMounts(data.mounts || []);
            setForm(defaultForm);
            setMessage({ type: 'success', text: data.message || 'Mount created successfully.' });
        } catch (error) {
            const validationMessage = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(' ')
                : null;
            setMessage({ type: 'error', text: error.response?.data?.message || validationMessage || 'Failed to create mount.' });
        } finally {
            setSaving(false);
        }
    };

    const deleteMount = async (mountId) => {
        if (!window.confirm('Delete this mount and detach it from all servers?')) {
            return;
        }

        try {
            const { data } = await client.delete(`/v1/admin/mounts/${mountId}`);
            setMounts(data.mounts || []);
            setMessage({ type: 'success', text: data.message || 'Mount deleted.' });
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete mount.' });
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
                    background: 'linear-gradient(135deg, rgba(25,30,42,0.98) 0%, rgba(15,18,28,0.98) 100%)',
                }}
            >
                <Chip
                    icon={<MountsIcon sx={{ fontSize: 16 }} />}
                    label="Shared Storage"
                    size="small"
                    sx={{ mb: 2, bgcolor: 'rgba(255,191,95,0.12)', color: '#ffcf6e', fontWeight: 700 }}
                />
                <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1, mb: 1.5 }}>
                    Mount definitions for node-scoped server reuse
                </Typography>
                <Typography variant="body2" sx={{ color: '#98a4b3', maxWidth: 760, lineHeight: 1.8 }}>
                    This ports CPanel mounts into RA-panel using the current node model. Mounts can be global or restricted to a single node and are attached to servers separately from the template itself.
                </Typography>
            </Box>

            {message.text ? (
                <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>
                    {message.text}
                </Alert>
            ) : null}

            <Grid container spacing={3}>
                <Grid item xs={12} lg={5}>
                    <Card sx={{ border: '1px solid rgba(255,255,255,0.06)', borderRadius: 3, boxShadow: 'none' }}>
                        <CardContent sx={{ display: 'grid', gap: 2 }}>
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                Create Mount
                            </Typography>
                            <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
                            <TextField label="Description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
                            <TextField label="Source Path (Host)" value={form.source_path} onChange={(event) => setForm({ ...form, source_path: event.target.value })} placeholder="/var/lib/ra-panel/shared" />
                            <TextField label="Target Path (Container)" value={form.target_path} onChange={(event) => setForm({ ...form, target_path: event.target.value })} placeholder="/home/container/shared" />
                            <TextField select label="Node Scope" value={form.node_id} onChange={(event) => setForm({ ...form, node_id: event.target.value })}>
                                <MenuItem value="">Any Node</MenuItem>
                                {nodes.map((node) => (
                                    <MenuItem key={node.id} value={node.id}>{node.name}</MenuItem>
                                ))}
                            </TextField>
                            <TextField select label="Write Mode" value={form.read_only ? 'readonly' : 'writable'} onChange={(event) => setForm({ ...form, read_only: event.target.value === 'readonly' })}>
                                <MenuItem value="writable">Writable</MenuItem>
                                <MenuItem value="readonly">Read-Only</MenuItem>
                            </TextField>
                            <Button variant="contained" startIcon={<AddIcon />} onClick={createMount} disabled={saving || loading}>
                                {saving ? 'Creating...' : 'Create Mount'}
                            </Button>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12} lg={7}>
                    <Paper elevation={0} sx={{ p: 0, borderRadius: 3, border: '1px solid rgba(255,255,255,0.06)', overflow: 'hidden' }}>
                        <Box sx={{ p: 3, borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                            <Typography variant="h6" sx={{ fontWeight: 800 }}>
                                Existing Mounts
                            </Typography>
                        </Box>
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
                                {(mounts || []).map((mount) => (
                                    <TableRow key={mount.id}>
                                        <TableCell>
                                            <Typography variant="body2" sx={{ fontWeight: 700 }}>{mount.name}</Typography>
                                            {mount.description ? (
                                                <Typography variant="caption" sx={{ color: 'text.secondary' }}>{mount.description}</Typography>
                                            ) : null}
                                        </TableCell>
                                        <TableCell sx={{ fontFamily: 'monospace' }}>{mount.sourcePath}</TableCell>
                                        <TableCell sx={{ fontFamily: 'monospace' }}>{mount.targetPath}</TableCell>
                                        <TableCell>
                                            <Chip
                                                size="small"
                                                label={mount.readOnly ? 'Read-Only' : 'Writable'}
                                                sx={{
                                                    bgcolor: mount.readOnly ? 'rgba(255,255,255,0.06)' : 'rgba(54,211,153,0.12)',
                                                    color: mount.readOnly ? '#cbd5e1' : '#7ce7bf',
                                                    fontWeight: 700,
                                                }}
                                            />
                                        </TableCell>
                                        <TableCell>{mount.nodeName || 'Any'}</TableCell>
                                        <TableCell align="right">
                                            <IconButton color="error" onClick={() => deleteMount(mount.id)}>
                                                <DeleteIcon />
                                            </IconButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {!loading && !(mounts || []).length ? (
                                    <TableRow>
                                        <TableCell colSpan={6}>
                                            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                                                No mounts created yet.
                                            </Typography>
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </Paper>
                </Grid>
            </Grid>
        </Box>
    );
};

export default AdminMounts;
