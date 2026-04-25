import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { AppSettingsProvider } from './context/AppSettingsContext';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { getTheme } from './theme';
import { lazy, Suspense, useEffect, useMemo } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

// Components & Pages (Lazy Loaded)
import LoadingScreen from './components/LoadingScreen';
const AuthPage = lazy(() => import('./pages/AuthPage'));
const DashboardLayout = lazy(() => import('./layouts/DashboardLayout'));
const DashboardHome = lazy(() => import('./pages/DashboardHome'));
const UserServerConsolePage = lazy(() => import('./pages/server/UserServerConsolePage'));
const StoreOverview = lazy(() => import('./pages/store/StoreOverview'));
const StoreDeals = lazy(() => import('./pages/store/StoreDeals'));
const StoreRedeem = lazy(() => import('./pages/store/StoreRedeem'));
const AccountLayout = lazy(() => import('./layouts/AccountLayout'));
const AccountOverview = lazy(() => import('./pages/account/AccountOverview'));
const AccountRewards = lazy(() => import('./pages/account/AccountRewards'));
const AccountAfk = lazy(() => import('./pages/account/AccountAfk'));
const AccountActivity = lazy(() => import('./pages/account/AccountActivity'));
const AccountThemes = lazy(() => import('./pages/account/AccountThemes'));
const AccountNotifications = lazy(() => import('./pages/account/AccountNotifications'));

const AdminRoute = lazy(() => import('./components/AdminRoute'));
const AdminLayout = lazy(() => import('./layouts/AdminLayout'));
const AdminHome = lazy(() => import('./pages/admin/AdminHome'));
const AdminAuthProviders = lazy(() => import('./pages/admin/AdminAuthProviders'));
const AdminCaptcha = lazy(() => import('./pages/admin/AdminCaptcha'));
const AdminMounts = lazy(() => import('./pages/admin/AdminMounts'));
const AdminSettings = lazy(() => import('./pages/admin/AdminSettings'));
const AdminRevenuePlans = lazy(() => import('./pages/admin/AdminRevenuePlans'));
const AdminStoreDeals = lazy(() => import('./pages/admin/AdminStoreDeals'));
const AdminRedeemCodes = lazy(() => import('./pages/admin/AdminRedeemCodes'));
const AdminForecasting = lazy(() => import('./pages/admin/AdminForecasting'));
const AdminRedis = lazy(() => import('./pages/admin/AdminRedis'));
const AdminUsers = lazy(() => import('./pages/admin/AdminUsers'));
const AdminUserCreate = lazy(() => import('./pages/admin/AdminUserCreate'));
const AdminUserEdit = lazy(() => import('./pages/admin/AdminUserEdit'));
const AdminLocations = lazy(() => import('./pages/admin/AdminLocations'));
const AdminLocationCreate = lazy(() => import('./pages/admin/AdminLocationCreate'));
const AdminLocationEdit = lazy(() => import('./pages/admin/AdminLocationEdit'));
const AdminNodes = lazy(() => import('./pages/admin/AdminNodes'));
const AdminNodeCreate = lazy(() => import('./pages/admin/AdminNodeCreate'));
const AdminNodeEdit = lazy(() => import('./pages/admin/AdminNodeEdit'));
const AdminNodeView = lazy(() => import('./pages/admin/AdminNodeView'));
const AdminServers = lazy(() => import('./pages/admin/AdminServers'));
const AdminServerCreate = lazy(() => import('./pages/admin/AdminServerCreate'));
const AdminServerEdit = lazy(() => import('./pages/admin/AdminServerEdit'));
const AdminDatabases = lazy(() => import('./pages/admin/AdminDatabases'));
const AdminIncidents = lazy(() => import('./pages/admin/AdminIncidents'));
const AdminPackages = lazy(() => import('./pages/admin/AdminPackages'));
const AdminPackageView = lazy(() => import('./pages/admin/AdminPackageView'));
const AdminImageView = lazy(() => import('./pages/admin/AdminImageView'));
const AdminImages = lazy(() => import('./pages/admin/AdminImages'));
const AdminNotifications = lazy(() => import('./pages/admin/AdminNotifications'));
const AdminConnectorLab = lazy(() => import('./pages/admin/AdminConnectorLab'));
const AdminExtensions = lazy(() => import('./pages/admin/AdminExtensions'));
const AdminServiceHealthChecks = lazy(() => import('./pages/admin/AdminServiceHealthChecks'));

