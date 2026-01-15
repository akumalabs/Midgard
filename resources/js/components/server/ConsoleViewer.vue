<script setup lang="ts">
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/lib/axios';
import { ArrowTopRightOnSquareIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const uuid = route.params.uuid as string;

const loading = ref(false);
const error = ref('');
const consoleUrl = ref('');
const consoleData = ref<any>(null);

const openConsole = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await api.get(`/client/servers/${uuid}/console`);
        consoleData.value = response.data.data;
        const data = consoleData.value;
        
        // Build the full noVNC URL for Proxmox
        if (data && data.node && data.ticket) {
            const url = `https://${data.node.fqdn}:8006/?console=kvm&novnc=1&vmid=${data.vmid}&vmname=${encodeURIComponent(data.name || '')}&node=${data.node.name}&resize=off&port=${data.port}&vncticket=${encodeURIComponent(data.ticket)}`;
            consoleUrl.value = url;
            
            // Open in new tab
            window.open(url, '_blank', 'noopener,noreferrer');
        } else {
            error.value = "Invalid console data received. Check node FQDN configuration.";
        }
        
    } catch (err: any) {
        error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to get console credentials.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="card p-8 text-center">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-primary-500/20 flex items-center justify-center">
                <ArrowTopRightOnSquareIcon class="w-8 h-8 text-primary-400" />
            </div>
            
            <h3 class="text-xl font-semibold text-white mb-2">Console Access</h3>
            <p class="text-secondary-400 mb-6">
                Click the button below to open the server console in a new browser tab.
                This will connect you directly to the Proxmox noVNC console.
            </p>
            
            <div v-if="error" class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
                {{ error }}
            </div>
            
            <button
                @click="openConsole"
                :disabled="loading"
                class="btn-primary inline-flex items-center gap-2 px-6 py-3"
            >
                <ArrowPathIcon v-if="loading" class="w-5 h-5 animate-spin" />
                <ArrowTopRightOnSquareIcon v-else class="w-5 h-5" />
                {{ loading ? 'Connecting...' : 'Open Console' }}
            </button>
            
            <p class="text-secondary-500 text-xs mt-4">
                Note: Your browser may block the popup. Please allow popups for this site.
            </p>
            
            <p v-if="consoleData?.node?.fqdn" class="text-warning-500 text-xs mt-2">
                If connection fails, your browser may be blocking the self-signed certificate. 
                <a :href="`https://${consoleData.node.fqdn}:8006`" target="_blank" class="underline hover:text-warning-400">
                    Open Proxmox Panel
                </a> once to accept the certificate.
            </p>
            
            <div v-if="consoleUrl" class="mt-6 p-4 bg-secondary-800 rounded-lg text-left">
                <p class="text-xs text-secondary-400 mb-2">If the popup was blocked, click below:</p>
                <a 
                    :href="consoleUrl" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="text-primary-400 hover:text-primary-300 text-sm break-all"
                >
                    Open Console Manually →
                </a>
            </div>
        </div>
    </div>
</template>
