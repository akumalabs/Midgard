<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import ServerSettingsModal from '@/components/ServerSettingsModal.vue';
import IsoModal from '@/components/IsoModal.vue';
import BackupManager from '@/components/server/BackupManager.vue';
import NetworkManager from '@/components/server/NetworkManager.vue';
import FirewallManager from '@/components/server/FirewallManager.vue';
import ConsoleViewer from '@/components/server/ConsoleViewer.vue';

import {
    ArrowLeftIcon,
    ServerIcon,
    CpuChipIcon,
    CircleStackIcon,
    ClockIcon,
    SignalIcon,
    Cog6ToothIcon,
    ShieldCheckIcon,
    GlobeAltIcon,
    ArchiveBoxIcon,
    CommandLineIcon,
    HomeIcon
} from '@heroicons/vue/24/outline';

const route = useRoute();
const queryClient = useQueryClient();
const uuid = computed(() => route.params.uuid as string);

const activeTab = ref('overview');
const showSettings = ref(false);
const showIso = ref(false);

const tabs = [
    { id: 'overview', name: 'Overview', icon: HomeIcon },
    { id: 'console', name: 'Console', icon: CommandLineIcon },
    { id: 'network', name: 'Network', icon: GlobeAltIcon },
    { id: 'firewall', name: 'Firewall', icon: ShieldCheckIcon },
    { id: 'backups', name: 'Backups', icon: ArchiveBoxIcon },
];

// Fetch server details
const { data: server, isLoading } = useQuery({
    queryKey: ['client', 'server', uuid],
    queryFn: () => clientServerApi.get(uuid.value),
});

