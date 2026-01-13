<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import { onMounted } from 'vue';

const authStore = useAuthStore();

onMounted(async () => {
    // Check if user is authenticated on app load
    await authStore.checkAuth();
});
</script>

<template>
    <div id="midgard-app" class="min-h-screen bg-secondary-950">
        <RouterView />
    </div>
</template>
