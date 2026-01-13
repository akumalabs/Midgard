<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { addressPoolApi, nodeApi } from '@/api';
import type { AddressPool, Address } from '@/api/addressPools';
import {
    PlusIcon,
    TrashIcon,
    PencilIcon,
    GlobeAltIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch pools
const { data: pools, isLoading } = useQuery({
    queryKey: ['admin', 'address-pools'],
    queryFn: () => addressPoolApi.list(),
});

// Fetch nodes for assignment
const { data: nodes } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Create Pool Modal
const showCreateModal = ref(false);
const editingPool = ref<AddressPool | null>(null);
const createFormData = ref({
    name: '',
    node_ids: [] as number[],
});
const createError = ref<string | null>(null);

// Add IPs Modal
const showAddModal = ref(false);
const addingToPool = ref<AddressPool | null>(null);
const addMode = ref<'range' | 'cidr' | 'single'>('range');
const addFormData = ref({
    addresses: '',
    start: '',
    end: '',
    cidr_input: '',
    gateway: '',
    cidr: 24,
});
const addError = ref<string | null>(null);

// View addresses modal
const showViewModal = ref(false);
const viewingPool = ref<AddressPool | null>(null);
const poolAddresses = ref<Address[]>([]);
const loadingAddresses = ref(false);

const openCreate = () => {
    editingPool.value = null;
    createFormData.value = { name: '', node_ids: [] };
    createError.value = null;
    showCreateModal.value = true;
};

const openEdit = (pool: AddressPool) => {
    editingPool.value = pool;
    createFormData.value = {
        name: pool.name,
        node_ids: pool.nodes?.map((n: any) => n.id) || [],
    };
    createError.value = null;
    showCreateModal.value = true;
};

const openAddAddresses = (pool: AddressPool) => {
    addingToPool.value = pool;
    addFormData.value = {
        addresses: '',
        start: '',
        end: '',
        cidr_input: '',
        gateway: '',
        cidr: 24,
    };
    addError.value = null;
    showAddModal.value = true;
};

const openViewAddresses = async (pool: AddressPool) => {
    viewingPool.value = pool;
    loadingAddresses.value = true;
    showViewModal.value = true;
    try {
        poolAddresses.value = await addressPoolApi.getAddresses(pool.id);
    } catch (e) {
        poolAddresses.value = [];
    }
    loadingAddresses.value = false;
};

// Create/Edit Pool mutation
const createMutation = useMutation({
    mutationFn: async () => {
        const data = {
            name: createFormData.value.name,
            node_ids: createFormData.value.node_ids,
        };
        if (editingPool.value) {
            return addressPoolApi.update(editingPool.value.id, data);
        } else {
            return addressPoolApi.create(data);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showCreateModal.value = false;
    },
    onError: (err: any) => {
        createError.value = err?.response?.data?.message || 'Failed to save pool';
    },
});

// Add addresses mutation
const addAddressesMutation = useMutation({
    mutationFn: async () => {
        if (!addingToPool.value) return;
        
        if (addMode.value === 'range') {
            // Add range
            return addressPoolApi.addRange(addingToPool.value.id, {
                start: addFormData.value.start,
                end: addFormData.value.end,
                cidr: addFormData.value.cidr,
                gateway: addFormData.value.gateway,
            });
        } else if (addMode.value === 'cidr') {
            // Parse CIDR and add as range
            const { networkStart, networkEnd, cidr } = parseCidr(addFormData.value.cidr_input);
            if (!networkStart || !networkEnd) {
                throw new Error('Invalid CIDR notation');
            }
            return addressPoolApi.addRange(addingToPool.value.id, {
                start: networkStart,
                end: networkEnd,
                cidr: cidr,
                gateway: addFormData.value.gateway,
            });
        } else {
            // Manual - add individual addresses
            const ips = addFormData.value.addresses
                .split('\n')
                .map(a => a.trim())
                .filter(a => a);
            
            const addresses = ips.map(ip => ({
                address: ip,
                cidr: addFormData.value.cidr,
                gateway: addFormData.value.gateway,
                type: 'ipv4',
            }));
            return addressPoolApi.addAddresses(addingToPool.value.id, addresses);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showAddModal.value = false;
    },
    onError: (err: any) => {
        addError.value = err?.response?.data?.message || err?.message || 'Failed to add addresses';
    },
});

// Delete address mutation
const deleteAddressMutation = useMutation({
    mutationFn: async (addressId: number) => {
        return addressPoolApi.deleteAddress(addressId);
    },
    onSuccess: () => {
        if (viewingPool.value) {
            openViewAddresses(viewingPool.value);
        }
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
    },
});

// Delete pool mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => addressPoolApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
    },
});

const confirmDelete = (pool: AddressPool) => {
    if (confirm(`Delete pool "${pool.name}"? All addresses will be removed.`)) {
        deleteMutation.mutate(pool.id);
    }
};

