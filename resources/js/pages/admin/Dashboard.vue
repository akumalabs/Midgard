<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useAuthStore } from '@/stores/auth';
import { adminServerApi, nodeApi, userApi } from '@/api';
import type { Server, Node, User } from '@/types/models';
import {
    ServerStackIcon,
    CpuChipIcon,
    UsersIcon,
    MapPinIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();

// Fetch data with Vue Query
const { data: servers, isLoading: loadingServers } = useQuery({
    queryKey: ['admin', 'servers'],
    queryFn: () => adminServerApi.list(),
});

const { data: nodes, isLoading: loadingNodes } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

const { data: users, isLoading: loadingUsers } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => userApi.list(),
});

// Computed stats
const stats = computed(() => [
    {
        name: 'Total Servers',
        value: servers.value?.length ?? 0,
        icon: ServerStackIcon,
        color: 'text-primary-400',
        loading: loadingServers.value,
    },
    {
        name: 'Active Nodes',
        value: nodes.value?.filter(n => !n.maintenance_mode).length ?? 0,
        icon: CpuChipIcon,
        color: 'text-success-500',
        loading: loadingNodes.value,
    },
    {
        name: 'Total Users',
        value: users.value?.length ?? 0,
        icon: UsersIcon,
        color: 'text-warning-500',
        loading: loadingUsers.value,
    },
]);

// Recent servers (last 5)
const recentServers = computed(() => {
    if (!servers.value) return [];
    return [...servers.value]
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
        .slice(0, 5);
});

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
        case 'installing': return 'badge-warning';
        default: return 'badge-secondary';
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-secondary-400">Welcome back, {{ authStore.user?.name }}</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                v-for="stat in stats"
                :key="stat.name"
                class="card card-body flex items-center gap-4"
            >
                <div class="p-3 rounded-lg bg-secondary-800">
                    <component :is="stat.icon" :class="['w-6 h-6', stat.color]" />
                </div>
                <div>
                    <p v-if="stat.loading" class="text-2xl font-bold text-white animate-pulse">...</p>
                    <p v-else class="text-2xl font-bold text-white">{{ stat.value }}</p>
                    <p class="text-sm text-secondary-400">{{ stat.name }}</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-lg font-semibold text-white">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <RouterLink to="/admin/nodes" class="btn-secondary text-center">
                        Add Node
                    </RouterLink>
                    <RouterLink to="/admin/servers" class="btn-secondary text-center">
                        Create Server
                    </RouterLink>
                    <RouterLink to="/admin/users" class="btn-secondary text-center">
                        Manage Users
                    </RouterLink>
                    <RouterLink to="/admin/locations" class="btn-secondary text-center">
                        Add Location
                    </RouterLink>
                </div>
            </div>
        </div>

        <!-- Recent Servers -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Recent Servers</h2>
                <RouterLink to="/admin/servers" class="text-sm text-primary-400 hover:text-primary-300">
                    View all →
                </RouterLink>
            </div>
            <div class="card-body p-0">
                <div v-if="loadingServers" class="p-6 text-center text-secondary-400">
                    Loading...
                </div>
                <div v-else-if="recentServers.length === 0" class="p-6 text-center text-secondary-400">
                    No servers yet
                </div>
                <table v-else class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>User</th>
                            <th>Node</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="server in recentServers" :key="server.id">
                            <td class="font-medium text-white">{{ server.name }}</td>
                            <td>
                                <span :class="statusColor(server.status)">
                                    {{ server.status }}
                                </span>
                            </td>
                            <td>{{ server.user?.name ?? '-' }}</td>
                            <td>{{ server.node?.name ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
