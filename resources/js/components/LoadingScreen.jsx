import React from 'react';
import { Box, CircularProgress, Typography, Fade } from '@mui/material';
import { Shield } from '@mui/icons-material';
import { motion } from 'framer-motion';

const LoadingScreen = () => {
    return (
        <Box 
            sx={{ 
                position: 'fixed', 
                inset: 0, 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'center', 
                bgcolor: 'background.default',
                zIndex: 9999
            }}
        >
            <Fade in={true} timeout={800}>
                <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                    <Box sx={{ position: 'relative', display: 'inline-flex', mb: 4 }}>
                        <CircularProgress 
                            size={80} 
                            thickness={2} 
                            sx={{ color: 'primary.main', opacity: 0.2 }} 
                            variant="determinate" 
                            value={100} 
                        />
                        <CircularProgress
                            size={80}
                            thickness={2}
                            sx={{
                                color: 'primary.main',
                                position: 'absolute',
                                left: 0,
                                circle: { strokeLinecap: 'round' }
                            }}
                        />
                        <Box
                            sx={{
                                top: 0,
                                left: 0,
                                bottom: 0,
                                right: 0,
                                position: 'absolute',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <Shield sx={{ fontSize: 32, color: 'primary.main' }} />
                        </Box>
                    </Box>
                    
                    <motion.div
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3 }}
                    >
                        <Typography 
                            variant="caption" 
                            sx={{ 
                                color: 'text.secondary', 
                                fontWeight: 800, 
                                letterSpacing: '0.3em', 
                                textTransform: 'uppercase' 
                            }}
                        >
                            Signal Initializing
                        </Typography>
                    </motion.div>
                </Box>
            </Fade>
        </Box>
    );
};

export default LoadingScreen;
