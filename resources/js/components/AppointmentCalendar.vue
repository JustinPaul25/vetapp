<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { EventInput, DateSelectArg, EventClickArg } from '@fullcalendar/core';

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
}

interface Props {
    appointments?: Appointment[];
}

const props = withDefaults(defineProps<Props>(), {
    appointments: () => [],
});

const calendarRef = ref<InstanceType<typeof FullCalendar>>();
const showNext30Days = ref(true);
const currentView = ref('dayGridMonth');

// Calculate date range for next 30 days
const dateRange = computed(() => {
    if (!showNext30Days.value) {
        return { min: null, max: null };
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 30);
    return {
        min: today,
        max: maxDate,
    };
});

// Convert appointments to FullCalendar events format
const events = computed<EventInput[]>(() => {
    // Ensure appointments is an array before mapping
    if (!props.appointments || !Array.isArray(props.appointments)) {
        return [];
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 30);

    return props.appointments
        .filter((apt) => {
            if (!apt.appointment_date) return false;
            
            // Filter to next 30 days only
            if (showNext30Days.value) {
                const aptDate = new Date(apt.appointment_date);
                aptDate.setHours(0, 0, 0, 0);
                return aptDate >= today && aptDate <= maxDate;
            }
            return true;
        })
        .map((apt) => {
            if (!apt.appointment_date || !apt.appointment_time) return null;

            // Parse date and time
            const [year, month, day] = apt.appointment_date.split('-').map(Number);
            const [hours, minutes] = apt.appointment_time.split(':').map(Number);

            // Create start date
            const start = new Date(year, month - 1, day, hours, minutes);
            
            // Default duration: 30 minutes
            const end = new Date(start.getTime() + 30 * 60 * 1000);

            // Get status color
            const statusColor = getStatusColor(apt.status);

            return {
                id: apt.id.toString(),
                title: `${apt.pet_name} - ${apt.appointment_type}`,
                start: start.toISOString(),
                end: end.toISOString(),
                backgroundColor: statusColor.backgroundColor,
                borderColor: statusColor.borderColor,
                textColor: statusColor.textColor,
                extendedProps: {
                    appointmentId: apt.id,
                    petName: apt.pet_name,
                    appointmentType: apt.appointment_type,
                    petType: apt.pet_type,
                    status: apt.status,
                    time: formatTime(apt.appointment_time),
                },
            };
        })
        .filter(Boolean) as EventInput[];
});

const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed':
            return {
                backgroundColor: '#d1e7dd',
                borderColor: '#198754',
                textColor: '#0f5132',
            };
        case 'approved':
            return {
                backgroundColor: '#cfe2ff',
                borderColor: '#0d6efd',
                textColor: '#084298',
            };
        case 'pending':
            return {
                backgroundColor: '#fff3cd',
                borderColor: '#ffc107',
                textColor: '#856404',
            };
        default:
            return {
                backgroundColor: '#e2e3e5',
                borderColor: '#6c757d',
                textColor: '#41464b',
            };
    }
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

// Count appointments in next 30 days
const upcomingCount = computed(() => {
    if (!props.appointments || !Array.isArray(props.appointments)) return 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 30);
    
    return props.appointments.filter((apt) => {
        if (!apt.appointment_date) return false;
        const aptDate = new Date(apt.appointment_date);
        aptDate.setHours(0, 0, 0, 0);
        return aptDate >= today && aptDate <= maxDate;
    }).length;
});

// FullCalendar event handlers
const handleEventClick = (clickInfo: EventClickArg) => {
    const appointmentId = clickInfo.event.extendedProps.appointmentId;
    if (appointmentId) {
        router.visit(`/appointments/${appointmentId}`);
    }
};

const handleDateSelect = (selectInfo: DateSelectArg) => {
    // Optional: Handle date selection for creating new appointments
    console.log('Date selected:', selectInfo.startStr);
};

// Calendar options
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: events.value,
    eventClick: handleEventClick,
    select: handleDateSelect,
    selectable: false,
    editable: false,
    height: 'auto',
    contentHeight: 'auto',
    aspectRatio: 1.8,
    eventDisplay: 'block',
    dayMaxEvents: 3,
    moreLinkClick: 'popover',
    ...(showNext30Days.value && {
        validRange: {
            start: dateRange.value.min,
            end: dateRange.value.max,
        },
    }),
}));

onMounted(() => {
    // Calendar is ready
});
</script>

