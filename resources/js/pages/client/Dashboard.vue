<script setup lang="ts">
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useAuthStore } from '@/stores/auth';
import { clientServerApi } from '@/api';
import { ServerStackIcon } from '@heroicons/vue/24/outline';

const authStore = useAuthStore();

// Fetch user's servers
const { data: servers, isLoading } = useQuery({
    queryKey: ['client', 'servers'],
    queryFn: () => clientServerApi.list(),
});

// Stats
const stats = computed(() => ({
    total: servers.value?.length ?? 0,
    running: servers.value?.filter(s => s.status === 'running').length ?? 0,
    stopped: servers.value?.filter(s => s.status === 'stopped').length ?? 0,
}));

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
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

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="card card-body">
                <p class="text-secondary-400 text-sm">Total Servers</p>
                <p class="text-2xl font-bold text-white">{{ stats.total }}</p>
            </div>
            <div class="card card-body">
                <p class="text-secondary-400 text-sm">Running</p>
                <p class="text-2xl font-bold text-success-500">{{ stats.running }}</p>
            </div>
            <div class="card card-body">
                <p class="text-secondary-400 text-sm">Stopped</p>
                <p class="text-2xl font-bold text-danger-500">{{ stats.stopped }}</p>
            </div>
        </div>

        <!-- Recent Servers -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Your Servers</h2>
                <RouterLink to="/servers" class="text-sm text-primary-400 hover:text-primary-300">
                    View all →
                </RouterLink>
            </div>
            <div class="card-body p-0">
                <div v-if="isLoading" class="p-6 text-center text-secondary-400">
                    Loading...
                </div>
                <div v-else-if="!servers?.length" class="p-6 text-center">
                    <ServerStackIcon class="w-12 h-12 mx-auto mb-4 text-secondary-500" />
                    <p class="text-secondary-400">You don't have any servers yet.</p>
                </div>
                <table v-else class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Resources</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="server in servers?.slice(0, 5)" :key="server.id">
                            <td class="font-medium text-white">{{ server.name }}</td>
                            <td>
                                <span :class="statusColor(server.status)">{{ server.status }}</span>
                            </td>
                            <td>{{ server.node?.location?.short_code || '-' }}</td>
                            <td class="text-sm text-secondary-400">
                                {{ server.cpu }} vCPU, {{ server.memory_formatted }}
                            </td>
                            <td>
                                <RouterLink 
                                    :to="`/servers/${server.uuid}`"
                                    class="text-primary-400 hover:text-primary-300"
                                >
                                    Manage →
                                </RouterLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
