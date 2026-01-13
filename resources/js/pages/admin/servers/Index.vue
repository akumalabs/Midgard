<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi } from '@/api';
import type { Server } from '@/types/models';
import {
    PlusIcon,
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    TrashIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch servers
const { data: servers, isLoading } = useQuery({
    queryKey: ['admin', 'servers'],
    queryFn: () => adminServerApi.list(),
});

// Power mutation
const powerMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'start' | 'stop' | 'restart' }) =>
        adminServerApi.power(id, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
    },
});

// Delete mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => adminServerApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
    },
});

const handlePower = (server: Server, action: 'start' | 'stop' | 'restart') => {
    powerMutation.mutate({ id: server.id, action });
};

const confirmDelete = (server: Server) => {
    if (confirm(`Delete server "${server.name}"? This cannot be undone.`)) {
        deleteMutation.mutate(server.id);
    }
};

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
        case 'installing': return 'badge-warning';
        case 'pending': return 'badge-warning';
        default: return 'badge-secondary';
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Servers</h1>
                <p class="text-secondary-400">Manage all virtual machines</p>
            </div>
            <button class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create Server
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading servers...</div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!servers?.length" class="card card-body text-center py-12">
            <h3 class="text-lg font-medium text-white mb-2">No servers yet</h3>
            <p class="text-secondary-400">Servers will appear here once created.</p>
        </div>

        <!-- Servers table -->
        <div v-else class="card">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>User</th>
                            <th>Node</th>
                            <th>Resources</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="server in servers" :key="server.id">
                            <td>
                                <div class="font-medium text-white">{{ server.name }}</div>
                                <div class="text-xs text-secondary-500">{{ server.uuid }}</div>
                            </td>
                            <td>
                                <span :class="statusColor(server.status)">{{ server.status }}</span>
                            </td>
                            <td>{{ server.user?.name ?? '-' }}</td>
                            <td>
                                <span v-if="server.node">{{ server.node.name }}</span>
                                <span v-else class="text-secondary-500">-</span>
                            </td>
                            <td class="text-sm">
                                <div>{{ server.cpu }} vCPU</div>
                                <div>{{ server.memory_formatted }}</div>
                            </td>
                            <td class="text-right space-x-1">
                                <button
                                    v-if="server.status === 'stopped'"
                                    @click="handlePower(server, 'start')"
                                    class="btn-ghost btn-sm text-success-500"
                                    title="Start"
                                >
                                    <PlayIcon class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="server.status === 'running'"
                                    @click="handlePower(server, 'stop')"
                                    class="btn-ghost btn-sm text-danger-500"
                                    title="Stop"
                                >
                                    <StopIcon class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="server.status === 'running'"
                                    @click="handlePower(server, 'restart')"
                                    class="btn-ghost btn-sm"
                                    title="Restart"
                                >
                                    <ArrowPathIcon class="w-4 h-4" />
                                </button>
                                <RouterLink :to="`/admin/servers/${server.id}`" class="btn-ghost btn-sm">
                                    <EyeIcon class="w-4 h-4" />
                                </RouterLink>
                                <button
                                    @click="confirmDelete(server)"
                                    class="btn-ghost btn-sm text-danger-500"
                                    title="Delete"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
