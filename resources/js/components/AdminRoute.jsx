import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const AdminRoute = ({ children }) => {
    const { user, isAuthenticated, loading } = useAuth();

    if (loading) {
        return null; // Or a loading spinner, but MainRouter already handles loading state
    }

    if (!isAuthenticated || !user?.is_admin) {
        return <Navigate to="/" replace />;
    }

    return children;
};

export default AdminRoute;