// Fetch server status
const { data: status, refetch: refetchStatus } = useQuery({
    queryKey: ['client', 'server', uuid, 'status'],
    queryFn: () => clientServerApi.status(uuid.value),
    refetchInterval: 3000,
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

// Formatters
const formatBytes = (bytes: number) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatUptime = (seconds: number) => {
    if (!seconds) return '0m';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
};

// Computed stats
const cpuPercent = computed(() => status.value?.cpu ? status.value.cpu.toFixed(0) : '0');
const memoryUsed = computed(() => formatBytes(status.value?.memory?.used || 0));
const memoryTotal = computed(() => formatBytes(status.value?.memory?.total || server.value?.memory || 0));
const bandwidthUsed = computed(() => server.value?.bandwidth_usage || 0);
const bandwidthLimit = computed(() => server.value?.bandwidth_limit || 0);
const bandwidthPercent = computed(() => bandwidthLimit.value ? Math.min(100, (bandwidthUsed.value / bandwidthLimit.value) * 100) : 0);
const diskUsed = computed(() => server.value?.disk || 0);
</script>

<template>
    <div class="space-y-6 animate-fade-in relative min-h-screen pb-20">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center p-20">
            <div class="animate-spin h-8 w-8 border-2 border-primary-500 border-t-transparent rounded-full"></div>
        </div>

        <template v-else-if="server">
            <!-- Header & Navigation -->
            <div class="sticky top-0 bg-[var(--bg-base)]/80 backdrop-blur-xl z-30 pb-4 border-b border-[var(--border-base)] -mx-6 px-6 pt-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <RouterLink to="/servers" class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--text-base)] text-sm mb-2 transition-colors">
                            <ArrowLeftIcon class="w-4 h-4 mr-1" />
                            Back to Servers
                        </RouterLink>
                        <div class="flex items-center gap-3">
                            <span 
                                class="w-3 h-3 rounded-full shadow-lg shadow-current/50"
                                :class="{
                                    'bg-success-500 text-success-500': isRunning,
                                    'bg-danger-500 text-danger-500': !isRunning && currentStatus !== 'unknown',
                                    'bg-warning-500 text-warning-500': currentStatus === 'unknown'
                                }"
                            ></span>
                            <h1 class="text-2xl font-bold text-[var(--text-base)]">{{ server.name }}</h1>
                            <span class="px-2 py-0.5 rounded text-xs font-mono bg-[var(--bg-surface-secondary)] text-[var(--text-muted)]">{{ server.hostname || server.uuid.substring(0,8) }}</span>
                        </div>
                    </div>
    
                    <!-- Power Controls -->
                    <div class="flex flex-wrap gap-2">
                         <button @click="showIso = true" class="btn-secondary px-3 py-1.5 text-sm">ISO</button>
                         <button @click="showSettings = true" class="btn-secondary px-3 py-1.5 text-sm">
                            <Cog6ToothIcon class="w-4 h-4" />
                         </button>
                         
                         <div class="h-6 w-px bg-[var(--border-base)] mx-1"></div>
                         
                         <button v-if="!isRunning" @click="powerMutation.mutate('start')" :disabled="powerMutation.isPending.value" class="btn-success px-4 py-1.5 text-sm">Start</button>
                         <template v-else>
                            <button @click="powerMutation.mutate('restart')" :disabled="powerMutation.isPending.value" class="btn-secondary px-3 py-1.5 text-sm">Restart</button>
                            <button @click="powerMutation.mutate('shutdown')" :disabled="powerMutation.isPending.value" class="btn-secondary px-3 py-1.5 text-sm">Stop</button>
                            <button @click="powerMutation.mutate('kill')" :disabled="powerMutation.isPending.value" class="btn-danger px-3 py-1.5 text-sm">Kill</button>
                         </template>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="flex space-x-1 overflow-x-auto no-scrollbar">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-all whitespace-nowrap"
                        :class="activeTab === tab.id ? 'bg-[var(--bg-surface-secondary)] text-[var(--text-base)] shadow-sm' : 'text-[var(--text-muted)] hover:text-[var(--text-base)] hover:bg-[var(--bg-surface-secondary)]/50'"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.name }}
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="mt-6">
                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'" class="space-y-6 animate-slide-up">
                    <!-- Stats Overview -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="card card-body hover:border-[var(--primary-base)] transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <CpuChipIcon class="w-5 h-5 text-[var(--primary-base)]" />
                                <span class="text-sm text-[var(--text-muted)]">CPU Usage</span>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-base)]">{{ cpuPercent }}%</p>
                            <div class="w-full bg-[var(--bg-surface-secondary)] h-1 mt-3 rounded-full overflow-hidden">
                                <div class="bg-[var(--primary-base)] h-full transition-all duration-500" :style="{ width: `${cpuPercent}%` }"></div>
                            </div>
                        </div>

                         <div class="card card-body hover:border-[var(--primary-base)] transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <CircleStackIcon class="w-5 h-5 text-[var(--primary-base)]" />
                                <span class="text-sm text-[var(--text-muted)]">Memory</span>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-base)]">{{ memoryUsed }}</p>
                            <p class="text-xs text-[var(--text-muted)] mt-1">of {{ memoryTotal }}</p>
                        </div>

                         <div class="card card-body hover:border-[var(--primary-base)] transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <SignalIcon class="w-5 h-5 text-[var(--primary-base)]" />
                                <span class="text-sm text-[var(--text-muted)]">Bandwidth</span>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-base)]">{{ formatBytes(bandwidthUsed) }}</p>
                            <div class="w-full bg-[var(--bg-surface-secondary)] h-1 mt-3 rounded-full overflow-hidden">
                                <div class="bg-blue-500 h-full transition-all duration-500" :style="{ width: `${bandwidthPercent}%` }"></div>
                            </div>
                        </div>
                        
                        <div class="card card-body hover:border-[var(--primary-base)] transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <ClockIcon class="w-5 h-5 text-[var(--primary-base)]" />
                                <span class="text-sm text-[var(--text-muted)]">Uptime</span>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-base)]">{{ isRunning ? formatUptime(status?.uptime || 0) : 'Offline' }}</p>
                        </div>
                    </div>

                    <!-- Details & Graphs Placeholder -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 card p-6">
                            <h3 class="text-lg font-medium mb-4 text-[var(--text-base)]">Network Traffic (24h)</h3>
                            <div class="h-64 flex items-center justify-center border border-dashed border-[var(--border-base)] rounded-lg">
                                <span class="text-[var(--text-muted)]">Chart Data Placeholder</span>
                            </div>
                        </div>
                        
                        <div class="card p-6 space-y-4">
                            <h3 class="text-lg font-medium mb-4 text-[var(--text-base)]">Information</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-[var(--text-muted)]">Node</p>
                                    <p class="font-medium text-[var(--text-base)]">{{ server.node?.name || 'Unknown' }}</p>
                                </div>
                                <div>
                                    <p class="text-[var(--text-muted)]">VMID</p>
                                    <p class="font-medium text-[var(--text-base)]">{{ server.vmid }}</p>
                                </div>
                                <div>
                                    <p class="text-[var(--text-muted)]">IP Address</p>
                                    <p class="font-medium text-[var(--text-base)]">{{ server.addresses?.[0]?.address || 'N/A' }}</p>
                                </div>
                                 <div>
                                    <p class="text-[var(--text-muted)]">OS</p>
                                    <p class="font-medium text-[var(--text-base)]">Linux</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feature Tabs -->
                <div v-else-if="activeTab === 'console'" class="animate-fade-in">
                    <ConsoleViewer />
                </div>
                <div v-else-if="activeTab === 'network'" class="animate-fade-in">
                    <NetworkManager />
                </div>
                <div v-else-if="activeTab === 'firewall'" class="animate-fade-in">
                    <FirewallManager />
                </div>
                <div v-else-if="activeTab === 'backups'" class="animate-fade-in">
                    <BackupManager />
                </div>
            </div>

            <!-- Modals -->
            <ServerSettingsModal :server-uuid="uuid" :show="showSettings" @close="showSettings = false" />
            <IsoModal :server-uuid="uuid" :show="showIso" @close="showIso = false" />
        </template>
    </div>
</template>
