import React, { useState } from 'react';
import { 
    Box, 
    AppBar, 
    Toolbar, 
    Typography, 
    IconButton, 
    Drawer,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    useMediaQuery,
    useTheme,
    Tooltip
} from '@mui/material';
import { 
    Dashboard as DashboardIcon,
    People as UsersIcon,
    Dns as ServersIcon,
    Memory as NodesIcon,
    Public as LocationIcon,
    ExitToApp as ExitIcon,
    Menu as MenuIcon,
    Storage as StorageIcon,
    Inventory2 as PackageIcon,
    Security as SecurityIcon,
    Settings as SettingsIcon,
    Hub as RedisIcon,
    GppMaybe as CaptchaIcon,
    FolderOpen as MountsIcon,
    NotificationsActive as NotificationsIcon,
    Extension as ExtensionIcon,
    WorkspacePremium as RevenueIcon,
    LocalOffer as DealIcon,
    Redeem as RedeemIcon,
    QueryStats as ForecastIcon,
    Sensors as ConnectorLabIcon,
    HealthAndSafety as HealthChecksIcon,
    ReportProblem as IncidentIcon,
} from '@mui/icons-material';
import { useNavigate, useLocation, Outlet } from 'react-router-dom';
import { useAppSettings } from '../context/AppSettingsContext';

const drawerWidth = 240;