const MainRouter = () => {
    const { isAuthenticated, loading } = useAuth();

    return (
        <AnimatePresence mode="wait">
            {loading ? (
                <motion.div
                    key="loading"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.3 }}
                >
                    <LoadingScreen />
                </motion.div>
            ) : (
                <motion.div
                    key={isAuthenticated ? 'dashboard' : 'auth'}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.4 }}
                    style={{ height: '100%' }}
                >
                    <Suspense fallback={<LoadingScreen />}>
                        <Routes>
                            {isAuthenticated ? (
                                <>
                                    <Route path="/" element={<DashboardLayout><DashboardHome /></DashboardLayout>} />
                                    <Route path="/server/:containerId" element={
                                        <DashboardLayout>
                                            <UserServerConsolePage />
                                        </DashboardLayout>
                                    } />
                                    <Route path="/store" element={
                                        <DashboardLayout>
                                            <StoreOverview />
                                        </DashboardLayout>
                                    } />
                                    <Route path="/store/deals" element={
                                        <DashboardLayout>
                                            <StoreDeals />
                                        </DashboardLayout>
                                    } />
                                    <Route path="/store/redeem" element={
                                        <DashboardLayout>
                                            <StoreRedeem />
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountOverview /></AccountLayout>
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account/themes" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountThemes /></AccountLayout>
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account/rewards" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountRewards /></AccountLayout>
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account/afk" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountAfk /></AccountLayout>
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account/activity" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountActivity /></AccountLayout>
                                        </DashboardLayout>
                                    } />
                                    <Route path="/account/notifications" element={
                                        <DashboardLayout>
                                            <AccountLayout><AccountNotifications /></AccountLayout>
                                        </DashboardLayout>
                                    } />

                                    {/* Admin Area */}
                                    <Route path="/admin" element={
                                        <AdminRoute>
                                            <AdminLayout />
                                        </AdminRoute>
                                    }>
                                        <Route index element={<AdminHome />} />
                                        <Route path="settings" element={<AdminSettings />} />
                                        <Route path="revenue-plans" element={<AdminRevenuePlans />} />
                                        <Route path="store/deals" element={<AdminStoreDeals />} />
                                        <Route path="store/redeem-codes" element={<AdminRedeemCodes />} />
                                        <Route path="forecasting" element={<AdminForecasting />} />
                                        <Route path="captcha" element={<AdminCaptcha />} />
                                        <Route path="redis" element={<AdminRedis />} />
                                        <Route path="mounts" element={<AdminMounts />} />
                                        <Route path="auth-providers" element={<AdminAuthProviders />} />
                                        <Route path="users" element={<AdminUsers />} />
                                        <Route path="users/create" element={<AdminUserCreate />} />
                                        <Route path="users/:id" element={<AdminUserEdit />} />
                                        <Route path="locations" element={<AdminLocations />} />
                                        <Route path="locations/create" element={<AdminLocationCreate />} />
                                        <Route path="locations/:id/edit" element={<AdminLocationEdit />} />
                                        <Route path="nodes" element={<AdminNodes />} />
                                        <Route path="nodes/create" element={<AdminNodeCreate />} />
                                        <Route path="nodes/:id/*" element={<AdminNodeView />} />
                                        <Route path="servers" element={<AdminServers />} />
                                        <Route path="servers/create" element={<AdminServerCreate />} />
                                        <Route path="servers/:id/edit" element={<AdminServerEdit />} />
                                        <Route path="servers/:id/*" element={<AdminServerEdit />} />
                                        <Route path="databases" element={<AdminDatabases />} />
                                        <Route path="incidents" element={<AdminIncidents />} />
                                        <Route path="connector-lab" element={<AdminConnectorLab />} />
                                        <Route path="service-health-checks" element={<AdminServiceHealthChecks />} />
                                        <Route path="packages" element={<AdminPackages />} />
                                        <Route path="packages/:id" element={<AdminPackageView />} />
                                        <Route path="images" element={<AdminImages />} />
                                        <Route path="images/:id" element={<AdminImageView />} />
                                        <Route path="notifications" element={<AdminNotifications />} />
                                        <Route path="notifications-test" element={<Navigate to="/admin/notifications?tab=test" replace />} />
                                        <Route path="extensions" element={<AdminExtensions />} />
                                    </Route>

                                    <Route path="*" element={<Navigate to="/" replace />} />
                                </>
                            ) : (
                                <>
                                    <Route path="/" element={<AuthPage />} />
                                    <Route path="*" element={<Navigate to="/" replace />} />
                                </>
                            )}
                        </Routes>
                    </Suspense>
                </motion.div>
            )}
        </AnimatePresence>
    );
};

const ThemeWrapper = ({ children }) => {
    const { user } = useAuth();
    
    const activeThemeId = user?.theme || 'default';
    
    // Dynamically apply user theme class to body
    useEffect(() => {
        document.body.className = `theme-${activeThemeId}`;
    }, [activeThemeId]);

    const activeTheme = useMemo(() => getTheme(activeThemeId), [activeThemeId]);

    return (
        <ThemeProvider theme={activeTheme}>
            <CssBaseline />
            {children}
        </ThemeProvider>
    );
};

const App = () => {
    return (
        <AppSettingsProvider>
            <AuthProvider>
                <ThemeWrapper>
                    <BrowserRouter>
                        <MainRouter />
                    </BrowserRouter>
                </ThemeWrapper>
            </AuthProvider>
        </AppSettingsProvider>
    );
};

const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
}