<template>
    <div class="w-full">
        <div
            v-if="!Array.isArray(props.appointments)"
            class="flex items-center justify-center p-8"
        >
            <div class="text-muted-foreground">Loading calendar...</div>
        </div>
        <div v-else>
            <!-- Filter Toggle -->
            <div class="filter-toggle flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="showNext30Days"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <span class="text-sm font-medium text-foreground">
                            Show only next 30 days
                        </span>
                    </label>
                    <span
                        v-if="showNext30Days"
                        class="badge bg-primary/20 text-primary"
                    >
                        {{ upcomingCount }} upcoming appointment{{ upcomingCount !== 1 ? 's' : '' }}
                    </span>
                </div>
                <div class="date-range">
                    <span v-if="showNext30Days">
                        Showing appointments from today to {{ 
                            new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toLocaleDateString('en-US', { 
                                month: 'short', 
                                day: 'numeric', 
                                year: 'numeric' 
                            }) 
                        }}
                    </span>
                    <span v-else>
                        Showing all appointments
                    </span>
                </div>
            </div>

            <!-- FullCalendar -->
            <div class="fullcalendar-container">
                <FullCalendar
                    ref="calendarRef"
                    :options="calendarOptions"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Filter Toggle Section */
.filter-toggle {
    background: linear-gradient(to right, #f8f9fa, #ffffff);
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.filter-toggle label {
    user-select: none;
}

.filter-toggle input[type="checkbox"] {
    accent-color: #1a73e8;
    cursor: pointer;
}

.filter-toggle .badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.filter-toggle .date-range {
    font-size: 12px;
    color: #5f6368;
    font-weight: 400;
}

/* FullCalendar Container */
.fullcalendar-container {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* FullCalendar Custom Styling */
:deep(.fc) {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

:deep(.fc-header-toolbar) {
    margin-bottom: 1.5em;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

:deep(.fc-toolbar-title) {
    font-size: 1.5em;
    font-weight: 600;
    color: #2c3e50;
}

:deep(.fc-button) {
    background-color: #fff;
    border: 1px solid #d0d0d0;
    color: #5f6368;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.2s ease;
}

:deep(.fc-button:hover) {
    background-color: #f1f3f4;
    border-color: #dadce0;
    color: #202124;
}

:deep(.fc-button-primary:not(:disabled):active),
:deep(.fc-button-primary:not(:disabled).fc-button-active) {
    background-color: #1a73e8;
    border-color: #1a73e8;
    color: #fff;
}

:deep(.fc-button-primary:disabled) {
    opacity: 0.5;
    cursor: not-allowed;
}

:deep(.fc-today-button) {
    background-color: #1a73e8 !important;
    border-color: #1a73e8 !important;
    color: #fff !important;
}

:deep(.fc-today-button:hover) {
    background-color: #1765cc !important;
}

/* Calendar Grid */
:deep(.fc-daygrid-day) {
    border: 1px solid #e0e0e0;
    background: #fff;
}

:deep(.fc-daygrid-day:hover) {
    background: #f8f9fa;
}

:deep(.fc-day-today) {
    background: #e8f0fe !important;
}

:deep(.fc-daygrid-day-number) {
    padding: 8px;
    font-weight: 500;
    color: #202124;
}

:deep(.fc-day-today .fc-daygrid-day-number) {
    color: #1a73e8;
    font-weight: 600;
}

/* Events */
:deep(.fc-event) {
    border-radius: 4px;
    border-left-width: 3px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s ease;
}

:deep(.fc-event:hover) {
    transform: translateX(2px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    z-index: 10;
}

:deep(.fc-event-title) {
    font-weight: 600;
    padding: 0;
}

/* Week/Day View */
:deep(.fc-timegrid-slot) {
    border-color: #e0e0e0;
}

:deep(.fc-timegrid-col) {
    border-color: #e0e0e0;
}

:deep(.fc-timegrid-now-indicator-line) {
    border-color: #1a73e8;
}

/* More Events Link */
:deep(.fc-more-link) {
    color: #1a73e8;
    font-weight: 500;
    font-size: 12px;
}

:deep(.fc-more-link:hover) {
    color: #1765cc;
    text-decoration: underline;
}

/* Popover */
:deep(.fc-popover) {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

:deep(.fc-popover-header) {
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    padding: 12px;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .filter-toggle {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .filter-toggle .date-range {
        font-size: 11px;
    }

    :deep(.fc-header-toolbar) {
        flex-direction: column;
        gap: 12px;
    }

    :deep(.fc-toolbar-title) {
        font-size: 1.2em;
    }

    :deep(.fc-button) {
        padding: 4px 8px;
        font-size: 12px;
    }

    :deep(.fc-event) {
        font-size: 10px;
        padding: 2px 4px;
    }
}
</style>
