<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/lib/axios';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { ArchiveBoxIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const uuid = route.params.uuid as string;

const backups = ref<any[]>([]);
const loading = ref(true);
const creating = ref(false);

const showCreateModal = ref(false);
const createForm = ref({
    mode: 'snapshot',
    compression: 'zstd'
});

const fetchBackups = async () => {
    loading.value = true;
    try {
        const { data } = await api.get(`/client/servers/${uuid}/backups`);
        backups.value = data.data || data.backups || data || [];
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const createBackup = async () => {
    creating.value = true;
    try {
        await api.post(`/client/servers/${uuid}/backups`, createForm.value);
        showCreateModal.value = false;
        fetchBackups();
    } catch (error) {
        alert('Failed to create backup');
    } finally {
        creating.value = false;
    }
};

const deleteBackup = async (id: number) => {
    if (!confirm('Are you sure? This cannot be undone.')) return;
    try {
        await api.delete(`/client/servers/${uuid}/backups/${id}`);
        fetchBackups();
    } catch (error) {
        alert('Failed to delete backup');
    }
};

const restoreBackup = async (id: number) => {
    if (!confirm('Are you sure? This will overwrite the current server state.')) return;
    try {
        await api.post(`/client/servers/${uuid}/backups/${id}/restore`);
        alert('Restore started');
    } catch (error) {
        alert('Failed to start restore');
    }
};

onMounted(fetchBackups);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-[var(--text-base)]">Backups</h2>
            <button @click="showCreateModal = true" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create Backup
            </button>
        </div>

        <!-- Backups List -->
        <div class="card overflow-hidden">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-base)]">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="6" class="p-4 text-center text-muted">Loading backups...</td>
                    </tr>
                    <tr v-else-if="backups.length === 0">
                        <td colspan="6" class="p-8 text-center text-muted">
                            <ArchiveBoxIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                            No backups found
                        </td>
                    </tr>
                    <tr v-for="backup in backups" :key="backup.id">
                        <td class="font-medium">{{ backup.name }}</td>
                        <td>{{ backup.mode }}</td>
                        <td>{{ backup.size ? (backup.size / 1024 / 1024).toFixed(2) + ' MB' : '-' }}</td>
                        <td>{{ new Date(backup.created_at).toLocaleString() }}</td>
                        <td>
                            <span :class="{
                                'badge-success': backup.status === 'completed',
                                'badge-warning': backup.status === 'creating' || backup.status === 'restoring',
                                'badge-danger': backup.status === 'failed',
                                'badge-secondary': backup.status === 'pending'
                            }" class="badge capitalize">
                                {{ backup.status }}
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <button @click="restoreBackup(backup.id)" class="text-[var(--primary-base)] hover:underline text-sm disabled:opacity-50" :disabled="backup.status !== 'completed'">Restore</button>
                            <button @click="deleteBackup(backup.id)" class="text-[var(--text-muted)] hover:text-red-500 transition-colors">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Modal -->
        <TransitionRoot appear :show="showCreateModal" as="template">
            <Dialog as="div" @close="showCreateModal = false" class="relative z-50">
                <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100" leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
                </TransitionChild>

                <div class="fixed inset-0 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100" leave="duration-200 ease-in" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95">
                            <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-2xl bg-[var(--bg-surface)] p-6 text-left align-middle shadow-xl transition-all border border-[var(--border-base)]">
                                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-[var(--text-base)]">
                                    Create Backup
                                </DialogTitle>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="label">Mode</label>
                                        <select v-model="createForm.mode" class="input">
                                            <option value="snapshot">Snapshot (Live)</option>
                                            <option value="suspend">Suspend (Safe)</option>
                                            <option value="stop">Stop (Offline)</option>
                                        </select>
                                        <p class="text-xs text-muted mt-1">Snapshot is fastest but may be inconsistent. Stop is safest.</p>
                                    </div>
                                    <div>
                                        <label class="label">Compression</label>
                                        <select v-model="createForm.compression" class="input">
                                            <option value="zstd">ZSTD (Recommended)</option>
                                            <option value="gzip">GZIP</option>
                                            <option value="lzo">LZO</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end space-x-3">
                                    <button @click="showCreateModal = false" class="btn-ghost">Cancel</button>
                                    <button @click="createBackup" :disabled="creating" class="btn-primary">
                                        {{ creating ? 'Starting...' : 'Create Backup' }}
                                    </button>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>
