<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { clientServerApi } from '@/api';
import { 
    CheckIcon, 
    XMarkIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

interface DeploymentStep {
    name: string;
    status: string;
    error?: string;
    started_at?: string;
    completed_at?: string;
    duration?: number;
}

interface Deployment {
    uuid: string;
    status: string;
    error?: string;
    started_at?: string;
    completed_at?: string;
    steps: DeploymentStep[];
}

const route = useRoute();
const uuid = route.params.uuid as string;

const deployment = ref<Deployment | null>(null);
const polling = ref<ReturnType<typeof setInterval> | null>(null);
const hasActiveDeployment = ref(false);

const fetchDeployment = async () => {
    try {
        const data = await clientServerApi.getDeployment(uuid);
        
        // Only set deployment if we have valid data with steps
        if (data && data.uuid && data.steps && data.steps.length > 0) {
            deployment.value = data;
            hasActiveDeployment.value = true;
            
            // Stop polling if completed or failed
            if (data.status === 'completed' || data.status === 'failed') {
                stopPolling();
                // Auto-hide after 3 seconds on completion
                if (data.status === 'completed') {
                    setTimeout(() => {
                        deployment.value = null;
                        hasActiveDeployment.value = false;
                    }, 3000);
                }
            }
        } else {
            // No active deployment
            deployment.value = null;
            hasActiveDeployment.value = false;
            stopPolling();
        }
    } catch (e) {
        console.error('Failed to fetch deployment:', e);
        deployment.value = null;
        hasActiveDeployment.value = false;
    }
};

const startPolling = () => {
    if (!polling.value) {
        polling.value = setInterval(fetchDeployment, 2000);
    }
};

const stopPolling = () => {
    if (polling.value) {
        clearInterval(polling.value);
        polling.value = null;
    }
};

const formatDuration = (seconds?: number | null): string => {
    if (!seconds) return '';
    if (seconds < 60) return `${seconds.toFixed(1)}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs.toFixed(0)}s`;
};

const getStepIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckIcon;
        case 'failed': return XMarkIcon;
        case 'running': return ArrowPathIcon;
        default: return null;
    }
};

const getStepClass = (status: string) => {
    switch (status) {
        case 'completed': return 'text-green-400';
        case 'failed': return 'text-red-400';
        case 'running': return 'text-blue-400';
        default: return 'text-secondary-500';
    }
};

const isActive = computed(() => hasActiveDeployment.value && deployment.value !== null);

const title = computed(() => {
    if (!deployment.value) return '';
    switch (deployment.value.status) {
        case 'pending': return 'Server Installing';
        case 'running': return 'Server Installing';
        case 'completed': return 'Installation Complete';
        case 'failed': return 'Installation Failed';
        default: return 'Server Installing';
    }
});

onMounted(() => {
    fetchDeployment();
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});

defineExpose({ fetchDeployment });
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="isActive"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
        >
            <div class="bg-secondary-900 rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 border border-secondary-700">
                <!-- Header -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-secondary-800 flex items-center justify-center">
                        <ArrowPathIcon 
                            v-if="deployment?.status === 'running' || deployment?.status === 'pending'"
                            class="w-6 h-6 text-secondary-400 animate-spin" 
                        />
                        <CheckIcon 
                            v-else-if="deployment?.status === 'completed'"
                            class="w-6 h-6 text-green-400" 
                        />
                        <XMarkIcon 
                            v-else-if="deployment?.status === 'failed'"
                            class="w-6 h-6 text-red-400" 
                        />
                    </div>
                    <h2 class="text-xl font-semibold text-white">{{ title }}</h2>
                </div>

                <!-- Steps -->
                <div class="space-y-3">
                    <div 
                        v-for="step in deployment?.steps" 
                        :key="step.name"
                        class="flex items-center justify-between py-2"
                    >
                        <div class="flex items-center gap-3">
                            <!-- Status Icon -->
                            <div class="w-5 h-5 flex items-center justify-center">
                                <component 
                                    v-if="getStepIcon(step.status)"
                                    :is="getStepIcon(step.status)"
                                    class="w-5 h-5"
                                    :class="[
                                        getStepClass(step.status),
                                        step.status === 'running' ? 'animate-spin' : ''
                                    ]"
                                />
                                <span 
                                    v-else 
                                    class="w-2 h-2 rounded-full bg-secondary-600"
                                ></span>
                            </div>
                            
                            <!-- Step Name -->
                            <span 
                                :class="step.status === 'pending' ? 'text-secondary-500' : 'text-white'"
                            >
                                {{ step.name }}
                            </span>
                        </div>
                        
                        <!-- Duration -->
                        <span class="text-secondary-500 text-sm">
                            {{ formatDuration(step.duration) }}
                        </span>
                    </div>
                </div>

                <!-- Error Message -->
                <div 
                    v-if="deployment?.error"
                    class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm"
                >
                    {{ deployment.error }}
                </div>

                <!-- Progress Info -->
                <div v-if="deployment?.status === 'running'" class="mt-4 text-center text-secondary-500 text-sm">
                    Please wait while we configure your server...
                </div>
            </div>
        </div>
    </Teleport>
</template>
