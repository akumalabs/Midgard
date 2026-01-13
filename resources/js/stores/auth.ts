import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/lib/axios';

export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string;
}

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref<User | null>(null);
    const token = ref<string | null>(localStorage.getItem('auth_token'));
    const loading = ref(false);

    // Getters
    const isAuthenticated = computed(() => !!user.value && !!token.value);
    const isAdmin = computed(() => user.value?.is_admin ?? false);

    // Actions
    async function login(email: string, password: string): Promise<void> {
        loading.value = true;
        try {
            const response = await api.post('/auth/login', { email, password });
            token.value = response.data.token;
            user.value = response.data.user;
            localStorage.setItem('auth_token', response.data.token);
        } finally {
            loading.value = false;
        }
    }

    async function logout(): Promise<void> {
        loading.value = true;
        try {
            await api.post('/auth/logout');
        } catch {
            // Ignore errors on logout
        } finally {
            user.value = null;
            token.value = null;
            localStorage.removeItem('auth_token');
            loading.value = false;
        }
    }

    async function checkAuth(): Promise<void> {
        if (!token.value) {
            user.value = null;
            return;
        }

        loading.value = true;
        try {
            const response = await api.get('/auth/user');
            user.value = response.data.data;
        } catch {
            // Token is invalid or expired
            user.value = null;
            token.value = null;
            localStorage.removeItem('auth_token');
        } finally {
            loading.value = false;
        }
    }

    async function updateProfile(data: Partial<User>): Promise<void> {
        loading.value = true;
        try {
            const response = await api.patch('/auth/user', data);
            user.value = response.data.data;
        } finally {
            loading.value = false;
        }
    }

    return {
        // State
        user,
        token,
        loading,
        // Getters
        isAuthenticated,
        isAdmin,
        // Actions
        login,
        logout,
        checkAuth,
        updateProfile,
    };
});
