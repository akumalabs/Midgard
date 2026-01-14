<script setup lang="ts">
import { ref, watch } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi, nodeApi, userApi, templateApi, addressPoolApi } from '@/api';
import type { Server } from '@/types/models';
import type { TemplateGroup } from '@/api/templates';
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

// Fetch address pools
const { data: addressPools } = useQuery({
    queryKey: ['admin', 'address-pools'],
    queryFn: () => addressPoolApi.list(),
});

// Modal state
const showModal = ref(false);
const templateGroups = ref<TemplateGroup[]>([]);
const loadingTemplates = ref(false);

const formData = ref({
    name: '',
    hostname: '',
    password: '',
    user_id: '' as string | number,
    node_id: '' as string | number,
    vmid: '' as string | number, // Custom VM ID
    template_vmid: '',
    cpu: 1,
    memory: 1,      // GB
    disk: 10,       // GB
    bandwidth_limit: 0,  // TB (0 = unlimited)
    ip_address: '',
    address_pool_id: '' as string | number,
});
const formError = ref<string | null>(null);

// Watch for node changes to load templates
watch(() => formData.value.node_id, async (nodeId) => {
    if (nodeId && typeof nodeId === 'number') {
        loadingTemplates.value = true;
        try {
            templateGroups.value = await templateApi.listGroups(nodeId);
        } catch (e) {
            templateGroups.value = [];
        }
        loadingTemplates.value = false;
    } else {
        templateGroups.value = [];
    }
});

const openCreate = () => {
    formData.value = {
        name: '',
        hostname: '',
        password: '',
        user_id: '',
        node_id: '',
        vmid: '',
        template_vmid: '',
        cpu: 1,
        memory: 1,      // GB
        disk: 10,       // GB
        bandwidth_limit: 0,  // TB (0 = unlimited)
        ip_address: '',
        address_pool_id: '',
    };
    templateGroups.value = [];
    formError.value = null;
    showModal.value = true;
};

