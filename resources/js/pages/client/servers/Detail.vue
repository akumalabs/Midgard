<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import VncConsole from '@/components/VncConsole.vue';
import {
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    ComputerDesktopIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const queryClient = useQueryClient();
const uuid = computed(() => route.params.uuid as string);
const showConsole = ref(false);

// Fetch server details
const { data: server, isLoading } = useQuery({
    queryKey: ['client', 'server', uuid],
    queryFn: () => clientServerApi.get(uuid.value),
});

// Fetch server status
const { data: status, refetch: refetchStatus } = useQuery({
    queryKey: ['client', 'server', uuid, 'status'],
    queryFn: () => clientServerApi.status(uuid.value),
    refetchInterval: 10000, // Refresh every 10 seconds
    enabled: computed(() => !!server.value),
});

// Power mutation
const powerMutation = useMutation({
    mutationFn: (action: 'start' | 'stop' | 'restart' | 'shutdown') =>
        clientServerApi.power(uuid.value, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['client', 'server', uuid.value] });
        refetchStatus();
    },
});

const statusColor = (s: string) => {
    switch (s) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
        default: return 'badge-secondary';
    }
};

const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatUptime = (seconds: number): string => {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Back link -->
        <RouterLink to="/servers" class="inline-flex items-center text-secondary-400 hover:text-white">
            <ArrowLeftIcon class="w-4 h-4 mr-2" />
            Back to Servers
        </RouterLink>

        <!-- Loading -->
        <div v-if="isLoading" class="card card-body">
            <div class="animate-pulse">
                <div class="h-8 bg-secondary-800 rounded w-1/3 mb-4"></div>
                <div class="h-4 bg-secondary-800 rounded w-1/2"></div>
            </div>
        </div>

        <template v-else-if="server">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ server.name }}</h1>
                    <p class="text-secondary-400">{{ server.hostname || server.uuid }}</p>
                </div>
                <span :class="statusColor(status?.status || server.status)">
                    {{ status?.status || server.status }}
                </span>
            </div>

            <!-- Power Controls -->
            <div class="card card-body">
                <h2 class="text-lg font-semibold text-white mb-4">Power Controls</h2>
                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="(status?.status || server.status) === 'stopped'"
                        @click="powerMutation.mutate('start')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-success"
                    >
                        <PlayIcon class="w-5 h-5 mr-2" />
                        Start
                    </button>
                    <button
                        v-if="(status?.status || server.status) === 'running'"
                        @click="powerMutation.mutate('shutdown')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-secondary"
                    >
                        <StopIcon class="w-5 h-5 mr-2" />
                        Shutdown
                    </button>
                    <button
                        v-if="(status?.status || server.status) === 'running'"
                        @click="powerMutation.mutate('restart')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-secondary"
                    >
                        <ArrowPathIcon class="w-5 h-5 mr-2" />
                        Restart
                    </button>
                    <button
                        v-if="(status?.status || server.status) === 'running'"
                        @click="powerMutation.mutate('stop')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-danger"
                    >
                        <StopIcon class="w-5 h-5 mr-2" />
                        Force Stop
                    </button>
                    
                    <!-- Console button -->
                    <button
                        v-if="(status?.status || server.status) === 'running'"
                        @click="showConsole = true"
                        class="btn-primary"
                    >
                        <ComputerDesktopIcon class="w-5 h-5 mr-2" />
                        Console
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card card-body">
                    <p class="text-secondary-400 text-sm">Uptime</p>
                    <p class="text-xl font-bold text-white">
                        {{ status ? formatUptime(status.uptime) : '-' }}
                    </p>
                </div>
                <div class="card card-body">
                    <p class="text-secondary-400 text-sm">CPU Usage</p>
                    <p class="text-xl font-bold text-white">
                        {{ status ? status.cpu.toFixed(1) + '%' : '-' }}
                    </p>
                </div>
                <div class="card card-body">
                    <p class="text-secondary-400 text-sm">Memory</p>
                    <p class="text-xl font-bold text-white">
                        {{ status ? (status.memory.percentage?.toFixed(1) || 0) + '%' : '-' }}
                    </p>
                    <p class="text-xs text-secondary-500">
                        {{ status ? formatBytes(status.memory.used) + ' / ' + formatBytes(status.memory.total) : '' }}
                    </p>
                </div>
                <div class="card card-body">
                    <p class="text-secondary-400 text-sm">Network</p>
                    <p class="text-sm text-white">
                        ↓ {{ status ? formatBytes(status.network?.in || 0) : '-' }}
                    </p>
                    <p class="text-sm text-white">
                        ↑ {{ status ? formatBytes(status.network?.out || 0) : '-' }}
                    </p>
                </div>
            </div>

            <!-- Server Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold text-white">Server Information</h2>
                </div>
                <div class="card-body grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-secondary-400 text-sm">CPU Cores</span>
                        <p class="text-white">{{ server.cpu }} vCPU</p>
                    </div>
                    <div>
                        <span class="text-secondary-400 text-sm">Memory</span>
                        <p class="text-white">{{ server.memory_formatted }}</p>
                    </div>
                    <div>
                        <span class="text-secondary-400 text-sm">Disk</span>
                        <p class="text-white">{{ server.disk_formatted }}</p>
                    </div>
                    <div>
                        <span class="text-secondary-400 text-sm">Location</span>
                        <p class="text-white">{{ server.node?.location?.name || '-' }}</p>
                    </div>
                    <div v-if="server.addresses?.length">
                        <span class="text-secondary-400 text-sm">IP Address</span>
                        <p class="text-white font-mono">
                            {{ server.addresses[0]?.address }}/{{ server.addresses[0]?.cidr }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
        
        <!-- VNC Console Modal -->
        <VncConsole 
            v-if="server"
            :server-uuid="uuid" 
            :show="showConsole"
            @close="showConsole = false"
        />
    </div>
</template>
