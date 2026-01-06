<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Bell, CheckCheck, ExternalLink } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { dashboard } from '@/routes';
import axios from 'axios';

interface Notification {
    id: string;
    type: string;
    subject: string;
    link: string | null;
    read_at: string | null;
    created_at: string;
    time_ago: string;
}

interface Props {
    notifications: {
        data: Notification[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Notifications', href: '#' },
];

const markAsRead = async (notificationId: string) => {
    try {
        await axios.post(`/notifications/${notificationId}/read`);
        router.reload({ only: ['notifications'] });
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post('/notifications/read-all');
        router.reload({ only: ['notifications'] });
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
    }
};

const unreadCount = computed(() => {
    return props.notifications.data.filter(n => !n.read_at).length;
});

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Notifications</h1>
                    <p class="text-muted-foreground mt-2">View all your notifications</p>
                </div>
                <Button
                    v-if="unreadCount > 0"
                    variant="outline"
                    @click="markAllAsRead"
                >
                    <CheckCheck class="mr-2 h-4 w-4" />
                    Mark all as read
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Notifications</CardTitle>
                            <CardDescription>
                                {{ props.notifications.total }} total notification{{ props.notifications.total !== 1 ? 's' : '' }}
                                <span v-if="unreadCount > 0">
                                    ({{ unreadCount }} unread)
                                </span>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="props.notifications.data.length === 0" class="py-12 text-center">
                        <Bell class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-4 text-sm text-gray-500">No notifications found</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="notification in props.notifications.data"
                            :key="notification.id"
                            class="flex items-start justify-between rounded-lg border p-4 transition-colors hover:bg-gray-50"
                            :class="{ 'bg-blue-50 border-blue-200': !notification.read_at }"
                        >
                            <div class="flex-1">
                                <div class="flex items-start gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p
                                                class="text-sm font-medium"
                                                :class="notification.read_at ? 'text-gray-700' : 'text-gray-900'"
                                            >
                                                {{ notification.subject }}
                                            </p>
                                            <Badge
                                                v-if="!notification.read_at"
                                                variant="default"
                                                class="bg-blue-600 text-white"
                                            >
                                                New
                                            </Badge>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ notification.time_ago }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ formatDate(notification.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4 flex items-center gap-2">
                                <Link
                                    v-if="notification.link"
                                    :href="notification.link"
                                    class="text-blue-600 hover:text-blue-700"
                                    @click="markAsRead(notification.id)"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </Link>
                                <Button
                                    v-if="!notification.read_at"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 w-8 p-0"
                                    @click="markAsRead(notification.id)"
                                >
                                    <CheckCheck class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="props.notifications.last_page > 1"
                        class="mt-6 flex items-center justify-between"
                    >
                        <div class="text-sm text-gray-500">
                            Showing {{ (props.notifications.current_page - 1) * props.notifications.per_page + 1 }} to
                            {{ Math.min(props.notifications.current_page * props.notifications.per_page, props.notifications.total) }}
                            of {{ props.notifications.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="props.notifications.current_page === 1"
                                @click="router.get(`/notifications?page=${props.notifications.current_page - 1}`)"
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="props.notifications.current_page === props.notifications.last_page"
                                @click="router.get(`/notifications?page=${props.notifications.current_page + 1}`)"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>




