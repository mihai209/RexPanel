import React, { useState } from 'react';
import {
    Box, Paper, Typography, Button, Grid, CircularProgress, Alert
} from '@mui/material';
import { ColorLens as ThemeIcon, CheckCircle as CheckIcon } from '@mui/icons-material';
import { useAuth } from '../../context/AuthContext';
import client from '../../api/client';

const themeOptions = [
  { id: "default", name: "Slate Blue (Default)", description: "The standard dark theme with slate blue accents.", preview: null },
  { id: "ace", name: "Ace", description: "Minimalist dark theme.", preview: null },
  { id: "azure", name: "Azure", description: "Deep blue professional theme.", preview: "https://wallpapers.com/images/featured/azure-dw6cswed15qk49l2.jpg" },
  { id: "dino-cartoon-fun", name: "Dino Cartoon Fun", description: "Playful dinosaur themed interface.", preview: null },
  { id: "discord-l", name: "Discord L", description: "Inspired by the popular chat platform.", preview: "https://cdn.prod.website-files.com/5f9072399b2640f14d6a2bf4/67d0c4e0932bb59c7f6271e5_2024_10_DiscordBlog_UsingDiscord-1.png" },
  { id: "easter", name: "Easter", description: "Spring and easter themed colors.", preview: "/themes/easter.png" },
  { id: "forest-night", name: "Forest Night", description: "Quiet forest vibes.", preview: null },
  { id: "gothic", name: "Gothic", description: "Dark and mysterious gothic aesthetic.", preview: "https://t4.ftcdn.net/jpg/02/00/99/09/360_F_200990953_ZUQhJDEQGCmJ10H9mLq8XjEckDQQjau9.jpg" },
  { id: "hacker", name: "Hacker", description: "Matrix inspired hacker theme.", preview: "/themes/hacker.png" },
  { id: "jurassic-summer", name: "Jurassic Summer", description: "Jungle and dinosaur summer theme.", preview: null },
  { id: "light", name: "Light", description: "Clean and bright light theme.", preview: null },
  { id: "m-bunicii", name: "M Bunicii", description: "A custom custom theme ported from CPanel.", preview: "/themes/m-bunicii.png" },
  { id: "minecraft", name: "Minecraft", description: "Blocky adventure theme.", preview: null },
  { id: "minimal-summer-clean", name: "Minimal Summer Clean", description: "Clean summer aesthetic.", preview: null },
  { id: "neon-circuit", name: "Neon Circuit", description: "Cyberpunk neon lights.", preview: null },
  { id: "ocean-deep-sea", name: "Ocean Deep Sea", description: "Depths of the ocean.", preview: null },
  { id: "retro-synth", name: "Retro Synth", description: "80s retro synthwave theme.", preview: null },
  { id: "school-again", name: "School Again", description: "Academic and studious theme.", preview: null },
  { id: "sky-islands-fantasy", name: "Sky Islands Fantasy", description: "Floating islands in the sky.", preview: "https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=1280&q=60&fm=webp" },
  { id: "sunset-gamer", name: "Sunset Gamer", description: "Warm sunset gaming vibes.", preview: null },
  { id: "super-dark", name: "Super Dark", description: "Extreme dark mode.", preview: null },
  { id: "tropical-island", name: "Tropical Island", description: "Paradise island vibes.", preview: null },
  { id: "winter-time", name: "Winter Time", description: "Cold and snowy winter theme.", preview: null },
  { id: "zombie-apocalipse", name: "Zombie Apocalipse", description: "Survival in a post-apocalyptic world.", preview: null }
];

