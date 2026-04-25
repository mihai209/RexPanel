import axios from 'axios';

const client = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Interceptor to add tokens to requests
client.interceptors.request.use((config) => {
    const token = localStorage.getItem('ra_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Interceptor to handle unauthenticated responses
client.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('ra_token');
            window.location.reload();
        }

        if (error.response?.status === 423 || error.response?.data?.code === 'ACCOUNT_SUSPENDED') {
            localStorage.removeItem('ra_token');
            sessionStorage.setItem('ra_suspension_message', error.response?.data?.message || 'Your account has been suspended by an administrator.');
            window.dispatchEvent(new CustomEvent('ra:account-suspended', {
                detail: {
                    message: error.response?.data?.message || 'Your account has been suspended by an administrator.',
                },
            }));
            window.location.reload();
        }

        return Promise.reject(error);
    }
);

export default client;
