<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi, nodeApi, userApi } from '@/api';
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

// Fetch nodes for dropdown
const { data: nodes } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Fetch users for dropdown
const { data: users } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => userApi.list(),
});

// Modal state
const showModal = ref(false);
const formData = ref({
    name: '',
    user_id: '' as string | number,
    node_id: '' as string | number,
    cpu: 1,
    memory: 1024, // 1GB in MB
    disk: 10240,  // 10GB in MB
});
const formError = ref<string | null>(null);

const openCreate = () => {
    formData.value = {
        name: '',
        user_id: '',
        node_id: '',
        cpu: 1,
        memory: 1024,
        disk: 10240,
    };
    formError.value = null;
    showModal.value = true;
};

// Create mutation
const createMutation = useMutation({
    mutationFn: async () => {
        return adminServerApi.create({
            ...formData.value,
            memory: formData.value.memory * 1024 * 1024, // Convert MB to bytes
            disk: formData.value.disk * 1024 * 1024,     // Convert MB to bytes
        });
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
        showModal.value = false;
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || 'Failed to create server';
    },
});

const handleSubmit = () => {
    formError.value = null;
    createMutation.mutate();
};

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
            <button @click="openCreate" class="btn-primary">
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
            <p class="text-secondary-400 mb-4">Create your first server to get started.</p>
            <button @click="openCreate" class="btn-primary mx-auto">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create Server
            </button>
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

        <!-- Create Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="card relative z-10 w-full max-w-lg">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">Create Server</h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <!-- Error -->
                        <div v-if="formError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ formError }}
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="label">Server Name</label>
                            <input v-model="formData.name" type="text" class="input" required placeholder="my-server" />
                        </div>

                        <!-- User -->
                        <div>
                            <label class="label">Owner</label>
                            <select v-model="formData.user_id" class="input" required>
                                <option value="">Select a user</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Node -->
                        <div>
                            <label class="label">Node</label>
                            <select v-model="formData.node_id" class="input" required>
                                <option value="">Select a node</option>
                                <option v-for="node in nodes" :key="node.id" :value="node.id">
                                    {{ node.name }} ({{ node.fqdn }})
                                </option>
                            </select>
                        </div>

                        <!-- Resources -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="label">CPU Cores</label>
                                <input v-model.number="formData.cpu" type="number" class="input" min="1" max="32" required />
                            </div>
                            <div>
                                <label class="label">Memory (MB)</label>
                                <input v-model.number="formData.memory" type="number" class="input" min="512" step="512" required />
                            </div>
                            <div>
                                <label class="label">Disk (MB)</label>
                                <input v-model.number="formData.disk" type="number" class="input" min="1024" step="1024" required />
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                                Cancel
                            </button>
                            <button type="submit" :disabled="createMutation.isPending.value" class="btn-primary flex-1">
                                {{ createMutation.isPending.value ? 'Creating...' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