const AccountThemes = () => {
    const { user, setUser } = useAuth();
    const [loadingTheme, setLoadingTheme] = useState(null);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const currentTheme = user?.theme || 'default';

    const handleSelectTheme = async (themeId) => {
        if (themeId === currentTheme) return;
        
        setLoadingTheme(themeId);
        setError('');
        setSuccess('');

        try {
            const res = await client.put('/v1/account/theme', { theme: themeId });
            setUser(res.data.user);
            setSuccess(res.data.message);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to apply theme.');
        } finally {
            setLoadingTheme(null);
        }
    };

    return (
        <Box>
            <Paper elevation={0} sx={{ bgcolor: 'background.paper', border: '1px solid rgba(255,255,255,0.05)', borderRadius: 2, p: 4 }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 3, color: 'text.primary', display: 'flex', alignItems: 'center', gap: 1 }}>
                    <ThemeIcon sx={{ fontSize: 20 }} /> Visual Themes
                </Typography>

                <Typography variant="body2" sx={{ color: 'text.secondary', mb: 4 }}>
                    Customize the look and feel of your control panel. 
                    Your selected theme is applied instantly across your account.
                </Typography>

                {error && <Alert severity="error" variant="filled" sx={{ mb: 3, bgcolor: '#ef4444' }}>{error}</Alert>}
                {success && <Alert severity="success" sx={{ mb: 3, bgcolor: 'rgba(54, 211, 153, 0.15)', color: '#36d399', border: '1px solid rgba(54, 211, 153, 0.3)', '& .MuiAlert-icon': { color: '#36d399' } }}>{success}</Alert>}

                <Grid container spacing={3}>
                    {themeOptions.map((th) => {
                        const isActive = currentTheme === th.id;
                        const isLoading = loadingTheme === th.id;

                        return (
                            <Grid item xs={12} sm={6} md={4} key={th.id}>
                                <Paper 
                                    elevation={0}
                                    sx={{ 
                                        borderRadius: 2, 
                                        overflow: 'hidden',
                                        border: '2px solid',
                                        borderColor: isActive ? 'primary.main' : 'rgba(255,255,255,0.05)',
                                        bgcolor: 'background.default',
                                        transition: 'all 0.2s',
                                        '&:hover': {
                                            borderColor: isActive ? 'primary.main' : 'rgba(255,255,255,0.15)'
                                        }
                                    }}
                                >
                                    <Box 
                                        sx={{ 
                                            height: 140, 
                                            bgcolor: 'rgba(0,0,0,0.2)', 
                                            display: 'flex', 
                                            alignItems: 'center', 
                                            justifyContent: 'center',
                                            backgroundImage: th.preview ? `url(${th.preview})` : 'none',
                                            backgroundSize: 'cover',
                                            backgroundPosition: 'center',
                                            borderBottom: '1px solid rgba(255,255,255,0.05)'
                                        }}
                                    >
                                        {!th.preview && (
                                            <Typography variant="overline" sx={{ color: 'rgba(255,255,255,0.2)', fontWeight: 700 }}>
                                                No Preview
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ p: 2 }}>
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700, color: 'text.primary', mb: 0.5, display: 'flex', alignItems: 'center', gap: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                            {th.name}
                                            {isActive && <CheckIcon sx={{ fontSize: 16, color: 'primary.main' }} />}
                                        </Typography>
                                        <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.75rem', mb: 2, height: 40, overflow: 'hidden' }}>
                                            {th.description}
                                        </Typography>

                                        <Button 
                                            variant={isActive ? "outlined" : "contained"}
                                            fullWidth
                                            size="small"
                                            disabled={isActive || isLoading}
                                            onClick={() => handleSelectTheme(th.id)}
                                            sx={{ 
                                                py: 0.5, 
                                                bgcolor: isActive ? 'transparent' : 'primary.main',
                                                borderColor: isActive ? 'rgba(255,255,255,0.1)' : 'transparent',
                                                color: isActive ? 'text.secondary' : '#fff',
                                            }}
                                        >
                                            {isLoading ? <CircularProgress size={16} color="inherit" /> : isActive ? 'Active' : 'Apply'}
                                        </Button>
                                    </Box>
                                </Paper>
                            </Grid>
                        );
                    })}
                </Grid>
            </Paper>
        </Box>
    );
};

export default AccountThemes;
