import React, { useEffect, useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    Grid,
    IconButton,
    MenuItem,
    Paper,
    Tab,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import {
    ArrowBack as BackIcon,
    Delete as DeleteIcon,
    Download as ExportIcon,
    Save as SaveIcon,
} from '@mui/icons-material';
import { useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';

const variableDefaults = {
    name: '',
    description: '',
    env_variable: '',
    default_value: '',
    user_viewable: true,
    user_editable: true,
    rules: '',
    field_type: 'text',
    sort_order: 0,
};

const AdminImageView = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [tab, setTab] = useState(0);
    const [image, setImage] = useState(null);
    const [packages, setPackages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [configForm, setConfigForm] = useState(null);
    const [scriptsForm, setScriptsForm] = useState(null);
    const [replaceJson, setReplaceJson] = useState('');
    const [variableForm, setVariableForm] = useState(variableDefaults);
    const [editingVariableId, setEditingVariableId] = useState(null);

    const fetchImage = async () => {
        setLoading(true);
        try {
            const [imageResponse, packagesResponse] = await Promise.all([
                client.get(`/v1/admin/images/${id}`),
                client.get('/v1/admin/packages'),
            ]);
            const payload = imageResponse.data;
            setImage(payload);
            setPackages(packagesResponse.data);
            setConfigForm({
                package_id: payload.package_id,
                name: payload.name || '',
                description: payload.description || '',
                docker_image: payload.docker_image || '',
                docker_images_text: Object.entries(payload.docker_images || {}).map(([label, value]) => `${label}|${value}`).join('\n'),
                features_text: (payload.features || []).join('\n'),
                denylist_text: (payload.file_denylist || []).join('\n'),
                startup: payload.startup || '',
                config_files: payload.config_files || '',
                config_startup: payload.config_startup || '',
                config_logs: payload.config_logs || '',
                config_stop: payload.config_stop || '',
                is_public: Boolean(payload.is_public),
            });
            setScriptsForm({
                script_install: payload.script_install || '',
                script_entry: payload.script_entry || '',
                script_container: payload.script_container || '',
            });
        } catch {
            setMessage({ type: 'error', text: 'Failed to load image details.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchImage();
    }, [id]);

    const normalizeDockerImages = (raw) => {
        const lines = raw.split('\n').map((line) => line.trim()).filter(Boolean);
        const images = {};
        lines.forEach((line) => {
            const [label, value] = line.split('|');
            images[value ? label : line] = value || line;
        });
        return images;
    };

    const handleSaveConfiguration = async () => {
        setSaving(true);
        try {
            await client.patch(`/v1/admin/images/${id}`, {
                package_id: configForm.package_id,
                name: configForm.name,
                description: configForm.description,
                docker_image: configForm.docker_image,
                docker_images: normalizeDockerImages(configForm.docker_images_text),
                features: configForm.features_text.split('\n').map((line) => line.trim()).filter(Boolean),
                file_denylist: configForm.denylist_text.split('\n').map((line) => line.trim()).filter(Boolean),
                startup: configForm.startup,
                config_files: configForm.config_files,
                config_startup: configForm.config_startup,
                config_logs: configForm.config_logs,
                config_stop: configForm.config_stop,
                is_public: configForm.is_public,
            });
            setMessage({ type: 'success', text: 'Image configuration updated successfully.' });
            await fetchImage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update image configuration.' });
        } finally {
            setSaving(false);
        }
    };

    const handleSaveScripts = async () => {
        setSaving(true);
        try {
            await client.patch(`/v1/admin/images/${id}/scripts`, scriptsForm);
            setMessage({ type: 'success', text: 'Install script updated successfully.' });
            await fetchImage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update install script.' });
        } finally {
            setSaving(false);
        }
    };

    const handleReplaceImport = async () => {
        setSaving(true);
        try {
            await client.put(`/v1/admin/images/${id}/import`, { json_payload: replaceJson });
            setReplaceJson('');
            setMessage({ type: 'success', text: 'Image replaced from import successfully.' });
            await fetchImage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to replace image from import.' });
        } finally {
            setSaving(false);
        }
    };

    const handleSaveVariable = async () => {
        setSaving(true);
        try {
            if (editingVariableId) {
                await client.patch(`/v1/admin/images/${id}/variables/${editingVariableId}`, variableForm);
            } else {
                await client.post(`/v1/admin/images/${id}/variables`, variableForm);
            }
            setVariableForm(variableDefaults);
            setEditingVariableId(null);
            setMessage({ type: 'success', text: 'Variable saved successfully.' });
            await fetchImage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to save variable.' });
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteVariable = async (variableId) => {
        if (!window.confirm('Delete this variable?')) {
            return;
        }

        try {
            await client.delete(`/v1/admin/images/${id}/variables/${variableId}`);
            setMessage({ type: 'success', text: 'Variable deleted successfully.' });
            await fetchImage();
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete variable.' });
        }
    };

    const handleDeleteImage = async () => {
        if (!window.confirm('Delete this image?')) {
            return;
        }

        try {
            await client.delete(`/v1/admin/images/${id}`);
            navigate(`/admin/packages/${image.package_id}`);
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to delete image.' });
        }
    };

    if (loading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}><Typography>Loading...</Typography></Box>;
    }

    if (!image || !configForm || !scriptsForm) {
        return <Alert severity="error">Image not found.</Alert>;
    }

    return (
        <Box>
            <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2 }}>
                <IconButton onClick={() => navigate(`/admin/packages/${image.package_id}`)}>
                    <BackIcon />
                </IconButton>
                <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="h5" sx={{ fontWeight: 800 }}>{image.name}</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        Packages / {image.package?.name} / {image.name}
                    </Typography>
                </Box>
                <Button variant="outlined" startIcon={<ExportIcon />} onClick={() => window.open(`/api/v1/admin/images/${id}/export`, '_blank')}>
                    Export
                </Button>
                <Button variant="outlined" color="error" startIcon={<DeleteIcon />} onClick={handleDeleteImage}>
                    Delete
                </Button>
            </Box>

            {message.text && <Alert severity={message.type || 'info'} sx={{ mb: 3 }}>{message.text}</Alert>}

            <Paper sx={{ mb: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                <Tabs value={tab} onChange={(_, next) => setTab(next)}>
                    <Tab label="Configuration" />
                    <Tab label="Variables" />
                    <Tab label="Install Script" />
                </Tabs>
            </Paper>

            {tab === 0 && (
                <Grid container spacing={3}>
                    <Grid item xs={12}>
                        <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Replace from JSON</Typography>
                            <TextField
                                multiline
                                minRows={6}
                                fullWidth
                                value={replaceJson}
                                onChange={(event) => setReplaceJson(event.target.value)}
                                placeholder="Paste the image JSON here to replace this CPanel image configuration."
                            />
                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
                                <Button variant="outlined" onClick={handleReplaceImport} disabled={saving || !replaceJson}>
                                    Replace Image
                                </Button>
                            </Box>
                        </Paper>
                    </Grid>
                    <Grid item xs={12}>
                        <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Configuration</Typography>
                            <Box sx={{ display: 'grid', gap: 2 }}>
                                <TextField select label="Package" value={configForm.package_id} onChange={(event) => setConfigForm({ ...configForm, package_id: event.target.value })}>
                                    {packages.map((pkg) => (
                                        <MenuItem key={pkg.id} value={pkg.id}>{pkg.name}</MenuItem>
                                    ))}
                                </TextField>
                                <TextField label="Name" value={configForm.name} onChange={(event) => setConfigForm({ ...configForm, name: event.target.value })} />
                                <TextField label="Author" value={image.author || ''} InputProps={{ readOnly: true }} />
                                <TextField label="Description" multiline minRows={3} value={configForm.description} onChange={(event) => setConfigForm({ ...configForm, description: event.target.value })} />
                                <TextField label="Default Docker Image" value={configForm.docker_image} onChange={(event) => setConfigForm({ ...configForm, docker_image: event.target.value })} />
                                <TextField label="Docker Images" multiline minRows={4} value={configForm.docker_images_text} onChange={(event) => setConfigForm({ ...configForm, docker_images_text: event.target.value })} />
                                <TextField label="Features" multiline minRows={3} value={configForm.features_text} onChange={(event) => setConfigForm({ ...configForm, features_text: event.target.value })} />
                                <TextField label="File Denylist" multiline minRows={3} value={configForm.denylist_text} onChange={(event) => setConfigForm({ ...configForm, denylist_text: event.target.value })} />
                                <TextField label="Startup Command" multiline minRows={4} value={configForm.startup} onChange={(event) => setConfigForm({ ...configForm, startup: event.target.value })} />
                                <TextField label="Config Files" multiline minRows={4} value={configForm.config_files} onChange={(event) => setConfigForm({ ...configForm, config_files: event.target.value })} />
                                <TextField label="Config Startup" multiline minRows={4} value={configForm.config_startup} onChange={(event) => setConfigForm({ ...configForm, config_startup: event.target.value })} />
                                <TextField label="Config Logs" multiline minRows={4} value={configForm.config_logs} onChange={(event) => setConfigForm({ ...configForm, config_logs: event.target.value })} />
                                <TextField label="Config Stop" value={configForm.config_stop} onChange={(event) => setConfigForm({ ...configForm, config_stop: event.target.value })} />
                                <FormControlLabel
                                    control={<Checkbox checked={configForm.is_public} onChange={(event) => setConfigForm({ ...configForm, is_public: event.target.checked })} />}
                                    label="Public image"
                                />
                                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <Button variant="contained" startIcon={<SaveIcon />} onClick={handleSaveConfiguration} disabled={saving}>
                                        {saving ? 'Saving...' : 'Save'}
                                    </Button>
                                </Box>
                            </Box>
                        </Paper>
                    </Grid>
                </Grid>
            )}

            {tab === 1 && (
                <Grid container spacing={3}>
                    <Grid item xs={12} md={5}>
                        <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>{editingVariableId ? 'Edit Variable' : 'New Variable'}</Typography>
                            <Box sx={{ display: 'grid', gap: 2 }}>
                                <TextField label="Name" value={variableForm.name} onChange={(event) => setVariableForm({ ...variableForm, name: event.target.value })} />
                                <TextField label="Environment Variable" value={variableForm.env_variable} onChange={(event) => setVariableForm({ ...variableForm, env_variable: event.target.value })} />
                                <TextField label="Description" value={variableForm.description} onChange={(event) => setVariableForm({ ...variableForm, description: event.target.value })} />
                                <TextField label="Default Value" value={variableForm.default_value} onChange={(event) => setVariableForm({ ...variableForm, default_value: event.target.value })} />
                                <TextField label="Rules" value={variableForm.rules} onChange={(event) => setVariableForm({ ...variableForm, rules: event.target.value })} />
                                <TextField label="Field Type" value={variableForm.field_type} onChange={(event) => setVariableForm({ ...variableForm, field_type: event.target.value })} />
                                <FormControlLabel control={<Checkbox checked={variableForm.user_viewable} onChange={(event) => setVariableForm({ ...variableForm, user_viewable: event.target.checked })} />} label="User viewable" />
                                <FormControlLabel control={<Checkbox checked={variableForm.user_editable} onChange={(event) => setVariableForm({ ...variableForm, user_editable: event.target.checked })} />} label="User editable" />
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Button onClick={() => { setVariableForm(variableDefaults); setEditingVariableId(null); }} disabled={saving}>Reset</Button>
                                    <Button variant="contained" onClick={handleSaveVariable} disabled={saving || !variableForm.name || !variableForm.env_variable}>
                                        {saving ? 'Saving...' : 'Save Variable'}
                                    </Button>
                                </Box>
                            </Box>
                        </Paper>
                    </Grid>
                    <Grid item xs={12} md={7}>
                        <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                            <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Variables</Typography>
                            <Box sx={{ display: 'grid', gap: 2 }}>
                                {(image.image_variables || []).map((variable) => (
                                    <Paper key={variable.id} variant="outlined" sx={{ p: 2, bgcolor: 'background.default' }}>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>{variable.name}</Typography>
                                        <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'text.secondary' }}>{variable.env_variable}</Typography>
                                        <Typography variant="body2" sx={{ mt: 1, mb: 2 }}>{variable.description || 'No description'}</Typography>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
                                            <Button size="small" onClick={() => {
                                                setEditingVariableId(variable.id);
                                                setVariableForm({
                                                    name: variable.name,
                                                    description: variable.description || '',
                                                    env_variable: variable.env_variable,
                                                    default_value: variable.default_value || '',
                                                    user_viewable: variable.user_viewable,
                                                    user_editable: variable.user_editable,
                                                    rules: variable.rules || '',
                                                    field_type: variable.field_type || 'text',
                                                    sort_order: variable.sort_order || 0,
                                                });
                                            }}>
                                                Edit
                                            </Button>
                                            <Button size="small" color="error" onClick={() => handleDeleteVariable(variable.id)}>
                                                Delete
                                            </Button>
                                        </Box>
                                    </Paper>
                                ))}
                                {!(image.image_variables || []).length && (
                                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>No variables configured for this image.</Typography>
                                )}
                            </Box>
                        </Paper>
                    </Grid>
                </Grid>
            )}

            {tab === 2 && (
                <Paper sx={{ p: 3, borderRadius: 2, border: '1px solid rgba(255,255,255,0.05)' }}>
                    <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Install Script</Typography>
                    <Box sx={{ display: 'grid', gap: 2 }}>
                        <TextField label="Container" value={scriptsForm.script_container} onChange={(event) => setScriptsForm({ ...scriptsForm, script_container: event.target.value })} />
                        <TextField label="Entrypoint" value={scriptsForm.script_entry} onChange={(event) => setScriptsForm({ ...scriptsForm, script_entry: event.target.value })} />
                        <TextField label="Install Script" multiline minRows={12} value={scriptsForm.script_install} onChange={(event) => setScriptsForm({ ...scriptsForm, script_install: event.target.value })} />
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <Button variant="contained" startIcon={<SaveIcon />} onClick={handleSaveScripts} disabled={saving}>
                                {saving ? 'Saving...' : 'Save Script'}
                            </Button>
                        </Box>
                    </Box>
                </Paper>
            )}
        </Box>
    );
};

export default AdminImageView;