const confirmDeleteAddress = (address: Address) => {
    if (confirm(`Delete IP ${address.address}?`)) {
        deleteAddressMutation.mutate(address.id);
    }
};

const handleCreate = () => {
    createError.value = null;
    createMutation.mutate();
};

const handleAddAddresses = () => {
    addError.value = null;
    addAddressesMutation.mutate();
};

// Parse CIDR notation to get start/end IPs
function parseCidr(cidr: string): { networkStart: string; networkEnd: string; cidr: number } {
    const [ip, prefix] = cidr.split('/');
    if (!ip || !prefix) return { networkStart: '', networkEnd: '', cidr: 24 };
    
    const prefixNum = parseInt(prefix);
    if (prefixNum < 24 || prefixNum > 30) {
        addError.value = 'CIDR prefix must be between /24 and /30';
        return { networkStart: '', networkEnd: '', cidr: prefixNum };
    }
    
    const parts = ip.split('.').map(Number);
    const hostBits = 32 - prefixNum;
    const numHosts = Math.pow(2, hostBits) - 2;
    
    const baseNum = (parts[0] << 24) + (parts[1] << 16) + (parts[2] << 8) + parts[3];
    const networkNum = baseNum & (~0 << hostBits);
    
    const startAddr = networkNum + 1;
    const endAddr = networkNum + numHosts;
    
    const networkStart = `${(startAddr >> 24) & 255}.${(startAddr >> 16) & 255}.${(startAddr >> 8) & 255}.${startAddr & 255}`;
    const networkEnd = `${(endAddr >> 24) & 255}.${(endAddr >> 16) & 255}.${(endAddr >> 8) & 255}.${endAddr & 255}`;
    
    return { networkStart, networkEnd, cidr: prefixNum };
}

