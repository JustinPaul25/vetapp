<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="auth-layout flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-3 font-medium"
                    >
                        <img src="/media/logo.png" alt="Panabo City ANIMED" class="h-20" />
                        <span class="text-blue-600 font-bold text-lg">Panabo City ANIMED</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                        <p class="text-center text-sm text-gray-600">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-layout {
    background: linear-gradient(to bottom, #ffffff 0%, #f3f4f6 100%);
}
</style>
