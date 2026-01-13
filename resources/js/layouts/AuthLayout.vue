<script setup lang="ts">
// Auth layout - minimal centered layout for login/register
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-secondary-950 px-4">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold gradient-text">Midgard</h1>
            <p class="text-secondary-400 mt-2">Server Management Panel</p>
        </div>

        <!-- Content -->
        <div class="w-full max-w-md">
            <RouterView />
        </div>

        <!-- Footer -->
        <p class="mt-8 text-secondary-600 text-sm">
            &copy; {{ new Date().getFullYear() }} Midgard. All rights reserved.
        </p>
    </div>
</template>