const toggleNode = (nodeId: number) => {
    const idx = createFormData.value.node_ids.indexOf(nodeId);
    if (idx > -1) {
        createFormData.value.node_ids.splice(idx, 1);
    } else {
        createFormData.value.node_ids.push(nodeId);
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">IP Address Pools</h1>
                <p class="text-secondary-400">Manage IP address allocation (IPAM)</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create Pool
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading pools...</div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!pools?.length" class="card card-body text-center py-12">
            <GlobeAltIcon class="w-12 h-12 mx-auto mb-4 text-secondary-500" />
            <h3 class="text-lg font-medium text-white mb-2">No IP pools yet</h3>
            <p class="text-secondary-400 mb-4">Create an IP pool to manage addresses for your servers.</p>
            <button @click="openCreate" class="btn-primary mx-auto">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create IP Pool
            </button>
        </div>

        <!-- Pools grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="pool in pools" :key="pool.id" class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="font-medium text-white">{{ pool.name }}</h3>
                    <div class="flex gap-1">
                        <button @click="openViewAddresses(pool)" class="btn-ghost btn-sm" title="View IPs">
                            <EyeIcon class="w-4 h-4" />
                        </button>
                        <button @click="openEdit(pool)" class="btn-ghost btn-sm" title="Edit">
                            <PencilIcon class="w-4 h-4" />
                        </button>
                        <button @click="confirmDelete(pool)" class="btn-ghost btn-sm text-danger-500" title="Delete">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="card-body space-y-3">
                    <!-- Nodes -->
                    <div v-if="pool.nodes?.length" class="text-sm">
                        <span class="text-secondary-400">Nodes: </span>
                        <span class="text-white">{{ pool.nodes.map(n => n.name).join(', ') }}</span>
                    </div>
                    
                    <div class="pt-3 border-t border-secondary-700">
                        <div class="flex justify-between items-center">
                            <div class="text-sm">
                                <span class="text-success-500 font-medium">{{ pool.available_count ?? 0 }}</span>
                                <span class="text-secondary-400"> / {{ pool.addresses_count ?? 0 }} available</span>
                            </div>
                            <button @click="openAddAddresses(pool)" class="btn-primary btn-sm">
                                <PlusIcon class="w-4 h-4 mr-1" />
                                Add IPs
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Pool Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingPool ? 'Edit Pool' : 'Create Pool' }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleCreate" class="card-body space-y-4">
                        <div v-if="createError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ createError }}
                        </div>

                        <div>
                            <label class="label">Pool Name</label>
                            <input v-model="createFormData.name" type="text" class="input" required placeholder="e.g. Public IPv4" />
                        </div>

                        <!-- Node Assignment -->
                        <div v-if="nodes?.length">
                            <label class="label">Assign to Nodes (optional)</label>
                            <div class="space-y-2 max-h-40 overflow-y-auto border border-secondary-700 rounded p-2">
                                <label 
                                    v-for="node in nodes" 
                                    :key="node.id" 
                                    class="flex items-center gap-2 p-2 rounded hover:bg-secondary-800 cursor-pointer"
                                >
                                    <input 
                                        type="checkbox" 
                                        :checked="createFormData.node_ids.includes(node.id)"
                                        @change="toggleNode(node.id)"
                                        class="rounded border-secondary-600"
                                    />
                                    <span class="text-white">{{ node.name }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showCreateModal = false" class="btn-secondary flex-1">Cancel</button>
                            <button type="submit" :disabled="createMutation.isPending.value" class="btn-primary flex-1">
                                {{ createMutation.isPending.value ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Add Addresses Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showAddModal = false"></div>
                <div class="card relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            Add IPs to {{ addingToPool?.name }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleAddAddresses" class="card-body space-y-4">
                        <div v-if="addError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ addError }}
                        </div>

                        <!-- Gateway -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Gateway</label>
                                <input v-model="addFormData.gateway" type="text" class="input" required placeholder="192.168.1.1" />
                            </div>
                            <div>
                                <label class="label">CIDR (prefix)</label>
                                <input v-model.number="addFormData.cidr" type="number" class="input" min="1" max="32" required placeholder="24" />
                            </div>
                        </div>

                        <!-- Mode selector -->
                        <div>
                            <label class="label">Add Method</label>
                            <div class="flex gap-2">
                                <button 
                                    type="button" 
                                    @click="addMode = 'range'" 
                                    :class="['btn-sm flex-1', addMode === 'range' ? 'btn-primary' : 'btn-secondary']"
                                >
                                    IP Range
                                </button>
                                <button 
                                    type="button" 
                                    @click="addMode = 'cidr'" 
                                    :class="['btn-sm flex-1', addMode === 'cidr' ? 'btn-primary' : 'btn-secondary']"
                                >
                                    CIDR Block
                                </button>
                                <button 
                                    type="button" 
                                    @click="addMode = 'single'" 
                                    :class="['btn-sm flex-1', addMode === 'single' ? 'btn-primary' : 'btn-secondary']"
                                >
                                    Manual
                                </button>
                            </div>
                        </div>

                        <!-- Range input -->
                        <div v-if="addMode === 'range'" class="space-y-2">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Start IP</label>
                                    <input v-model="addFormData.start" type="text" class="input" required placeholder="192.168.1.10" />
                                </div>
                                <div>
                                    <label class="label">End IP</label>
                                    <input v-model="addFormData.end" type="text" class="input" required placeholder="192.168.1.50" />
                                </div>
                            </div>
                            <p class="text-xs text-secondary-500">Max 256 IPs per range</p>
                        </div>

                        <!-- CIDR input -->
                        <div v-else-if="addMode === 'cidr'" class="space-y-2">
                            <div>
                                <label class="label">Subnet (CIDR notation)</label>
                                <input v-model="addFormData.cidr_input" type="text" class="input" required placeholder="192.168.1.0/24" />
                            </div>
                            <p class="text-xs text-secondary-500">
                                All usable host IPs will be added. Supports /24 to /30
                            </p>
                        </div>

                        <!-- Individual input -->
                        <div v-else>
                            <label class="label">IP Addresses (one per line)</label>
                            <textarea 
                                v-model="addFormData.addresses" 
                                class="input" 
                                rows="5" 
                                required 
                                placeholder="192.168.1.10&#10;192.168.1.11&#10;192.168.1.12"
                            ></textarea>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showAddModal = false" class="btn-secondary flex-1">Cancel</button>
                            <button type="submit" :disabled="addAddressesMutation.isPending.value" class="btn-primary flex-1">
                                {{ addAddressesMutation.isPending.value ? 'Adding...' : 'Add IPs' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- View Addresses Modal -->
        <Teleport to="body">
            <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showViewModal = false"></div>
                <div class="card relative z-10 w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
                    <div class="card-header flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">
                            IPs in {{ viewingPool?.name }}
                        </h2>
                        <button @click="openAddAddresses(viewingPool!)" class="btn-primary btn-sm">
                            <PlusIcon class="w-4 h-4 mr-1" />
                            Add IPs
                        </button>
                    </div>
                    <div class="card-body overflow-y-auto">
                        <div v-if="loadingAddresses" class="text-center py-8 text-secondary-400">
                            Loading...
                        </div>
                        <div v-else-if="!poolAddresses.length" class="text-center py-8 text-secondary-400">
                            No IP addresses in this pool. Click "Add IPs" to add some.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Gateway</th>
                                        <th>Status</th>
                                        <th>Server</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="addr in poolAddresses" :key="addr.id">
                                        <td class="font-mono text-white">{{ addr.address }}/{{ addr.cidr }}</td>
                                        <td class="font-mono text-secondary-400">{{ addr.gateway }}</td>
                                        <td>
                                            <span v-if="addr.server_id" class="badge-warning">In Use</span>
                                            <span v-else class="badge-success">Available</span>
                                        </td>
                                        <td>{{ addr.server?.name || '-' }}</td>
                                        <td class="text-right">
                                            <button 
                                                v-if="!addr.server_id"
                                                @click="confirmDeleteAddress(addr)" 
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
                    <div class="card-footer">
                        <button @click="showViewModal = false" class="btn-secondary w-full">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
