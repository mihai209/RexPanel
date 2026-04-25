import React from 'react';
import { Box, Tabs, Tab, Paper } from '@mui/material';
import { useNavigate, useLocation } from 'react-router-dom';
import {
    Person as PersonIcon,
    History as HistoryIcon,
    ColorLens as ThemeIcon,
    Redeem as RedeemIcon,
    Timer as TimerIcon,
    NotificationsActive as NotificationsIcon,
} from '@mui/icons-material';

const AccountLayout = ({ children }) => {
    const navigate = useNavigate();
    const location = useLocation();

    const tabs = [
        { label: 'Overview', path: '/account', icon: <PersonIcon sx={{ fontSize: 18 }} /> },
        { label: 'Rewards', path: '/account/rewards', icon: <RedeemIcon sx={{ fontSize: 18 }} /> },
        { label: 'AFK', path: '/account/afk', icon: <TimerIcon sx={{ fontSize: 18 }} /> },
        { label: 'Activity', path: '/account/activity', icon: <HistoryIcon sx={{ fontSize: 18 }} /> },
        { label: 'Notifications', path: '/account/notifications', icon: <NotificationsIcon sx={{ fontSize: 18 }} /> },
        { label: 'Themes', path: '/account/themes', icon: <ThemeIcon sx={{ fontSize: 18 }} /> },
    ];

    const currentTab = tabs.findIndex(t => t.path === location.pathname);

    return (
        <Box>
            <Paper
                elevation={0}
                sx={{
                    bgcolor: 'background.paper',
                    border: '1px solid rgba(255, 255, 255, 0.05)',
                    borderRadius: 2,
                    mb: 3,
                    overflow: 'hidden',
                }}
            >
                <Tabs
                    value={currentTab === -1 ? 0 : currentTab}
                    onChange={(_, idx) => navigate(tabs[idx].path)}
                    sx={{
                        px: 2,
                        '& .MuiTab-root': {
                            color: 'text.secondary',
                            fontWeight: 600,
                            fontSize: '0.875rem',
                            textTransform: 'none',
                            minHeight: 56,
                            gap: 1,
                        },
                        '& .Mui-selected': { color: 'primary.main' },
                        '& .MuiTabs-indicator': { bgcolor: 'primary.main' },
                    }}
                >
                    {tabs.map((tab) => (
                        <Tab key={tab.path} label={tab.label} icon={tab.icon} iconPosition="start" />
                    ))}
                </Tabs>
            </Paper>
            {children}
        </Box>
    );
};

export default AccountLayout;
