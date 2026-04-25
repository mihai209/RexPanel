import React, { useState, useEffect } from 'react';
import { 
    Box, Typography, Paper, Grid, TextField, Button, IconButton,
    Alert, CircularProgress, MenuItem, Switch, FormControlLabel,
    InputAdornment, Snackbar, Divider, RadioGroup, Radio, FormControl, FormLabel,
    useTheme, alpha
} from '@mui/material';
import { 
    ArrowBack as BackIcon, 
    Save as SaveIcon,
    Dns as DnsIcon,
    LocationOn as LocationIcon,
    Storage as StorageIcon,
    Settings as SettingsIcon,
    Add as AddIcon,
    Lan as IpIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import client from '../../api/client';

const AdminNodeCreate = () => {
    const navigate = useNavigate();
    const theme = useTheme();
    
    const [locations, setLocations] = useState([]);
    const [loadingLocations, setLoadingLocations] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [snackbar, setSnackbar] = useState('');

    const [form, setForm] = useState({
        name: '',
        description: '',
        location_id: '',
        is_public: true,
        fqdn: '',
        daemon_port: 8080,
        daemon_sftp_port: 2022,
        memory_limit: 8192,
        memory_overallocate: 0,
        disk_limit: 51200,
        disk_overallocate: 0,
        daemon_base: '/var/lib/ra-panel/volumes',
        use_ssl: true,
        behind_proxy: false
    });

    useEffect(() => {
        client.get('/v1/admin/locations')
            .then(res => {
                setLocations(res.data);
                if (res.data.length > 0) {
                    setForm(prev => ({ ...prev, location_id: res.data[0].id }));
                }
            })
            .catch(() => setError('Failed to fetch locations.'))
            .finally(() => setLoadingLocations(false));
    }, []);

    if (!loadingLocations && locations.length === 0) {
        return (
            <Box sx={{ p: 4, textAlign: 'center' }}>
                <Paper sx={{ p: 10, borderRadius: 3, border: '1px solid rgba(244,67,54,0.3)', bgcolor: alpha('#f44336', 0.05) }}>
                    <LocationIcon sx={{ fontSize: 80, color: 'error.main', mb: 3 }} />
                    <Typography variant="h4" sx={{ fontWeight: 900, mb: 1 }}>Location Required</Typography>
                    <Typography variant="body1" sx={{ color: 'text.secondary', mb: 4, maxWidth: 500, mx: 'auto' }}>
                        You must create a location before you can add a new node. Locations help organize your infrastructure by region.
                    </Typography>
                    <Button 
                        variant="contained" size="large" 
                        onClick={() => navigate('/admin/locations/create')}
                        sx={{ fontWeight: 900, px: 4 }}
                    >
                        Create Your First Location
                    </Button>
                </Paper>
            </Box>
        );
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            const res = await client.post('/v1/admin/nodes', form);
            setSnackbar('Node created successfully!');
            setTimeout(() => navigate(`/admin/nodes/${res.data.id}/overview`), 1000);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create node. Please check all fields.');
        } finally {
            setSaving(false);
        }
    };

    const cardStyle = {
        borderRadius: 1,
        bgcolor: '#232b35', // CPanel-style dark card background
        border: '1px solid rgba(255,255,255,0.05)',
        overflow: 'hidden',
        boxShadow: '0 2px 4px rgba(0,0,0,0.2)'
    };

    const headerStyle = {
        px: 2, py: 1.5,
        bgcolor: '#232b35',
        borderTop: '2px solid #57c7ff', // The light blue top border from screenshot
        borderBottom: '1px solid rgba(255,255,255,0.05)'
    };

    const inputStyle = {
        '& .MuiOutlinedInput-root': {
            borderRadius: 1,
            bgcolor: '#1a2028', // Even darker for inputs
            '& fieldset': { borderColor: 'rgba(255,255,255,0.1)' },
            '&:hover fieldset': { borderColor: 'rgba(255,255,255,0.2)' },
            '&.Mui-focused fieldset': { borderColor: '#57c7ff' }
        },
        '& .MuiInputBase-input': { fontSize: '0.85rem', py: 1.2 },
        '& .MuiInputLabel-root': { color: '#8e99a3', fontWeight: 700, fontSize: '0.8rem' },
        '& .MuiFormHelperText-root': { color: '#6b7280', fontSize: '0.65rem', lineHeight: 1.2, mt: 0.5, letterSpacing: '0.01em' }
    };

    return (
        <Box sx={{ maxWidth: '1400px', mx: 'auto', pb: 5 }}>
            {/* Header Area */}
            <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 800, letterSpacing: '-0.01em', mb: 0.5 }}>New Node</Typography>
                    <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.8rem' }}>Create a new local or remote node for servers to be installed to.</Typography>
                </Box>
                <Box sx={{ textAlign: 'right' }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, fontSize: '0.7rem', color: 'text.disabled', mb: 1 }}>
                        Admin &gt; Nodes &gt; <span style={{ color: '#57c7ff' }}>New</span>
                    </Box>
                </Box>
            </Box>

            {error && <Alert severity="error" sx={{ mb: 3, borderRadius: 1, bgcolor: alpha(theme.palette.error.main, 0.1), border: '1px solid rgba(244,67,54,0.2)' }}>{error}</Alert>}

            <form onSubmit={handleSubmit}>
                <Grid container spacing={2}>
                    {/* LEFT COLUMN: Basic Details */}
                    <Grid item xs={6}>
                        <Paper sx={cardStyle}>
                            <Box sx={headerStyle}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 800, fontSize: '0.85rem', color: '#fff' }}>Basic Details</Typography>
                            </Box>
                            <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Name</Typography>
                                    <TextField
                                        fullWidth size="small" required
                                        value={form.name} onChange={e => setForm({...form, name: e.target.value})}
                                        sx={inputStyle}
                                    />
                                    <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block' }}>
                                        Character limits: <code>a-zA-Z0-9_.-</code> and <code>[Space]</code> (min 1, max 100 characters).
                                    </Typography>
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Description</Typography>
                                    <TextField
                                        fullWidth size="small" multiline rows={2}
                                        value={form.description} onChange={e => setForm({...form, description: e.target.value})}
                                        sx={inputStyle}
                                    />
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Location</Typography>
                                    <TextField
                                        select fullWidth size="small" required
                                        value={form.location_id} onChange={e => setForm({...form, location_id: e.target.value})}
                                        disabled={loadingLocations} sx={inputStyle}
                                    >
                                        {locations.map(loc => (
                                            <MenuItem key={loc.id} value={loc.id}>{loc.name}</MenuItem>
                                        ))}
                                    </TextField>
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Node Visibility</Typography>
                                    <RadioGroup row value={form.is_public ? '1' : '0'} onChange={e => setForm({...form, is_public: e.target.value === '1'})} sx={{ gap: 2 }}>
                                        <FormControlLabel value="1" control={<Radio size="small" color="success" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Public</Typography>} />
                                        <FormControlLabel value="0" control={<Radio size="small" color="error" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Private</Typography>} />
                                    </RadioGroup>
                                    <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block' }}>
                                        By setting a node to <code>private</code> you will be denying the ability to auto-deploy to this node.
                                    </Typography>
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>FQDN</Typography>
                                    <TextField
                                        fullWidth size="small" required
                                        value={form.fqdn} onChange={e => setForm({...form, fqdn: e.target.value})}
                                        sx={inputStyle}
                                    />
                                    <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block', lineHeight: 1.1 }}>
                                        Please enter domain name (e.g <code>node.example.com</code>) to be used for connecting to the daemon. An IP address may be used <em>only</em> if you are not using SSL for this node.
                                    </Typography>
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Communicate Over SSL</Typography>
                                    <RadioGroup row value={form.use_ssl ? '1' : '0'} onChange={e => setForm({...form, use_ssl: e.target.value === '1'})} sx={{ gap: 2 }}>
                                        <FormControlLabel value="1" control={<Radio size="small" color="success" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Use SSL Connection</Typography>} />
                                        <FormControlLabel value="0" control={<Radio size="small" color="error" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Use HTTP Connection</Typography>} />
                                    </RadioGroup>
                                    <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block', lineHeight: 1.1 }}>
                                        In most cases you should select to use a SSL connection. If using an IP Address or you do not wish to use SSL at all, select a HTTP connection.
                                    </Typography>
                                </Box>

                                <Box>
                                    <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Behind Proxy</Typography>
                                    <RadioGroup row value={form.behind_proxy ? '1' : '0'} onChange={e => setForm({...form, behind_proxy: e.target.value === '1'})} sx={{ gap: 2 }}>
                                        <FormControlLabel value="0" control={<Radio size="small" color="success" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Not Behind Proxy</Typography>} />
                                        <FormControlLabel value="1" control={<Radio size="small" color="info" sx={{ py: 0 }} />} label={<Typography variant="body2" sx={{ fontWeight: 700 }}>Behind Proxy</Typography>} />
                                    </RadioGroup>
                                    <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block', lineHeight: 1.1 }}>
                                        If you are running the daemon behind a proxy such as Cloudflare, select this to have the daemon skip looking for certificates on boot.
                                    </Typography>
                                </Box>
                            </Box>
                        </Paper>
                    </Grid>

                    {/* RIGHT COLUMN: Configuration */}
                    <Grid item xs={6}>
                        <Paper sx={cardStyle}>
                            <Box sx={headerStyle}>
                                <Typography variant="subtitle2" sx={{ fontWeight: 800, fontSize: '0.85rem', color: '#fff' }}>Configuration</Typography>
                            </Box>
                            <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                <Grid container spacing={2}>
                                    <Grid item xs={12}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon Server File Directory</Typography>
                                        <TextField
                                            fullWidth size="small" required
                                            value={form.daemon_base} onChange={e => setForm({...form, daemon_base: e.target.value})}
                                            sx={inputStyle}
                                        />
                                        <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', mt: 0.2, display: 'block' }}>
                                            Enter the directory where server files should be stored. Usually <code>/var/lib/ra-panel/volumes</code>.
                                        </Typography>
                                    </Grid>

                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Total Memory</Typography>
                                        <TextField
                                            fullWidth size="small" type="number" required
                                            value={form.memory_limit} onChange={e => setForm({...form, memory_limit: e.target.value})}
                                            InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& .MuiTypography-root': { color: 'text.disabled', fontSize: '0.7rem' } }}>MiB</InputAdornment> }}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Memory Over Allocation</Typography>
                                        <TextField
                                            fullWidth size="small" type="number"
                                            value={form.memory_overallocate} onChange={e => setForm({...form, memory_overallocate: e.target.value})}
                                            InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& .MuiTypography-root': { color: 'text.disabled', fontSize: '0.7rem' } }}>%</InputAdornment> }}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={12} sx={{ pt: '0 !important' }}>
                                        <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', display: 'block', lineHeight: 1.1, mt: 0.5 }}>
                                            Enter the total amount of memory available for new servers. If you would like to allow overallocation of memory enter the percentage that you want to allow. Entering <code>0</code> will prevent creating new servers if it would put the node over the limit.
                                        </Typography>
                                    </Grid>

                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Total Disk Space</Typography>
                                        <TextField
                                            fullWidth size="small" type="number" required
                                            value={form.disk_limit} onChange={e => setForm({...form, disk_limit: e.target.value})}
                                            InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& .MuiTypography-root': { color: 'text.disabled', fontSize: '0.7rem' } }}>MiB</InputAdornment> }}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Disk Over Allocation</Typography>
                                        <TextField
                                            fullWidth size="small" type="number"
                                            value={form.disk_overallocate} onChange={e => setForm({...form, disk_overallocate: e.target.value})}
                                            InputProps={{ endAdornment: <InputAdornment position="end" sx={{ '& .MuiTypography-root': { color: 'text.disabled', fontSize: '0.7rem' } }}>%</InputAdornment> }}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={12} sx={{ pt: '0 !important' }}>
                                        <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', display: 'block', lineHeight: 1.1, mt: 0.5 }}>
                                            Enter the total amount of disk space available for new servers. If you would like to allow overallocation of disk space enter the percentage that you want to allow. Entering <code>0</code> will prevent creating new servers if it would put the node over the limit.
                                        </Typography>
                                    </Grid>

                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon Port</Typography>
                                        <TextField
                                            fullWidth size="small" type="number" required
                                            value={form.daemon_port} onChange={e => setForm({...form, daemon_port: e.target.value})}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" sx={{ fontWeight: 800, color: '#8e99a3', mb: 0.2, display: 'block' }}>Daemon SFTP Port</Typography>
                                        <TextField
                                            fullWidth size="small" type="number" required
                                            value={form.daemon_sftp_port} onChange={e => setForm({...form, daemon_sftp_port: e.target.value})}
                                            sx={inputStyle}
                                        />
                                    </Grid>
                                    <Grid item xs={12} sx={{ pt: '0 !important' }}>
                                        <Typography variant="caption" sx={{ color: '#6b7280', fontSize: '0.65rem', display: 'block', lineHeight: 1.1, mt: 0.5 }}>
                                            The daemon runs its own SFTP management container and does not use the SSHd process on the main physical server. <strong>Do not use the same port that you have assigned for your physical server's SSH process.</strong>
                                        </Typography>
                                    </Grid>
                                </Grid>

                                <Box sx={{ mt: 1, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
                                    <Button 
                                        type="submit" variant="contained" 
                                        disabled={saving} size="small"
                                        sx={{ 
                                            bgcolor: '#28a745', 
                                            '&:hover': { bgcolor: '#218838' }, 
                                            fontWeight: 700, borderRadius: 1, px: 3, py: 0.8,
                                            textTransform: 'none'
                                        }}
                                    >
                                        {saving ? <CircularProgress size={20} color="inherit" /> : 'Create Node'}
                                    </Button>
                                </Box>
                            </Box>
                        </Paper>
                    </Grid>
                </Grid>
            </form>

            <Snackbar
                open={Boolean(snackbar)} autoHideDuration={3000}
                onClose={() => setSnackbar('')} message={snackbar}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
            />
        </Box>
    );
};

export default AdminNodeCreate;
