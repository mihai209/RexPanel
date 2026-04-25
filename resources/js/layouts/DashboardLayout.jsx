import React, { useState } from 'react';
import { 
    Box, 
    AppBar, 
    Toolbar, 
    Typography, 
    IconButton, 
    Avatar,
    Menu,
    MenuItem,
    ListItemIcon,
    ListItemText,
    Container,
    Tooltip,
    Drawer,
    List,
    ListItem,
    ListItemButton,
    Chip,
    useMediaQuery,
    useTheme as useMuiTheme
} from '@mui/material';
import { 
    ViewList as ServersIcon,
    AccountCircle as AccountIcon,
    AdminPanelSettings as AdminIcon,
    Logout as LogoutIcon,
    Menu as MenuIcon,
    Settings as SettingsIcon,
    AccountBalanceWallet as WalletIcon,
    Storefront as StoreIcon,
} from '@mui/icons-material';
import { useAuth } from '../context/AuthContext';
import { useAppSettings } from '../context/AppSettingsContext';
import { useNavigate, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';

const formatWalletCoins = (value) => {
    const amount = Number(value ?? 0);

    if (!Number.isFinite(amount)) {
        return '0';
    }

    if (Math.abs(amount) < 1000) {
        return String(Math.trunc(amount));
    }

    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(amount);
};

const DashboardLayout = ({ children }) => {
    const { user, logout } = useAuth();
    const { settings } = useAppSettings();
    const navigate = useNavigate();
    const location = useLocation();
    const [anchorEl, setAnchorEl] = useState(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const theme = useMuiTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('sm'));

    const handleMenuOpen = (e) => setAnchorEl(e.currentTarget);
    const handleMenuClose = () => setAnchorEl(null);

    const navItems = [
        { icon: <ServersIcon />, label: 'Servers', path: '/' },
        { icon: <StoreIcon />, label: 'Store', path: '/store' },
    ];

    if (user?.is_admin) {
        navItems.push(
            { icon: <SettingsIcon />, label: 'Admin Panel', path: '/admin' },
        );
    }

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh', bgcolor: 'transparent' }}>
            {/* Top Navigation */}
            <AppBar 
                position="fixed" 
                elevation={0}
                sx={{ 
                    bgcolor: 'background.paper',
                    borderBottom: '1px solid rgba(255, 255, 255, 0.06)'
                }}
            >
                <Toolbar sx={{ justifyContent: 'space-between' }}>
                    {/* Left: Brand */}
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        {isMobile && (
                            <IconButton 
                                color="inherit" 
                                edge="start" 
                                onClick={() => setMobileOpen(true)}
                                sx={{ mr: 1 }}
                            >
                                <MenuIcon />
                            </IconButton>
                        )}
                        <Typography 
                            variant="h6" 
                            sx={{ 
                                fontWeight: 700, 
                                fontFamily: 'Outfit', 
                                letterSpacing: '-0.02em',
                                color: '#e2e8f0'
                            }}
                        >
                            {settings.brandName}
                        </Typography>
                    </Box>

                    {/* Right: Nav Icons */}
                    <Box sx={{ display: { xs: 'none', sm: 'flex' }, alignItems: 'center', gap: 0.5 }}>
                        <Chip
                            icon={<WalletIcon sx={{ fontSize: 16 }} />}
                            label={`${formatWalletCoins(user?.coins)} Coins`}
                            size="small"
                            onClick={() => navigate('/account/rewards')}
                            sx={{
                                mr: 1,
                                bgcolor: 'rgba(54, 211, 153, 0.12)',
                                color: '#9ae6b4',
                                border: '1px solid rgba(54, 211, 153, 0.18)',
                                '& .MuiChip-icon': { color: '#36d399' },
                            }}
                        />
                        {navItems.map((item, i) => {
                            const isActive = item.path === '/'
                                ? location.pathname === '/'
                                : location.pathname.startsWith(item.path);
                            return (
                                <Tooltip key={i} title={item.label}>
                                    <IconButton
                                        onClick={() => item.path && navigate(item.path)}
                                        sx={{
                                            color: isActive ? '#36d399' : 'rgba(255, 255, 255, 0.5)',
                                            '&:hover': { color: '#e2e8f0' },
                                            transition: 'color 0.2s'
                                        }}
                                    >
                                        {item.icon}
                                    </IconButton>
                                </Tooltip>
                            );
                        })}

                        <NotificationBell />

                        <IconButton onClick={handleMenuOpen} sx={{ ml: 1 }}>
                            <Avatar 
                                src={user?.avatar_url}
                                sx={{ 
                                    width: 32, 
                                    height: 32, 
                                    bgcolor: '#2a3654',
                                    fontSize: '0.8rem',
                                    fontWeight: 700,
                                    color: '#36d399',
                                    border: '2px solid rgba(54, 211, 153, 0.3)'
                                }}
                            >
                                {user?.username?.[0]?.toUpperCase()}
                            </Avatar>
                        </IconButton>

                        <Menu
                            anchorEl={anchorEl}
                            open={Boolean(anchorEl)}
                            onClose={handleMenuClose}
                            PaperProps={{
                                sx: {
                                    bgcolor: 'background.paper',
                                    border: '1px solid rgba(255, 255, 255, 0.08)',
                                    mt: 1,
                                    minWidth: 180,
                                }
                            }}
                        >
                            <MenuItem disabled>
                                <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.4)', fontWeight: 600 }}>
                                    {user?.email}
                                </Typography>
                            </MenuItem>
                            {user?.is_admin && (
                                <MenuItem onClick={() => { handleMenuClose(); navigate('/admin'); }}>
                                    <ListItemIcon><AdminIcon sx={{ color: 'primary.main' }} /></ListItemIcon>
                                    <ListItemText primaryTypographyProps={{ fontSize: '0.875rem' }}>Admin Panel</ListItemText>
                                </MenuItem>
                            )}
                            <MenuItem onClick={() => { handleMenuClose(); navigate('/account'); }}>
                                <ListItemIcon><AccountIcon sx={{ color: 'rgba(255, 255, 255, 0.7)' }} /></ListItemIcon>
                                <ListItemText primaryTypographyProps={{ fontSize: '0.875rem' }}>Account</ListItemText>
                            </MenuItem>
                            <MenuItem onClick={() => { handleMenuClose(); logout(); }}>
                                <ListItemIcon><LogoutIcon sx={{ color: '#f87171' }} /></ListItemIcon>
                                <ListItemText primaryTypographyProps={{ fontSize: '0.875rem' }}>Sign Out</ListItemText>
                            </MenuItem>
                        </Menu>
                    </Box>
                </Toolbar>
            </AppBar>

            {/* Mobile Drawer */}
            <Drawer 
                open={mobileOpen} 
                onClose={() => setMobileOpen(false)}
                PaperProps={{ sx: { bgcolor: 'background.paper', width: 260 } }}
            >
                <Box sx={{ p: 2 }}>
                    <Typography variant="h6" sx={{ fontWeight: 700, fontFamily: 'Outfit', color: 'text.primary', mb: 2 }}>
                        {settings.brandName}
                    </Typography>
                </Box>
                <List>
                    <ListItem>
                        <Chip
                            icon={<WalletIcon sx={{ fontSize: 16 }} />}
                            label={`${formatWalletCoins(user?.coins)} Coins`}
                            size="small"
                            sx={{
                                bgcolor: 'rgba(54, 211, 153, 0.12)',
                                color: '#9ae6b4',
                                border: '1px solid rgba(54, 211, 153, 0.18)',
                                '& .MuiChip-icon': { color: '#36d399' },
                            }}
                        />
                    </ListItem>
                    {navItems.map((item, i) => (
                        <ListItem key={i} disablePadding>
                            <ListItemButton 
                                onClick={() => { if (item.path) navigate(item.path); setMobileOpen(false); }}
                                sx={{ 
                                    color: (item.path === '/' ? location.pathname === '/' : location.pathname.startsWith(item.path)) ? '#36d399' : 'rgba(255,255,255,0.5)',
                                    '&:hover': { bgcolor: 'rgba(255,255,255,0.05)' }
                                }}
                            >
                                <ListItemIcon sx={{ color: 'inherit', minWidth: 40 }}>{item.icon}</ListItemIcon>
                                <ListItemText primary={item.label} primaryTypographyProps={{ fontSize: '0.875rem', fontWeight: 500 }} />
                            </ListItemButton>
                        </ListItem>
                    ))}
                    <ListItem disablePadding>
                        <ListItemButton onClick={logout} sx={{ color: '#f87171' }}>
                            <ListItemIcon sx={{ color: 'inherit', minWidth: 40 }}><LogoutIcon /></ListItemIcon>
                            <ListItemText primary="Sign Out" primaryTypographyProps={{ fontSize: '0.875rem' }} />
                        </ListItemButton>
                    </ListItem>
                </List>
            </Drawer>

            {/* Main Content */}
            <Box component="main" sx={{ flexGrow: 1, mt: '64px' }}>
                <Container maxWidth="lg" sx={{ py: 4 }}>
                    {children}
                </Container>
            </Box>

            {/* Footer */}
            <Box sx={{ py: 3, textAlign: 'center', borderTop: '1px solid rgba(255, 255, 255, 0.04)' }}>
                <Typography variant="caption" sx={{ color: 'rgba(255, 255, 255, 0.2)', fontWeight: 500 }}>
                    © 2025 - 2026 {settings.brandName} Software
                </Typography>
            </Box>
        </Box>
    );
};

export default DashboardLayout;
