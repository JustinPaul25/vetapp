<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Bell, CheckCheck, ExternalLink } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { dashboard } from '@/routes';
import axios from 'axios';
import { useAbly } from '@/composables/useAbly';

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

// Initialize real-time notifications
const { notifications: realTimeNotifications, connect, disconnect } = useAbly();

// Combine server-side notifications with real-time notifications
const allNotifications = ref<Notification[]>([...props.notifications.data]);

// Track which notifications we've marked as read in the UI
const readNotificationIds = ref<Set<string>>(new Set(
    props.notifications.data.filter(n => n.read_at).map(n => n.id)
));

// Update allNotifications when real-time notifications arrive
watch(realTimeNotifications, (newRealTimeNotifs) => {
    // Trigger a refresh from database when new real-time notifications arrive
    // This ensures we get the latest data including any that were just saved to DB
    refreshNotifications();
}, { deep: true });

// Refresh notifications from database
const refreshNotifications = async () => {
    try {
        const response = await fetch(`/notifications/api/list?limit=${props.notifications.per_page}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            const dbNotifications = data.notifications || [];

            // Merge database notifications with current list
            // Use database notifications as source of truth for read status and content
            const notificationMap = new Map<string, Notification>();

            // Add database notifications first (these are the source of truth)
            dbNotifications.forEach((dbNotif: any) => {
                const notification: Notification = {
                    id: dbNotif.id,
                    type: dbNotif.type || 'appointment',
                    subject: dbNotif.subject || dbNotif.message || 'Notification',
                    link: dbNotif.link || null,
                    read_at: dbNotif.read_at || null,
                    created_at: dbNotif.created_at,
                    time_ago: dbNotif.time_ago || formatTimeAgo(new Date(dbNotif.created_at)),
                };
                notificationMap.set(dbNotif.id, notification);
            });

            // Add any real-time notifications that aren't in database yet (very recent ones)
            // Only add if they're not already represented in database notifications
            realTimeNotifications.value.forEach((rtNotif) => {
                // Check if this real-time notification matches any database notification
                const matchingDbNotif = Array.from(notificationMap.values()).find(n => {
                    // Match by subject (most reliable)
                    const subjectMatch = n.subject === rtNotif.subject || 
                                       n.subject.includes(rtNotif.message) ||
                                       rtNotif.subject.includes(n.subject);
                    
                    // Also check by timestamp (within 5 minutes)
                    const rtTime = rtNotif.timestamp.getTime();
                    const dbTime = new Date(n.created_at).getTime();
                    const timeMatch = Math.abs(rtTime - dbTime) < 5 * 60 * 1000; // 5 minutes
                    
                    return subjectMatch && timeMatch;
                });

                if (!matchingDbNotif) {
                    // This is a new real-time notification not yet in database
                    const notification: Notification = {
                        id: rtNotif.id,
                        type: 'appointment',
                        subject: rtNotif.subject,
                        link: rtNotif.link || null,
                        read_at: null,
                        created_at: rtNotif.timestamp.toISOString(),
                        time_ago: formatTimeAgo(rtNotif.timestamp),
                    };
                    notificationMap.set(rtNotif.id, notification);
                }
            });

            // Update the list (sort by created_at descending)
            allNotifications.value = Array.from(notificationMap.values()).sort((a, b) => {
                return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
            });

            // Update read status set
            readNotificationIds.value = new Set(
                allNotifications.value.filter(n => n.read_at).map(n => n.id)
            );
        }
    } catch (error) {
        console.error('Failed to refresh notifications:', error);
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Notifications', href: '#' },
];

const markAsRead = async (notificationId: string) => {
    try {
        // Optimistically update UI
        const notification = allNotifications.value.find(n => n.id === notificationId);
        if (notification && !notification.read_at) {
            notification.read_at = new Date().toISOString();
            readNotificationIds.value.add(notificationId);
        }

        await axios.post(`/notifications/${notificationId}/read`);
        
        // Refresh to get accurate data
        await refreshNotifications();
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
        // Revert optimistic update on error
        await refreshNotifications();
    }
};

const markAllAsRead = async () => {
    try {
        // Optimistically update UI
        allNotifications.value.forEach(n => {
            if (!n.read_at) {
                n.read_at = new Date().toISOString();
                readNotificationIds.value.add(n.id);
            }
        });

        await axios.post('/notifications/read-all');
        
        // Refresh to get accurate data
        await refreshNotifications();
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error);
        // Revert optimistic update on error
        await refreshNotifications();
    }
};

const unreadCount = computed(() => {
    return allNotifications.value.filter(n => !n.read_at).length;
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

const formatTimeAgo = (date: Date) => {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) {
        return 'Just now';
    } else if (minutes < 60) {
        return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
    } else if (hours < 24) {
        return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
    } else if (days < 7) {
        return `${days} day${days !== 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString();
    }
};

// Set up periodic refresh interval
let refreshInterval: ReturnType<typeof setInterval> | null = null;

// Connect to real-time notifications on mount
onMounted(() => {
    connect();
    // Refresh notifications initially
    refreshNotifications();
    // Set up periodic refresh every 30 seconds to ensure we don't miss anything
    refreshInterval = setInterval(refreshNotifications, 30000);
});

// Clean up on unmount
onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    disconnect();
});

// Get notifications for current page (handle pagination)
const displayedNotifications = computed(() => {
    if (props.notifications.current_page === 1) {
        // On first page, show all notifications (including real-time ones)
        return allNotifications.value.slice(0, props.notifications.per_page);
    } else {
        // On other pages, only show paginated database notifications
        return props.notifications.data;
    }
});

// Compute total count (including real-time notifications on first page)
const totalCount = computed(() => {
    if (props.notifications.current_page === 1) {
        // On first page, use the merged count (includes any new real-time notifications)
        return Math.max(allNotifications.value.length, props.notifications.total);
    } else {
        // On other pages, use server-side total
        return props.notifications.total;
    }
});
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
                                {{ totalCount }} total notification{{ totalCount !== 1 ? 's' : '' }}
                                <span v-if="unreadCount > 0">
                                    ({{ unreadCount }} unread)
                                </span>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="displayedNotifications.length === 0" class="py-12 text-center">
                        <Bell class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-4 text-sm text-gray-500">No notifications found</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="notification in displayedNotifications"
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
                            {{ Math.min(props.notifications.current_page * props.notifications.per_page, totalCount) }}
                            of {{ totalCount }} results
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






