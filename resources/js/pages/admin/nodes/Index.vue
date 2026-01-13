<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { nodeApi } from '@/api';
import type { Node } from '@/types/models';
import {
    PlusIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    XCircleIcon,
    TrashIcon,
    PencilIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch nodes
const { data: nodes, isLoading, error } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Test connection mutation
const testingNode = ref<number | null>(null);
const testResult = ref<{ id: number; success: boolean; message: string } | null>(null);

const testConnection = async (node: Node) => {
    testingNode.value = node.id;
    testResult.value = null;
    try {
        const result = await nodeApi.testConnection(node.id);
        testResult.value = { id: node.id, success: result.success, message: result.message };
    } catch (e: any) {
        testResult.value = { id: node.id, success: false, message: e?.message || 'Connection failed' };
    } finally {
        testingNode.value = null;
    }
};

// Sync mutation
const syncingNode = ref<number | null>(null);
const syncNode = async (node: Node) => {
    syncingNode.value = node.id;
    try {
        await nodeApi.sync(node.id);
        queryClient.invalidateQueries({ queryKey: ['admin', 'nodes'] });
    } finally {
        syncingNode.value = null;
    }
};

// Delete mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => nodeApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'nodes'] });
    },
});

const confirmDelete = (node: Node) => {
    if (confirm(`Are you sure you want to delete node "${node.name}"?`)) {
        deleteMutation.mutate(node.id);
    }
};

const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Nodes</h1>
                <p class="text-secondary-400">Manage your Proxmox nodes</p>
            </div>
            <button class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Node
            </button>
        </div>

        <!-- Loading state -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading nodes...</div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="card card-body text-center py-12 text-danger-500">
            Failed to load nodes
        </div>

        <!-- Empty state -->
        <div v-else-if="!nodes?.length" class="card card-body text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-secondary-800 flex items-center justify-center">
                <svg class="w-8 h-8 text-secondary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-white mb-2">No nodes yet</h3>
            <p class="text-secondary-400 mb-4">Get started by adding your first Proxmox node.</p>
            <button class="btn-primary mx-auto">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Your First Node
            </button>
        </div>

        <!-- Nodes table -->
        <div v-else class="card">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Location</th>
                            <th>FQDN</th>
                            <th>Resources</th>
                            <th>Servers</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="node in nodes" :key="node.id">
                            <td class="font-medium text-white">{{ node.name }}</td>
                            <td>
                                <span v-if="node.location" class="badge-secondary">
                                    {{ node.location.short_code }}
                                </span>
                                <span v-else class="text-secondary-500">-</span>
                            </td>
                            <td>{{ node.fqdn }}:{{ node.port }}</td>
                            <td class="text-sm">
                                <div>CPU: {{ node.cpu }} cores</div>
                                <div>RAM: {{ formatBytes(node.memory) }}</div>
                            </td>
                            <td>{{ node.servers_count ?? 0 }}</td>
                            <td>
                                <span v-if="node.maintenance_mode" class="badge-warning">
                                    Maintenance
                                </span>
                                <template v-else>
                                    <!-- Test result -->
                                    <span v-if="testResult?.id === node.id && testResult.success" class="badge-success">
                                        <CheckCircleIcon class="w-4 h-4 mr-1" />
                                        Connected
                                    </span>
                                    <span v-else-if="testResult?.id === node.id && !testResult.success" class="badge-danger">
                                        <XCircleIcon class="w-4 h-4 mr-1" />
                                        Error
                                    </span>
                                    <span v-else class="badge-secondary">Unknown</span>
                                </template>
                            </td>
                            <td class="text-right space-x-2">
                                <button
                                    @click="testConnection(node)"
                                    :disabled="testingNode === node.id"
                                    class="btn-ghost btn-sm"
                                    title="Test Connection"
                                >
                                    <CheckCircleIcon v-if="testingNode !== node.id" class="w-4 h-4" />
                                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                                </button>
                                <button
                                    @click="syncNode(node)"
                                    :disabled="syncingNode === node.id"
                                    class="btn-ghost btn-sm"
                                    title="Sync Resources"
                                >
                                    <ArrowPathIcon :class="['w-4 h-4', syncingNode === node.id && 'animate-spin']" />
                                </button>
                                <button class="btn-ghost btn-sm" title="Edit">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button
                                    @click="confirmDelete(node)"
                                    class="btn-ghost btn-sm text-danger-500 hover:text-danger-400"
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
