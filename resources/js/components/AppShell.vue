<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/toast';
import FlashMessages from '@/components/FlashMessages.vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <FlashMessages />
        <slot />
        <Toaster />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <FlashMessages />
        <slot />
        <Toaster />
    </SidebarProvider>
</template>
