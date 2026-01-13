<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { addressPoolApi } from '@/api';
import type { AddressPool } from '@/api/addressPools';
import {
    PlusIcon,
    TrashIcon,
    PencilIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch pools
const { data: pools, isLoading } = useQuery({
    queryKey: ['admin', 'address-pools'],
    queryFn: () => addressPoolApi.list(),
});

// Modal state
const showModal = ref(false);
const editingPool = ref<AddressPool | null>(null);
const formData = ref({
    name: '',
    gateway: '',
    netmask: '255.255.255.0',
    dns_primary: '8.8.8.8',
    dns_secondary: '8.8.4.4',
});
const formError = ref<string | null>(null);

// Add addresses modal
const showAddModal = ref(false);
const addingToPool = ref<AddressPool | null>(null);
const addMode = ref<'single' | 'range'>('range');
const addFormData = ref({
    addresses: '',
    start: '',
    end: '',
});

const openCreate = () => {
    editingPool.value = null;
    formData.value = {
        name: '',
        gateway: '',
        netmask: '255.255.255.0',
        dns_primary: '8.8.8.8',
        dns_secondary: '8.8.4.4',
    };
    formError.value = null;
    showModal.value = true;
};

const openEdit = (pool: AddressPool) => {
    editingPool.value = pool;
    formData.value = {
        name: pool.name,
        gateway: pool.gateway,
        netmask: pool.netmask,
        dns_primary: pool.dns_primary,
        dns_secondary: pool.dns_secondary || '',
    };
    formError.value = null;
    showModal.value = true;
};

const openAddAddresses = (pool: AddressPool) => {
    addingToPool.value = pool;
    addFormData.value = { addresses: '', start: '', end: '' };
    showAddModal.value = true;
};

// Save mutation
const saveMutation = useMutation({
    mutationFn: async () => {
        if (editingPool.value) {
            return addressPoolApi.update(editingPool.value.id, formData.value);
        } else {
            return addressPoolApi.create(formData.value);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showModal.value = false;
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || 'Failed to save pool';
    },
});

// Add addresses mutation
const addAddressesMutation = useMutation({
    mutationFn: async () => {
        if (!addingToPool.value) return;
        
        if (addMode.value === 'range') {
            return addressPoolApi.addRange(
                addingToPool.value.id,
                addFormData.value.start,
                addFormData.value.end
            );
        } else {
            const addresses = addFormData.value.addresses
                .split('\n')
                .map(a => a.trim())
                .filter(a => a);
            return addressPoolApi.addAddresses(addingToPool.value.id, addresses);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showAddModal.value = false;
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || 'Failed to add addresses';
    },
});

// Delete mutation
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

const handleSubmit = () => {
    formError.value = null;
    saveMutation.mutate();
};

const handleAddAddresses = () => {
    formError.value = null;
    addAddressesMutation.mutate();
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
                        <button @click="openEdit(pool)" class="btn-ghost btn-sm">
                            <PencilIcon class="w-4 h-4" />
                        </button>
                        <button @click="confirmDelete(pool)" class="btn-ghost btn-sm text-danger-500">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="card-body space-y-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-secondary-400">Gateway:</span>
                            <span class="text-white ml-2">{{ pool.gateway }}</span>
                        </div>
                        <div>
                            <span class="text-secondary-400">Netmask:</span>
                            <span class="text-white ml-2">{{ pool.netmask }}</span>
                        </div>
                        <div>
                            <span class="text-secondary-400">DNS 1:</span>
                            <span class="text-white ml-2">{{ pool.dns_primary }}</span>
                        </div>
                        <div>
                            <span class="text-secondary-400">DNS 2:</span>
                            <span class="text-white ml-2">{{ pool.dns_secondary || '-' }}</span>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-t border-secondary-700">
                        <div class="flex justify-between items-center">
                            <div class="text-sm">
                                <span class="text-success-500 font-medium">{{ pool.available_count ?? 0 }}</span>
                                <span class="text-secondary-400"> / {{ pool.addresses_count ?? 0 }} available</span>
                            </div>
                            <button @click="openAddAddresses(pool)" class="btn-secondary btn-sm">
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
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingPool ? 'Edit Pool' : 'Create Pool' }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <div v-if="formError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ formError }}
                        </div>

                        <div>
                            <label class="label">Pool Name</label>
                            <input v-model="formData.name" type="text" class="input" required placeholder="e.g. IPv4 Public" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Gateway</label>
                                <input v-model="formData.gateway" type="text" class="input" required placeholder="192.168.1.1" />
                            </div>
                            <div>
                                <label class="label">Netmask</label>
                                <input v-model="formData.netmask" type="text" class="input" required placeholder="255.255.255.0" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Primary DNS</label>
                                <input v-model="formData.dns_primary" type="text" class="input" required placeholder="8.8.8.8" />
                            </div>
                            <div>
                                <label class="label">Secondary DNS</label>
                                <input v-model="formData.dns_secondary" type="text" class="input" placeholder="8.8.4.4" />
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="btn-secondary flex-1">Cancel</button>
                            <button type="submit" :disabled="saveMutation.isPending.value" class="btn-primary flex-1">
                                {{ saveMutation.isPending.value ? 'Saving...' : 'Save' }}
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
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            Add Addresses to {{ addingToPool?.name }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleAddAddresses" class="card-body space-y-4">
                        <div v-if="formError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ formError }}
                        </div>

                        <!-- Mode selector -->
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
                                @click="addMode = 'single'" 
                                :class="['btn-sm flex-1', addMode === 'single' ? 'btn-primary' : 'btn-secondary']"
                            >
                                Individual IPs
                            </button>
                        </div>

                        <!-- Range input -->
                        <div v-if="addMode === 'range'" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Start IP</label>
                                <input v-model="addFormData.start" type="text" class="input" required placeholder="192.168.1.10" />
                            </div>
                            <div>
                                <label class="label">End IP</label>
                                <input v-model="addFormData.end" type="text" class="input" required placeholder="192.168.1.50" />
                            </div>
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
                                {{ addAddressesMutation.isPending.value ? 'Adding...' : 'Add Addresses' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
