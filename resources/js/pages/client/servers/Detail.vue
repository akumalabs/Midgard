<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import VncConsole from '@/components/VncConsole.vue';
import ServerSettingsModal from '@/components/ServerSettingsModal.vue';
import SnapshotsPanel from '@/components/SnapshotsPanel.vue';
import IsoModal from '@/components/IsoModal.vue';
import {
    ArrowLeftIcon,
    ServerIcon,
    CpuChipIcon,
    CircleStackIcon,
    ClockIcon,
    SignalIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const queryClient = useQueryClient();
const uuid = computed(() => route.params.uuid as string);
const showConsole = ref(false);
const showSettings = ref(false);
const showIso = ref(false);

// Fetch server details
const { data: server, isLoading } = useQuery({
    queryKey: ['client', 'server', uuid],
    queryFn: () => clientServerApi.get(uuid.value),
});

// Fetch server status with faster refresh (2 seconds for real-time feel)
const { data: status, refetch: refetchStatus } = useQuery({
    queryKey: ['client', 'server', uuid, 'status'],
    queryFn: () => clientServerApi.status(uuid.value),
    refetchInterval: 2000,
    enabled: computed(() => !!server.value),
});

// Power mutation
const powerMutation = useMutation({
    mutationFn: (action: 'start' | 'stop' | 'restart' | 'shutdown' | 'kill') =>
        clientServerApi.power(uuid.value, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['client', 'server', uuid.value] });
        refetchStatus();
    },
});

const currentStatus = computed(() => status.value?.status || server.value?.status || 'unknown');
const isRunning = computed(() => currentStatus.value === 'running');

const formatBytes = (bytes: number): string => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatUptime = (seconds: number): string => {
    if (!seconds) return '0m';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days} day${days > 1 ? 's' : ''}`;
    if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''}`;
    return `${mins} minute${mins > 1 ? 's' : ''}`;
};

const cpuPercent = computed(() => {
    if (!status.value?.cpu) return '0';
    return status.value.cpu.toFixed(0);
});

const memoryUsed = computed(() => formatBytes(status.value?.memory?.used || 0));
const memoryTotal = computed(() => formatBytes(status.value?.memory?.total || server.value?.memory || 0));

const bandwidthUsed = computed(() => server.value?.bandwidth_usage || 0);
const bandwidthLimit = computed(() => server.value?.bandwidth_limit || 0);
const bandwidthPercent = computed(() => {
    if (!bandwidthLimit.value) return 0;
    return Math.min(100, (bandwidthUsed.value / bandwidthLimit.value) * 100);
});

