import React from 'react';
import { Box, Typography, Paper, Grid } from '@mui/material';
import { Dashboard as DashboardIcon } from '@mui/icons-material';

const AdminHome = () => {
    return (
        <Box>
            <Typography variant="h5" sx={{ fontWeight: 800, mb: 1, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                <DashboardIcon /> Admin Overview
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 4 }}>
                Welcome to the administration dashboard. System metrics and quick actions will appear here.
            </Typography>

            <Grid container spacing={3}>
                {['Users', 'Servers', 'Nodes', 'Allocations'].map((stat) => (
                    <Grid item xs={12} sm={6} md={3} key={stat}>
                        <Paper 
                            sx={{ 
                                p: 3, 
                                bgcolor: 'background.paper', 
                                border: '1px solid rgba(255,255,255,0.05)', 
                                borderRadius: 2 
                            }}
                        >
                            <Typography variant="subtitle2" sx={{ color: 'text.secondary', fontWeight: 600, textTransform: 'uppercase', fontSize: '0.75rem' }}>
                                Total {stat}
                            </Typography>
                            <Typography variant="h4" sx={{ fontWeight: 800, mt: 1, color: 'text.primary' }}>
                                0
                            </Typography>
                        </Paper>
                    </Grid>
                ))}
            </Grid>
        </Box>
    );
};

export default AdminHome;