// Create mutation
const createMutation = useMutation({
    mutationFn: async () => {
        // Sanitize hostname: lowercase, replace spaces with dots, remove invalid chars
        const sanitizeHostname = (name: string): string => {
            return name
                .toLowerCase()
                .replace(/\s+/g, '.')           // Replace spaces with dot
                .replace(/[^a-z0-9.-]/g, '')    // Remove invalid characters (allow dots and hyphens)
                .replace(/\.+/g, '.')           // Replace multiple dots with single
                .replace(/^[.-]|[.-]$/g, '');   // Remove leading/trailing dots/hyphens
        };
        
        const hostname = formData.value.hostname 
            ? sanitizeHostname(formData.value.hostname)
            : sanitizeHostname(formData.value.name);
            
        const data: any = {
            name: formData.value.name,
            hostname: hostname,
            user_id: formData.value.user_id,
            node_id: formData.value.node_id,
            template_vmid: formData.value.template_vmid,
            cpu: formData.value.cpu,
            memory: formData.value.memory * 1024 * 1024 * 1024, // GB to bytes
            disk: formData.value.disk * 1024 * 1024 * 1024,     // GB to bytes
            bandwidth_limit: formData.value.bandwidth_limit ? formData.value.bandwidth_limit * 1024 * 1024 * 1024 * 1024 : null, // TB to bytes
        };
        // Optional: password
        if (formData.value.password) {
            data.password = formData.value.password;
        }
        // Optional: custom VMID (if blank, Proxmox auto-assigns)
        if (formData.value.vmid) {
            data.vmid = Number(formData.value.vmid);
        }
        // IP assignment
        if (formData.value.address_pool_id) {
            data.address_pool_id = formData.value.address_pool_id;
        }
        if (formData.value.ip_address) {
            data.ip_address = formData.value.ip_address;
        }
        return adminServerApi.create(data);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
        showModal.value = false;
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || err?.response?.data?.error || 'Failed to create server';
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
                            <th>VMID</th>
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
                            <td class="font-mono text-secondary-400">{{ server.vmid || '-' }}</td>
                            <td>
                                <div class="font-medium text-white">{{ server.name }}</div>
                                <div class="text-xs text-secondary-500">{{ server.hostname }}</div>
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
                <div class="card relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">Create Server</h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <!-- Error -->
                        <div v-if="formError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ formError }}
                        </div>

                        <!-- Section: Basic Info -->
                        <div class="p-4 bg-secondary-800/30 rounded-lg space-y-4">
                            <h4 class="text-sm font-medium text-secondary-300 border-b border-secondary-700 pb-2">Server Details</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2">
                                    <label class="label">Server Name</label>
                                    <input v-model="formData.name" type="text" class="input" required placeholder="my-server" />
                                </div>
                                <div>
                                    <label class="label">VM ID</label>
                                    <input v-model="formData.vmid" type="number" class="input" min="100" placeholder="Auto" />
                                    <p class="text-xs text-secondary-500 mt-1">Leave blank for auto</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Hostname</label>
                                    <input v-model="formData.hostname" type="text" class="input" placeholder="server1.example.com" />
                                </div>
                                <div>
                                    <label class="label">Server Password</label>
                                    <input v-model="formData.password" type="password" class="input" placeholder="Min 8 characters" minlength="8" />
                                </div>
                            </div>
                        </div>

                        <!-- Section: Assignment -->
                        <div class="p-4 bg-secondary-800/30 rounded-lg space-y-4">
                            <h4 class="text-sm font-medium text-secondary-300 border-b border-secondary-700 pb-2">Assignment</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Owner</label>
                                    <select v-model="formData.user_id" class="input" required>
                                        <option value="">Select user</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }} ({{ user.email }})
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Node</label>
                                    <select v-model="formData.node_id" class="input" required>
                                        <option value="">Select node</option>
                                        <option v-for="node in nodes" :key="node.id" :value="node.id">
                                            {{ node.name }} ({{ node.fqdn }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="label">Template</label>
                                <select v-model="formData.template_vmid" class="input" required :disabled="!formData.node_id">
                                    <option value="">{{ formData.node_id ? (loadingTemplates ? 'Loading...' : 'Select template') : 'Select node first' }}</option>
                                    <optgroup v-for="group in templateGroups" :key="group.id" :label="group.name">
                                        <option v-for="template in group.templates" :key="template.id" :value="template.vmid">
                                            {{ template.name }}
                                        </option>
                                    </optgroup>
                                </select>
                                <p v-if="!templateGroups.length && formData.node_id && !loadingTemplates" class="text-xs text-warning-500 mt-1">
                                    No templates found. Sync templates from Proxmox in the Nodes page.
                                </p>
                            </div>
                        </div>
                        <!-- Section: Resources -->
                        <div class="p-4 bg-secondary-800/30 rounded-lg space-y-4">
                            <h4 class="text-sm font-medium text-secondary-300 border-b border-secondary-700 pb-2">Resources</h4>
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="label text-xs">CPU Cores</label>
                                    <input v-model.number="formData.cpu" type="number" class="input" min="1" max="64" required />
                                </div>
                                <div>
                                    <label class="label text-xs">Memory (GB)</label>
                                    <input v-model.number="formData.memory" type="number" class="input" min="1" step="1" required />
                                </div>
                                <div>
                                    <label class="label text-xs">Disk (GB)</label>
                                    <input v-model.number="formData.disk" type="number" class="input" min="1" step="1" required />
                                </div>
                                <div>
                                    <label class="label text-xs">Bandwidth (TB)</label>
                                    <input v-model.number="formData.bandwidth_limit" type="number" class="input" min="0" step="0.1" placeholder="0 = unlimited" />
                                </div>
                            </div>
                        </div>

                        <!-- Section: Network -->
                        <div class="p-4 bg-secondary-800/30 rounded-lg space-y-4">
                            <h4 class="text-sm font-medium text-secondary-300 border-b border-secondary-700 pb-2">Network</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">IP Pool</label>
                                    <select v-model="formData.address_pool_id" class="input">
                                        <option value="">No auto IP assignment</option>
                                        <option v-for="pool in addressPools" :key="pool.id" :value="pool.id">
                                            {{ pool.name }} ({{ pool.available_count || 0 }} available)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Manual IP (optional)</label>
                                    <input v-model="formData.ip_address" type="text" class="input" placeholder="Leave blank to auto-assign" />
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                                Cancel
                            </button>
                            <button type="submit" :disabled="createMutation.isPending.value" class="btn-primary flex-1">
                                {{ createMutation.isPending.value ? 'Creating...' : 'Create Server' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
