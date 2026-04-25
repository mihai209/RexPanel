import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';

const AppSettingsContext = createContext(null);

const bootstrapSettings = () => {
    const initial = window.RA_PANEL_BOOTSTRAP?.settings || {};

    return {
        brandName: typeof initial.brandName === 'string' && initial.brandName.trim() !== '' ? initial.brandName.trim() : 'RA-panel',
        faviconUrl: typeof initial.faviconUrl === 'string' && initial.faviconUrl.trim() !== '' ? initial.faviconUrl.trim() : '/favicon.ico',
    };
};

export const AppSettingsProvider = ({ children }) => {
    const [settings, setSettings] = useState(bootstrapSettings);

    useEffect(() => {
        document.title = settings.brandName || 'RA-panel';

        let favicon = document.getElementById('app-favicon');
        if (!favicon) {
            favicon = document.createElement('link');
            favicon.id = 'app-favicon';
            favicon.rel = 'icon';
            document.head.appendChild(favicon);
        }

        favicon.href = settings.faviconUrl || '/favicon.ico';
    }, [settings]);

    const value = useMemo(() => ({
        settings,
        setSettings,
    }), [settings]);

    return (
        <AppSettingsContext.Provider value={value}>
            {children}
        </AppSettingsContext.Provider>
    );
};

export const useAppSettings = () => {
    const context = useContext(AppSettingsContext);

    if (!context) {
        throw new Error('useAppSettings must be used within AppSettingsProvider.');
    }

    return context;
};
