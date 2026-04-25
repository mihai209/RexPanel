export const formatAllocationLabel = (allocation) => {
    if (!allocation) {
        return '—';
    }

    const alias = allocation.ip_alias ? ` (${allocation.ip_alias})` : '';
    return `${allocation.ip}:${allocation.port}${alias}`;
};

export const normalizeNullableNumber = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
};

export const buildStartupPreview = ({ startup, variables, memory, allocation }) => {
    const env = {
        ...(variables || {}),
        SERVER_MEMORY: String(memory ?? ''),
        SERVER_IP: allocation?.ip || '',
        SERVER_PORT: allocation?.port ? String(allocation.port) : '',
    };

    const template = String(startup || '');
    const resolved = template
        .replace(/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/g, (match, key) => (
            Object.prototype.hasOwnProperty.call(env, key) ? String(env[key] ?? '') : match
        ))
        .replace(/(?<!\$)\{([A-Za-z0-9_]+)\}/g, (match, key) => (
            Object.prototype.hasOwnProperty.call(env, key) ? String(env[key] ?? '') : match
        ));

    return { env, resolved };
};
