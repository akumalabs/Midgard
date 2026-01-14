<script setup lang="ts">
import { ref, watch } from 'vue';
import { useMutation } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import {
    XMarkIcon,
    KeyIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    serverUuid: string;
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const activeTab = ref<'password' | 'danger'>('password');

// Password change
const newPassword = ref('');
const confirmPassword = ref('');
const passwordError = ref('');

const passwordMutation = useMutation({
    mutationFn: () => clientServerApi.updatePassword(props.serverUuid, newPassword.value),
    onSuccess: () => {
        newPassword.value = '';
        confirmPassword.value = '';
        passwordError.value = '';
        alert('Password updated successfully. Please reboot the server to apply changes.');
    },
    onError: (error: any) => {
        passwordError.value = error.response?.data?.message || 'Failed to update password';
    },
});

const handlePasswordSubmit = () => {
    passwordError.value = '';
    
    if (newPassword.value.length < 8) {
        passwordError.value = 'Password must be at least 8 characters';
        return;
    }
    
    if (newPassword.value !== confirmPassword.value) {
        passwordError.value = 'Passwords do not match';
        return;
    }
    
    passwordMutation.mutate();
};

// Reset on close
watch(() => props.show, (val) => {
    if (!val) {
        newPassword.value = '';
        confirmPassword.value = '';
        passwordError.value = '';
        activeTab.value = 'password';
    }
});
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="show" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="emit('close')"
        >
            <div class="bg-secondary-900 rounded-xl shadow-2xl w-full max-w-lg border border-secondary-700 animate-fade-in">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b border-secondary-700">
                    <h2 class="text-lg font-semibold text-white">Server Settings</h2>
                    <button @click="emit('close')" class="text-secondary-400 hover:text-white">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-secondary-700">
                    <button
                        @click="activeTab = 'password'"
                        class="px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'password' 
                            ? 'text-primary-400 border-b-2 border-primary-400' 
                            : 'text-secondary-400 hover:text-white'"
                    >
                        <KeyIcon class="w-4 h-4 inline mr-2" />
                        Password
                    </button>
                    <button
                        @click="activeTab = 'danger'"
                        class="px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'danger' 
                            ? 'text-red-400 border-b-2 border-red-400' 
                            : 'text-secondary-400 hover:text-white'"
                    >
                        <ExclamationTriangleIcon class="w-4 h-4 inline mr-2" />
                        Danger Zone
                    </button>
                </div>

                <!-- Password Tab -->
                <div v-if="activeTab === 'password'" class="p-4 space-y-4">
                    <p class="text-secondary-400 text-sm">
                        Change the root password for your server. A reboot is required to apply the new password.
                    </p>

                    <div v-if="passwordError" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
                        {{ passwordError }}
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">New Password</label>
                        <input
                            v-model="newPassword"
                            type="password"
                            class="input w-full"
                            placeholder="Enter new password"
                            autocomplete="new-password"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">Confirm Password</label>
                        <input
                            v-model="confirmPassword"
                            type="password"
                            class="input w-full"
                            placeholder="Confirm new password"
                            autocomplete="new-password"
                        />
                    </div>

                    <button
                        @click="handlePasswordSubmit"
                        :disabled="passwordMutation.isPending.value || !newPassword || !confirmPassword"
                        class="btn-primary w-full"
                    >
                        <ArrowPathIcon v-if="passwordMutation.isPending.value" class="w-4 h-4 animate-spin mr-2" />
                        Update Password
                    </button>
                </div>

                <!-- Danger Zone Tab -->
                <div v-if="activeTab === 'danger'" class="p-4 space-y-4">
                    <div class="p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                        <h3 class="text-red-400 font-medium mb-2">Reinstall Server</h3>
                        <p class="text-secondary-400 text-sm mb-3">
                            This will completely wipe your server and reinstall the operating system. All data will be lost.
                        </p>
                        <button class="btn-danger" disabled>
                            Reinstall Server (Coming Soon)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