const diskUsed = computed(() => server.value?.disk || 0);
const diskTotal = computed(() => server.value?.disk || 0);
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Loading -->
        <div v-if="isLoading" class="card card-body">
            <div class="animate-pulse">
                <div class="h-8 bg-secondary-800 rounded w-1/3 mb-4"></div>
                <div class="h-4 bg-secondary-800 rounded w-1/2"></div>
            </div>
        </div>

        <template v-else-if="server">
            <!-- Header with Power Controls -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <RouterLink to="/servers" class="inline-flex items-center text-secondary-400 hover:text-white text-sm mb-2">
                        <ArrowLeftIcon class="w-4 h-4 mr-1" />
                        Back to Servers
                    </RouterLink>
                    <h1 class="text-2xl font-bold text-white">{{ server.name }}</h1>
                    <p class="text-secondary-400">{{ server.hostname || server.uuid }}</p>
                </div>
                
                <!-- Power Buttons (Convoy style) -->
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="showSettings = true"
                        class="btn-secondary px-4 py-2"
                    >
                        <Cog6ToothIcon class="w-4 h-4 inline mr-1" />
                        Settings
                    </button>
                    <button
                        @click="showIso = true"
                        class="btn-secondary px-4 py-2"
                    >
                        ISO
                    </button>
                    <button
                        @click="showConsole = true"
                        :disabled="!isRunning"
                        class="btn-secondary px-4 py-2"
                    >
                        Console
                    </button>
                    <button
                        v-if="!isRunning"
                        @click="powerMutation.mutate('start')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-secondary px-4 py-2"
                    >
                        Start
                    </button>
                    <button
                        v-if="isRunning"
                        @click="powerMutation.mutate('restart')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-secondary px-4 py-2"
                    >
                        Restart
                    </button>
                    <button
                        v-if="isRunning"
                        @click="powerMutation.mutate('kill')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-danger-outline px-4 py-2"
                    >
                        Kill
                    </button>
                    <button
                        v-if="isRunning"
                        @click="powerMutation.mutate('shutdown')"
                        :disabled="powerMutation.isPending.value"
                        class="btn-danger px-4 py-2"
                    >
                        Shutdown
                    </button>
                </div>
            </div>

            <!-- Stats Grid (2x2) - Convoy Style -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Server State -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-secondary-400 text-sm">Server State</span>
                        <ServerIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span 
                            class="w-2 h-2 rounded-full"
                            :class="isRunning ? 'bg-green-500' : 'bg-red-500'"
                        ></span>
                        <span class="text-xl font-bold text-white capitalize">{{ currentStatus }}</span>
                    </div>
                </div>

                <!-- CPU Usage -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-secondary-400 text-sm">CPU Usage</span>
                        <CpuChipIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <p class="text-xl font-bold text-white">{{ cpuPercent }}%</p>
                </div>

                <!-- Memory Usage -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-secondary-400 text-sm">Memory Usage</span>
                        <CircleStackIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <p class="text-xl font-bold text-white">{{ memoryUsed }}</p>
                    <p class="text-xs text-secondary-500">/ {{ memoryTotal }}</p>
                </div>

                <!-- Uptime -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-secondary-400 text-sm">Uptime</span>
                        <ClockIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <p class="text-xl font-bold text-white">
                        {{ isRunning ? formatUptime(status?.uptime || 0) : 'Offline' }}
                    </p>
                </div>
            </div>

            <!-- Resource Cards (3-column) - Convoy Style -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Bandwidth Allowance -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-secondary-400 text-sm">Bandwidth Allowance</span>
                        <SignalIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <p class="text-lg font-bold text-white">{{ formatBytes(bandwidthUsed) }} used</p>
                    <p class="text-xs text-secondary-500 mb-3">
                        out of {{ bandwidthLimit ? formatBytes(bandwidthLimit) : 'Unlimited' }}
                        {{ bandwidthLimit ? ' • ' + bandwidthPercent.toFixed(2) + '%' : '' }}
                    </p>
                    <div class="w-full bg-secondary-800 rounded-full h-1.5">
                        <div 
                            class="bg-primary-500 h-1.5 rounded-full transition-all duration-300"
                            :style="{ width: bandwidthPercent + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Storage Usage -->
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-secondary-400 text-sm">Storage Usage</span>
                        <CircleStackIcon class="w-4 h-4 text-secondary-500" />
                    </div>
                    <p class="text-lg font-bold text-white">{{ formatBytes(diskUsed) }} used</p>
                    <p class="text-xs text-secondary-500 mb-3">
                        out of {{ formatBytes(diskTotal) }} • allocated
                    </p>
                    <div class="w-full bg-secondary-800 rounded-full h-1.5">
                        <div 
                            class="bg-primary-500 h-1.5 rounded-full"
                            style="width: 100%"
                        ></div>
                    </div>
                </div>

                <!-- System Specifications -->
                <div class="card card-body">
                    <span class="text-secondary-400 text-sm mb-3 block">System Specifications</span>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-secondary-500">CPU Cores</p>
                            <p class="text-lg font-bold text-white">{{ server.cpu }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-500">Memory</p>
                            <p class="text-lg font-bold text-white">{{ server.memory_formatted }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-500">Disk</p>
                            <p class="text-lg font-bold text-white">{{ server.disk_formatted }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IPAM Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold text-white">IPAM</h2>
                </div>
                <div class="card-body">
                    <div v-if="server.addresses?.length" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="addr in server.addresses" :key="addr.address">
                            <p class="text-secondary-400 text-sm">Address</p>
                            <p class="text-white font-mono text-lg">{{ addr.address }}/{{ addr.cidr }}</p>
                        </div>
                        <div>
                            <p class="text-secondary-400 text-sm">Gateway</p>
                            <p class="text-white font-mono text-lg">{{ server.addresses[0]?.gateway || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-secondary-400 text-sm">Mac Address</p>
                            <p class="text-white font-mono text-lg">N/A</p>
                        </div>
                    </div>
                    <p v-else class="text-secondary-500">No IP addresses assigned</p>
                </div>
            </div>

            <!-- Network Traffic -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold text-white">Network Traffic</h2>
                </div>
                <div class="card-body grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-secondary-400 text-sm">Inbound</p>
                        <p class="text-xl font-bold text-white">↓ {{ formatBytes(status?.network?.in || 0) }}</p>
                    </div>
                    <div>
                        <p class="text-secondary-400 text-sm">Outbound</p>
                        <p class="text-xl font-bold text-white">↑ {{ formatBytes(status?.network?.out || 0) }}</p>
                    </div>
                </div>
            </div>
            <!-- Snapshots Panel -->
            <SnapshotsPanel :server-uuid="uuid" />
        </template>
        
        <!-- VNC Console Modal -->
        <VncConsole 
            v-if="server"
            :server-uuid="uuid" 
            :show="showConsole"
            @close="showConsole = false"
        />
        
        <!-- Settings Modal -->
        <ServerSettingsModal
            :server-uuid="uuid"
            :show="showSettings"
            @close="showSettings = false"
        />
        
        <!-- ISO Modal -->
        <IsoModal
            :server-uuid="uuid"
            :show="showIso"
            @close="showIso = false"
        />
    </div>
</template>
