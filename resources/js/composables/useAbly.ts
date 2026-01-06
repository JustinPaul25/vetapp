import { ref, type Ref } from 'vue';
import * as Ably from 'ably';
import { usePage } from '@inertiajs/vue3';
// Using fetch instead of axios

interface Notification {
    id: string;
    appointment_id?: number;
    subject: string;
    message: string;
    link?: string;
    patient_name?: string;
    owner_name?: string;
    timestamp: Date;
}

let ablyClient: Ably.Realtime | null = null;
let channels: Ably.RealtimeChannel[] = [];
const notifications: Ref<Notification[]> = ref([]);
const isConnected: Ref<boolean> = ref(false);

export function useAbly() {
    const page = usePage();
    const user = page.props.auth?.user;

    const loadDatabaseNotifications = async () => {
        if (!user) {
            return;
        }

        try {
            // Use appropriate endpoint based on user role
            let endpoint = '/notifications/api/list?limit=20';
            if (!user.roles?.includes('admin') && !user.roles?.includes('staff')) {
                // For clients, use the client notification endpoint
                endpoint = '/notifications/api/list?limit=20';
            }

            const response = await fetch(endpoint, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                const dbNotifications = data.notifications || [];
                const now = new Date();
                
                // Add database notifications to the list (avoid duplicates)
                dbNotifications.forEach((dbNotif: any) => {
                    const dbNotifTime = new Date(dbNotif.created_at);
                    
                    // Skip very recent notifications (last 2 minutes) - they'll come via Ably real-time
                    const timeDiff = now.getTime() - dbNotifTime.getTime();
                    if (timeDiff < 120000) { // 2 minutes
                        return; // Skip this notification, it will come via Ably
                    }
                    
                    // Check if notification already exists by multiple criteria
                    const dbNotifSubject = dbNotif.subject || dbNotif.message || 'Notification';
                    const dbNotifMessage = dbNotif.message || dbNotif.subject || 'Notification';
                    
                    const exists = notifications.value.some((existing) => {
                        // Check by database notification ID
                        if (existing.id === dbNotif.id) {
                            return true;
                        }
                        
                        // Check by subject and message match within 30 seconds
                        const subjectMatch = existing.subject === dbNotifSubject;
                        const messageMatch = existing.message === dbNotifMessage;
                        const existingTimeDiff = Math.abs(existing.timestamp.getTime() - dbNotifTime.getTime());
                        
                        if (subjectMatch && messageMatch && existingTimeDiff < 30000) {
                            return true;
                        }
                        
                        return false;
                    });
                    
                    if (!exists) {
                        const notification: Notification = {
                            id: dbNotif.id,
                            subject: dbNotifSubject,
                            message: dbNotifMessage,
                            link: dbNotif.link || undefined,
                            timestamp: dbNotifTime,
                        };
                        notifications.value.unshift(notification);
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load database notifications:', error);
        }
    };

    const connect = async () => {
        if (!user || ablyClient) {
            return;
        }

        // Load database notifications first
        await loadDatabaseNotifications();

        try {
            // Get Ably token from backend
            const response = await fetch('/api/ably/token', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            const { token } = data;

            // Initialize Ably client
            ablyClient = new Ably.Realtime({
                key: token,
                clientId: `user-${user.id}`,
            });

            ablyClient.connection.on('connected', () => {
                isConnected.value = true;
                console.log('Ably connected');
            });

            ablyClient.connection.on('disconnected', () => {
                isConnected.value = false;
                console.log('Ably disconnected');
            });

            // Subscribe to user-specific channel
            const userChannel = ablyClient.channels.get(`user:${user.id}`);
            userChannel.subscribe('appointment.created', (message) => {
                addNotification(message.data as any);
            });
            userChannel.subscribe('appointment.approved', (message) => {
                addNotification(message.data as any);
            });
            userChannel.subscribe('appointment.rescheduled', (message) => {
                addNotification(message.data as any);
            });
            channels.push(userChannel);

            // Subscribe to admin channel if user is admin
            if (user.roles?.includes('admin')) {
                const adminChannel = ablyClient.channels.get('admin:notifications');
                adminChannel.subscribe('appointment.created', (message) => {
                    addNotification(message.data as any);
                });
                channels.push(adminChannel);
            }

            // Subscribe to staff channel if user is staff
            if (user.roles?.includes('staff')) {
                const staffChannel = ablyClient.channels.get('staff:notifications');
                staffChannel.subscribe('appointment.created', (message) => {
                    addNotification(message.data as any);
                });
                channels.push(staffChannel);
            }
        } catch (error) {
            console.error('Failed to connect to Ably:', error);
        }
    };

    const disconnect = () => {
        channels.forEach((channel) => {
            channel.unsubscribe();
        });
        channels = [];

        if (ablyClient) {
            ablyClient.close();
            ablyClient = null;
        }
        isConnected.value = false;
    };

    const addNotification = (data: any) => {
        // Check for duplicates before adding
        // Check by appointment_id + subject + message similarity (within 30 seconds)
        const now = new Date();
        const isDuplicate = notifications.value.some((existing) => {
            // Same appointment ID and similar subject/message
            if (data.appointment_id && existing.appointment_id === data.appointment_id) {
                const subjectMatch = existing.subject === (data.subject || 'New Appointment');
                const messageMatch = existing.message === (data.message || '');
                const timeDiff = Math.abs(now.getTime() - existing.timestamp.getTime());
                
                // If same appointment, subject, and message, and within 30 seconds, it's a duplicate
                if (subjectMatch && messageMatch && timeDiff < 30000) {
                    return true;
                }
            }
            
            // Also check by subject and message content (for notifications without appointment_id)
            const subjectMatch = existing.subject === (data.subject || 'New Appointment');
            const messageMatch = existing.message === (data.message || '');
            const timeDiff = Math.abs(now.getTime() - existing.timestamp.getTime());
            
            // If same subject and message within 10 seconds, it's likely a duplicate
            if (subjectMatch && messageMatch && timeDiff < 10000) {
                return true;
            }
            
            return false;
        });

        if (isDuplicate) {
            console.log('Duplicate notification detected, skipping:', data.subject);
            return;
        }

        const notification: Notification = {
            id: `${Date.now()}-${Math.random()}`,
            appointment_id: data.appointment_id,
            subject: data.subject || 'New Appointment',
            message: data.message || '',
            link: data.link,
            patient_name: data.patient_name,
            owner_name: data.owner_name,
            timestamp: new Date(),
        };

        notifications.value.unshift(notification);

        // Play notification sound (optional)
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.subject, {
                body: notification.message,
                icon: '/favicon.ico',
            });
        }
    };

    const removeNotification = (id: string) => {
        const index = notifications.value.findIndex((n) => n.id === id);
        if (index > -1) {
            notifications.value.splice(index, 1);
        }
    };

    const clearAllNotifications = () => {
        notifications.value = [];
    };

    const unreadCount = () => {
        return notifications.value.length;
    };

    return {
        notifications,
        isConnected,
        unreadCount,
        removeNotification,
        clearAllNotifications,
        connect,
        disconnect,
    };
}

