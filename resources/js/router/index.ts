import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Layouts
import AuthLayout from '@/layouts/AuthLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ClientLayout from '@/layouts/ClientLayout.vue';

// Auth Pages
import Login from '@/pages/auth/Login.vue';

// Admin Pages
import AdminDashboard from '@/pages/admin/Dashboard.vue';
import AdminNodes from '@/pages/admin/nodes/Index.vue';
import AdminServers from '@/pages/admin/servers/Index.vue';
import AdminUsers from '@/pages/admin/users/Index.vue';
import AdminLocations from '@/pages/admin/locations/Index.vue';

// Client Pages
import ClientDashboard from '@/pages/client/Dashboard.vue';
import ClientServers from '@/pages/client/servers/Index.vue';
import ClientServerDetail from '@/pages/client/servers/Detail.vue';

const routes = [
    // Auth routes
    {
        path: '/auth',
        component: AuthLayout,
        children: [
            {
                path: 'login',
                name: 'login',
                component: Login,
                meta: { guest: true },
            },
        ],
    },

    // Admin routes
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            {
                path: '',
                name: 'admin.dashboard',
                component: AdminDashboard,
            },
            {
                path: 'nodes',
                name: 'admin.nodes',
                component: AdminNodes,
            },
            {
                path: 'servers',
                name: 'admin.servers',
                component: AdminServers,
            },
            {
                path: 'users',
                name: 'admin.users',
                component: AdminUsers,
            },
            {
                path: 'locations',
                name: 'admin.locations',
                component: AdminLocations,
            },
        ],
    },

    // Client routes
    {
        path: '/',
        component: ClientLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'client.dashboard',
                component: ClientDashboard,
            },
            {
                path: 'servers',
                name: 'client.servers',
                component: ClientServers,
            },
            {
                path: 'servers/:uuid',
                name: 'client.servers.detail',
                component: ClientServerDetail,
                props: true,
            },
        ],
    },

    // Catch-all redirect
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Check if route requires authentication
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    // Check if route requires admin access
    if (to.meta.requiresAdmin && !authStore.isAdmin) {
        return next({ name: 'client.dashboard' });
    }

    // Redirect authenticated users away from guest-only pages
    if (to.meta.guest && authStore.isAuthenticated) {
        return next({ name: authStore.isAdmin ? 'admin.dashboard' : 'client.dashboard' });
    }

    next();
});

export default router;
