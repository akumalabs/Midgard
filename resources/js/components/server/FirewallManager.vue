<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/lib/axios';
import { ShieldCheckIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'; // Adjust imports

const route = useRoute();
const uuid = route.params.uuid as string;

const rules = ref<any[]>([]);
const loading = ref(true);
const firewallEnabled = ref(true);

const fetchFirewall = async () => {
    loading.value = true;
    try {
        const { data } = await api.get(`/client/servers/${uuid}/firewall`);
        rules.value = data.rules || [];
        // firewallEnabled.value = data.enabled; // If API returned status
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const toggleFirewall = async () => {
    try {
        await api.post(`/client/servers/${uuid}/firewall/toggle`, { enabled: !firewallEnabled.value });
        firewallEnabled.value = !firewallEnabled.value;
    } catch (error) {
        alert('Failed to toggle firewall');
    }
};

onMounted(fetchFirewall);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-medium text-[var(--text-base)]">Firewall</h2>
                <p class="text-sm text-muted">Manage inbound and outbound traffic rules.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium" :class="firewallEnabled ? 'text-success-500' : 'text-muted'">
                        {{ firewallEnabled ? 'Active' : 'Disabled' }}
                    </span>
                    <button 
                        @click="toggleFirewall"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-secondary-900"
                        :class="firewallEnabled ? 'bg-success-500' : 'bg-secondary-700'"
                    >
                        <span 
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition" 
                            :class="firewallEnabled ? 'translate-x-6' : 'translate-x-1'" 
                        />
                    </button>
                </div>
                
                <button class="btn-secondary text-sm">
                    <PlusIcon class="w-4 h-4 mr-2" />
                    Add Rule
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Action</th>
                        <th>Protocol</th>
                        <th>Source/Dest</th>
                        <th>Ports</th>
                        <th>Comment</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-base)]">
                    <tr v-if="rules.length === 0">
                        <td colspan="7" class="p-8 text-center text-muted">
                            <ShieldCheckIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                            No custom rules defined. Default policy applies.
                        </td>
                    </tr>
                    <tr v-for="(rule, idx) in rules" :key="idx">
                        <td>{{ rule.type }}</td>
                        <td>{{ rule.action }}</td>
                        <td>{{ rule.proto || 'ANY' }}</td>
                        <td>{{ rule.source || rule.dest || 'ANY' }}</td>
                        <td>{{ rule.sport || rule.dport || 'ANY' }}</td>
                        <td>{{ rule.comment }}</td>
                        <td class="text-right">
                             <button class="text-[var(--text-muted)] hover:text-red-500">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
