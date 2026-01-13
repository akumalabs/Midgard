<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
    HomeIcon,
    ServerStackIcon,
    UsersIcon,
    MapPinIcon,
    CpuChipIcon,
    CircleStackIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: '/admin', icon: HomeIcon, routeName: 'admin.dashboard' },
    { name: 'Nodes', href: '/admin/nodes', icon: CpuChipIcon, routeName: 'admin.nodes' },
    { name: 'Servers', href: '/admin/servers', icon: ServerStackIcon, routeName: 'admin.servers' },
    { name: 'Locations', href: '/admin/locations', icon: MapPinIcon, routeName: 'admin.locations' },
    { name: 'Users', href: '/admin/users', icon: UsersIcon, routeName: 'admin.users' },
];

const isActiveRoute = (routeName: string) => {
    return route.name === routeName;
};

async function handleLogout() {
    await authStore.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-secondary-950">
        <!-- Mobile sidebar backdrop -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-secondary-900 border-r border-secondary-800 transform transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-6 border-b border-secondary-800">
                <RouterLink to="/admin" class="flex items-center gap-2">
                    <span class="text-2xl font-bold gradient-text">Midgard</span>
                </RouterLink>
                <button
                    class="lg:hidden text-secondary-400 hover:text-white"
                    @click="sidebarOpen = false"
                >
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.name"
                    :to="item.href"
                    :class="[
                        'nav-link',
                        isActiveRoute(item.routeName) && 'nav-link-active',
                    ]"
                >
                    <component :is="item.icon" class="w-5 h-5" />
                    {{ item.name }}
                </RouterLink>
            </nav>

            <!-- User section -->
            <div class="border-t border-secondary-800 p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-white">
                            {{ authStore.user?.name?.charAt(0)?.toUpperCase() }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            {{ authStore.user?.name }}
                        </p>
                        <p class="text-xs text-secondary-400 truncate">
                            {{ authStore.user?.email }}
                        </p>
                    </div>
                </div>
                <button
                    @click="handleLogout"
                    class="w-full nav-link text-danger-500 hover:text-danger-400 hover:bg-danger-500/10"
                >
                    <ArrowRightOnRectangleIcon class="w-5 h-5" />
                    Sign Out
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex items-center h-16 px-4 bg-secondary-900/80 backdrop-blur-xl border-b border-secondary-800 lg:px-8">
                <button
                    class="lg:hidden text-secondary-400 hover:text-white mr-4"
                    @click="sidebarOpen = true"
                >
                    <Bars3Icon class="w-6 h-6" />
                </button>

                <div class="flex-1"></div>

                <!-- Admin badge -->
                <span class="badge badge-primary">Admin</span>
            </header>

            <!-- Page content -->
            <main class="p-4 lg:p-8">
                <RouterView />
            </main>
        </div>
    </div>
</template>
