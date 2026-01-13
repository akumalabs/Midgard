<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import { z } from 'zod';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const error = ref<string | null>(null);

const schema = toTypedSchema(
    z.object({
        email: z.string().email('Invalid email address'),
        password: z.string().min(1, 'Password is required'),
    })
);

const { defineField, handleSubmit, errors } = useForm({
    validationSchema: schema,
});

const [email, emailAttrs] = defineField('email');
const [password, passwordAttrs] = defineField('password');

const onSubmit = handleSubmit(async (values) => {
    error.value = null;
    try {
        await authStore.login(values.email, values.password);

        // Redirect based on admin status
        const redirect = route.query.redirect as string;
        if (redirect) {
            router.push(redirect);
        } else if (authStore.isAdmin) {
            router.push({ name: 'admin.dashboard' });
        } else {
            router.push({ name: 'client.dashboard' });
        }
    } catch (e: any) {
        error.value = e?.message || 'Invalid credentials. Please try again.';
    }
});
</script>

<template>
    <div class="card animate-fade-in">
        <div class="card-header">
            <h2 class="text-xl font-semibold text-white">Sign in to your account</h2>
        </div>

        <div class="card-body">
            <!-- Error message -->
            <div
                v-if="error"
                class="mb-4 p-3 bg-danger-500/10 border border-danger-500/50 rounded-lg text-danger-500 text-sm"
            >
                {{ error }}
            </div>

            <form @submit="onSubmit" class="space-y-4">
                <!-- Email -->
                <div>
                    <label for="email" class="label">Email address</label>
                    <input
                        id="email"
                        type="email"
                        v-model="email"
                        v-bind="emailAttrs"
                        :class="['input', errors.email && 'input-error']"
                        placeholder="you@example.com"
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-danger-500">
                        {{ errors.email }}
                    </p>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="label">Password</label>
                    <input
                        id="password"
                        type="password"
                        v-model="password"
                        v-bind="passwordAttrs"
                        :class="['input', errors.password && 'input-error']"
                        placeholder="••••••••"
                    />
                    <p v-if="errors.password" class="mt-1 text-sm text-danger-500">
                        {{ errors.password }}
                    </p>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="authStore.loading"
                    class="w-full btn-primary"
                >
                    <span v-if="authStore.loading">Signing in...</span>
                    <span v-else>Sign in</span>
                </button>
            </form>
        </div>
    </div>
</template>