const AdminLayout = ({ children }) => {
    const { settings } = useAppSettings();
    const theme = useTheme();
    const isMobile = useMediaQuery(theme.breakpoints.down('md'));
    const navigate = useNavigate();
    const location = useLocation();
    
    const [mobileOpen, setMobileOpen] = useState(false);

    const handleDrawerToggle = () => setMobileOpen(!mobileOpen);

    const menuItems = [
        { label: 'Overview', path: '/admin', icon: <DashboardIcon /> },
        { label: 'Settings', path: '/admin/settings', icon: <SettingsIcon /> },
        { label: 'Revenue Plans', path: '/admin/revenue-plans', icon: <RevenueIcon /> },
        { label: 'Store Deals', path: '/admin/store/deals', icon: <DealIcon /> },
        { label: 'Redeem Codes', path: '/admin/store/redeem-codes', icon: <RedeemIcon /> },
        { label: 'Forecasting', path: '/admin/forecasting', icon: <ForecastIcon /> },
        { label: 'Captcha', path: '/admin/captcha', icon: <CaptchaIcon /> },
        { label: 'Redis', path: '/admin/redis', icon: <RedisIcon /> },
        { label: 'Mounts', path: '/admin/mounts', icon: <MountsIcon /> },
        { label: 'Locations', path: '/admin/locations', icon: <LocationIcon /> },
        { label: 'Users', path: '/admin/users', icon: <UsersIcon /> },
        { label: 'Servers', path: '/admin/servers', icon: <ServersIcon /> },
        { label: 'Nodes', path: '/admin/nodes', icon: <NodesIcon /> },
        { label: 'Databases', path: '/admin/databases', icon: <StorageIcon /> },
        { label: 'Incidents', path: '/admin/incidents', icon: <IncidentIcon /> },
        { label: 'Connector Lab', path: '/admin/connector-lab', icon: <ConnectorLabIcon /> },
        { label: 'Health Checks', path: '/admin/service-health-checks', icon: <HealthChecksIcon /> },
        { label: 'Packages', path: '/admin/packages', icon: <PackageIcon /> },
        { label: 'Notifications', path: '/admin/notifications', icon: <NotificationsIcon /> },
        { label: 'Extensions', path: '/admin/extensions', icon: <ExtensionIcon /> },
        { label: 'Auth Providers', path: '/admin/auth-providers', icon: <SecurityIcon /> },
    ];

    const drawer = (
        <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column', bgcolor: 'background.paper' }}>
            <Box sx={{ p: 3, pb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 800, fontFamily: 'Outfit', color: 'primary.main', display: 'flex', alignItems: 'center', gap: 1 }}>
                    <DashboardIcon sx={{ fontSize: 24 }} /> {settings.brandName} Admin
                </Typography>
            </Box>
            
            <List sx={{ px: 2, flexGrow: 1 }}>
                {menuItems.map((item) => {
                    const isActive = item.path === '/admin' 
                        ? location.pathname === '/admin' 
                        : location.pathname.startsWith(item.path);

                    return (
                        <ListItem key={item.path} disablePadding sx={{ mb: 0.5 }}>
                            <ListItemButton
                                onClick={() => { navigate(item.path); if (isMobile) setMobileOpen(false); }}
                                sx={{
                                    borderRadius: 1.5,
                                    bgcolor: isActive ? 'primary.main' : 'transparent',
                                    color: isActive ? '#fff' : 'text.secondary',
                                    '&:hover': {
                                        bgcolor: isActive ? 'primary.main' : 'rgba(255, 255, 255, 0.05)',
                                        color: isActive ? '#fff' : 'text.primary',
                                    }
                                }}
                            >
                                <ListItemIcon sx={{ color: 'inherit', minWidth: 40 }}>
                                    {item.icon}
                                </ListItemIcon>
                                <ListItemText 
                                    primary={item.label} 
                                    primaryTypographyProps={{ 
                                        fontSize: '0.875rem', 
                                        fontWeight: isActive ? 700 : 500 
                                    }} 
                                />
                            </ListItemButton>
                        </ListItem>
                    );
                })}
            </List>

            <Box sx={{ p: 2 }}>
                <Tooltip title="Return to Panel" placement="right">
                    <ListItemButton
                        onClick={() => navigate('/')}
                        sx={{
                            borderRadius: 1.5,
                            color: 'text.secondary',
                            bgcolor: 'rgba(255,255,255,0.02)',
                            border: '1px solid rgba(255,255,255,0.05)',
                            '&:hover': {
                                bgcolor: 'rgba(255,255,255,0.05)',
                                color: 'text.primary',
                            }
                        }}
                    >
                        <ListItemIcon sx={{ color: 'inherit', minWidth: 40 }}>
                            <ExitIcon />
                        </ListItemIcon>
                        <ListItemText primary="Exit Admin" primaryTypographyProps={{ fontSize: '0.875rem', fontWeight: 600 }} />
                    </ListItemButton>
                </Tooltip>
            </Box>
        </Box>
    );

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: 'background.default' }}>
            {/* Topbar for mobile */}
            <AppBar
                position="fixed"
                sx={{
                    width: { md: `calc(100% - ${drawerWidth}px)` },
                    ml: { md: `${drawerWidth}px` },
                    bgcolor: 'background.paper',
                    backgroundImage: 'none',
                    boxShadow: 'none',
                    borderBottom: '1px solid rgba(255, 255, 255, 0.05)',
                    display: { xs: 'block', md: 'none' }
                }}
            >
                <Toolbar>
                    <IconButton
                        color="inherit"
                        aria-label="open drawer"
                        edge="start"
                        onClick={handleDrawerToggle}
                        sx={{ mr: 2 }}
                    >
                        <MenuIcon color="primary" />
                    </IconButton>
                    <Typography variant="h6" noWrap sx={{ fontWeight: 700, fontFamily: 'Outfit' }}>
                        {settings.brandName} Admin
                    </Typography>
                </Toolbar>
            </AppBar>

            {/* Sidebar Desktop/Mobile */}
            <Box component="nav" sx={{ width: { md: drawerWidth }, flexShrink: { md: 0 } }}>
                <Drawer
                    variant={isMobile ? "temporary" : "permanent"}
                    open={isMobile ? mobileOpen : true}
                    onClose={handleDrawerToggle}
                    ModalProps={{ keepMounted: true }} // Better open performance on mobile.
                    sx={{
                        '& .MuiDrawer-paper': { 
                            boxSizing: 'border-box', 
                            width: drawerWidth, 
                            borderRight: '1px solid rgba(255,255,255,0.05)'
                        },
                    }}
                >
                    {drawer}
                </Drawer>
            </Box>

            {/* Main Content Area */}
            <Box
                component="main"
                sx={{
                    flexGrow: 1,
                    p: { xs: 2, md: 4 },
                    pt: { xs: 10, md: 4 },
                    width: { md: `calc(100% - ${drawerWidth}px)` }
                }}
            >
                {children || <Outlet />}
            </Box>
        </Box>
    );
};

export default AdminLayout;
