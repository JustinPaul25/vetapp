<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { Bell } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAbly } from '@/composables/useAbly';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';

const { notifications, unreadCount, removeNotification, clearAllNotifications, connect, disconnect } = useAbly();

onMounted(() => {
    connect();
});

onUnmounted(() => {
    disconnect();
});

const hasNotifications = computed(() => unreadCount() > 0);
const notificationCount = computed(() => unreadCount());

const formatTime = (date: Date) => {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);

    if (seconds < 60) {
        return 'Just now';
    } else if (minutes < 60) {
        return `${minutes}m ago`;
    } else if (hours < 24) {
        return `${hours}h ago`;
    } else {
        return date.toLocaleDateString();
    }
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative h-9 w-9 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <Bell class="h-5 w-5" />
                <Badge
                    v-if="hasNotifications"
                    class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 p-0 text-xs text-white"
                >
                    {{ notificationCount > 9 ? '9+' : notificationCount }}
                </Badge>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <div class="flex items-center justify-between p-4">
                <h3 class="text-sm font-semibold">Notifications</h3>
                <Button
                    v-if="hasNotifications"
                    variant="ghost"
                    size="sm"
                    class="h-auto p-0 text-xs text-blue-600 hover:text-blue-700"
                    @click="clearAllNotifications"
                >
                    Clear all
                </Button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <div
                    v-if="!hasNotifications"
                    class="p-8 text-center text-sm text-gray-500"
                >
                    No new notifications
                </div>
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="border-b border-gray-100 p-4 hover:bg-gray-50"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <Link
                                v-if="notification.link"
                                :href="notification.link"
                                class="block"
                                @click="removeNotification(notification.id)"
                            >
                                <p class="text-sm font-medium text-gray-900">
                                    {{ notification.subject }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ notification.message }}
                                </p>
                                <div
                                    v-if="notification.patient_name || notification.owner_name"
                                    class="mt-2 text-xs text-gray-400"
                                >
                                    <span v-if="notification.patient_name">
                                        Pet: {{ notification.patient_name }}
                                    </span>
                                    <span
                                        v-if="notification.patient_name && notification.owner_name"
                                        class="mx-1"
                                    >
                                        •
                                    </span>
                                    <span v-if="notification.owner_name">
                                        Owner: {{ notification.owner_name }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ formatTime(notification.timestamp) }}
                                </p>
                            </Link>
                            <div v-else>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ notification.subject }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ notification.message }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ formatTime(notification.timestamp) }}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-6 w-6 text-gray-400 hover:text-gray-600"
                            @click="removeNotification(notification.id)"
                        >
                            ×
                        </Button>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-100 p-2">
                <Link
                    href="/notifications"
                    class="block w-full rounded-md px-3 py-2 text-center text-sm font-medium text-blue-600 hover:bg-blue-50"
                >
                    See all notifications
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

