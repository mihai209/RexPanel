export const RESOURCE_LABELS = {
    ramMb: 'RAM',
    cpuPercent: 'CPU',
    diskMb: 'Disk',
    swapMb: 'Swap',
    databases: 'Databases',
    allocations: 'Allocations',
    images: 'Images',
    packages: 'Packages',
};

export const formatResourceList = (resources = {}) => Object.entries(resources)
    .filter(([, value]) => Number(value || 0) > 0)
    .map(([key, value]) => {
        const numeric = Number(value || 0);
        if (key === 'ramMb' || key === 'diskMb' || key === 'swapMb') {
            const gb = numeric / 1024;
            return `${RESOURCE_LABELS[key] || key}: ${Number.isInteger(gb) ? gb : gb.toFixed(2)} GB`;
        }
        if (key === 'cpuPercent') {
            const cores = numeric / 100;
            return `${RESOURCE_LABELS[key] || key}: ${Number.isInteger(cores) ? cores : cores.toFixed(2)} cores`;
        }
        return `${RESOURCE_LABELS[key] || key}: ${numeric}`;
    })
    .join(' · ');

export const emptyResources = () => ({
    ramMb: 0,
    cpuPercent: 0,
    diskMb: 0,
    swapMb: 0,
    databases: 0,
    allocations: 0,
    images: 0,
    packages: 0,
});

export const convertEditorResourcesToApi = (resources = {}) => ({
    ramMb: Math.max(0, Math.round(Number(resources.ramGb || 0) * 1024)),
    cpuPercent: Math.max(0, Math.round(Number(resources.cpuCores || 0) * 100)),
    diskMb: Math.max(0, Math.round(Number(resources.diskGb || 0) * 1024)),
    swapMb: Math.max(0, Math.round(Number(resources.swapGb || 0) * 1024)),
    allocations: Math.max(0, Number(resources.allocations || 0)),
    images: Math.max(0, Number(resources.images || 0)),
    databases: Math.max(0, Number(resources.databases || 0)),
    packages: Math.max(0, Number(resources.packages || 0)),
});

export const convertApiResourcesToEditor = (resources = {}) => ({
    ramGb: Number(resources.ramMb || 0) / 1024,
    cpuCores: Number(resources.cpuPercent || 0) / 100,
    diskGb: Number(resources.diskMb || 0) / 1024,
    swapGb: Number(resources.swapMb || 0) / 1024,
    allocations: Number(resources.allocations || 0),
    images: Number(resources.images || 0),
    databases: Number(resources.databases || 0),
    packages: Number(resources.packages || 0),
});
