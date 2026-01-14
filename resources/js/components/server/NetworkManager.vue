<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/lib/axios';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { GlobeAltIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const uuid = route.params.uuid as string;

const interfaces = ref<any[]>([]);
const loading = ref(true);
const showAddModal = ref(false);
const adding = ref(false); // Used in template/future implementation

const addForm = ref({ // Used in template/future
    model: 'virtio',
    bridge: 'vmbr0',
    firewall: true,
    rate_limit: 0
});

const fetchNetwork = async () => {
    loading.value = true;
    try {
        // Correct path based on API route definition: apiResource('servers.network') -> /servers/{server}/network
        // And prefix client -> /client/servers/{server}/network
        // Where {server} is the UUID if binding works, or ID. 
        // Let's assume UUID for now as client usually uses UUID.
        const { data } = await api.get(`/client/servers/${uuid}/network`);
        interfaces.value = data.data || data.interfaces || [];
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchNetwork);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-[var(--text-base)]">Network Interfaces</h2>
            <button @click="showAddModal = true" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Interface
            </button>
        </div>

        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>MAC Address</th>
                        <th>Bridge</th>
                        <th>Firewall</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-base)]">
                    <tr v-if="interfaces.length === 0">
                        <td colspan="5" class="p-8 text-center text-muted">
                            <GlobeAltIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                            No interfaces configured (or failed to load)
                        </td>
                    </tr>
                    <tr v-for="(iface, idx) in interfaces" :key="idx">
                        <td>{{ iface.device || 'net' + idx }}</td>
                        <td class="font-mono text-xs">{{ iface.mac }}</td>
                        <td>{{ iface.bridge }}</td>
                        <td>
                            <span :class="iface.firewall ? 'text-success-500' : 'text-danger-500'">
                                {{ iface.firewall ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="text-right">
                             <button class="text-[var(--text-muted)] hover:text-red-500">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Add Modal (Simplified placeholder) -->
         <TransitionRoot appear :show="showAddModal" as="template">
            <Dialog as="div" @close="showAddModal = false" class="relative z-50">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
                <div class="fixed inset-0 overflow-y-auto flex items-center justify-center p-4">
                     <DialogPanel class="w-full max-w-md card p-6">
                        <DialogTitle class="text-lg font-medium mb-4">Add Interface</DialogTitle>
                        <p class="text-muted text-sm mb-4">Adding interfaces requires a restart.</p>
                        <div class="flex justify-end gap-3">
                            <button @click="showAddModal = false" class="btn-ghost">Cancel</button>
                            <button @click="showAddModal = false" class="btn-primary">Add</button>
                        </div>
                     </DialogPanel>
                </div>
            </Dialog>
         </TransitionRoot>
    </div>
</template>
