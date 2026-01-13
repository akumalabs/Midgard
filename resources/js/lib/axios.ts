import axios from 'axios';

// Create axios instance with default config
const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

// Request interceptor
api.interceptors.request.use(
    (config) => {
        // Get token from localStorage
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor
api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Handle 401 Unauthorized
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            window.location.href = '/auth/login';
        }

        // Handle 403 Forbidden
        if (error.response?.status === 403) {
            console.error('Access denied');
        }

        // Handle 422 Validation errors
        if (error.response?.status === 422) {
            // Return the validation errors
            return Promise.reject(error.response.data);
        }

        // Handle 500 Server errors
        if (error.response?.status >= 500) {
            console.error('Server error:', error.response.data);
        }

        return Promise.reject(error);
    }
);

export default api;
