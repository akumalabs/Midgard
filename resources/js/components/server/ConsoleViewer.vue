<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/lib/axios';

const route = useRoute();
const uuid = route.params.uuid as string;

const iframeUrl = ref('');
const loading = ref(true);
const error = ref('');

const connect = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get(`/client/servers/${uuid}/console/vnc`);
        // We assume response structure: { ticket, port, node: { fqdn, name }, vmid, name }
        // The controller ServerConsoleController returns json.
        
        // This is a mockup of constructing the PVE URL directly (usually requires more auth handling)
         if (data.node && data.ticket) {
               iframeUrl.value = `https://${data.node.fqdn}:8006/?console=kvm&novnc=1&vmid=${data.vmid}&vmname=${data.name}&node=${data.node.name}&resize=off&port=${data.port}&vncticket=${encodeURIComponent(data.ticket)}`;
         } else {
             // Fallback if structure differs
             error.value = "Invalid console data received.";
         }
        
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to establish console session.';
    } finally {
        loading.value = false;
    }
};

onMounted(connect);
</script>

<template>
    <div class="card h-[600px] flex flex-col overflow-hidden relative bg-black">
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-white z-10 bg-black/50">
            <div class="text-center">
                <div class="animate-spin h-8 w-8 border-2 border-primary-500 border-t-transparent rounded-full mx-auto mb-2"></div>
                Connecting to console...
            </div>
        </div>
        
        <div v-if="error" class="absolute inset-0 flex items-center justify-center text-danger-500 z-10 bg-black/80 p-6 text-center">
            <div>
                <p class="text-lg font-bold mb-2">Connection Failed</p>
                <p>{{ error }}</p>
                <button @click="connect" class="btn-secondary mt-4">Retry</button>
            </div>
        </div>

        <div v-if="!loading && !error && !iframeUrl" class="flex-1 flex items-center justify-center text-white">
            <p>Ready to connect</p>
        </div>
        
        <!-- Direct Iframe (Best effort without dedicated WS proxy) -->
        <iframe 
            v-if="iframeUrl"
            :src="iframeUrl"
            class="w-full h-full border-0"
            allowfullscreen
        ></iframe>
    </div>
</template>
