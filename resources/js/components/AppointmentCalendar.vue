<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { EventInput, DateSelectArg, EventClickArg, DateClickArg } from '@fullcalendar/core';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

interface Appointment {
    id: number | string;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
    is_followup?: boolean;
    prescription_id?: number;
}

interface DisabledDate {
    id: number;
    date: string;
    reason?: string | null;
}

interface Props {
    appointments?: Appointment[];
    disabledDates?: DisabledDate[];
    routePrefix?: string;
    canManageDisabledDates?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    appointments: () => [],
    disabledDates: () => [],
    routePrefix: '/admin/appointments',
    canManageDisabledDates: false,
});

const calendarRef = ref<InstanceType<typeof FullCalendar>>();
const showNext30Days = ref(true);
const currentView = ref('dayGridMonth');

// Disabled date management
const disableDateDialogOpen = ref(false);
const selectedDateForDisable = ref('');
const disableReason = ref('');
const isDisabling = ref(false);
const { success: showSuccess, error: showError } = useToast();

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
                // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
                const [year, month, day] = apt.appointment_date.split('-').map(Number);
                const aptDate = new Date(year, month - 1, day);
                aptDate.setHours(0, 0, 0, 0);
                return aptDate >= today && aptDate <= maxDate;
            }
            return true;
        })
        .map((apt) => {
            if (!apt.appointment_date) return null;

            // Check if this is a follow-up appointment (no time required)
            const isFollowUp = apt.is_followup === true;

            // Parse date
            const [year, month, day] = apt.appointment_date.split('-').map(Number);
            let start: Date;
            let end: Date;
            let allDay = false;

            if (isFollowUp) {
                // Follow-up appointments are all-day events
                start = new Date(year, month - 1, day);
                start.setHours(0, 0, 0, 0);
                end = new Date(start);
                end.setDate(end.getDate() + 1);
                allDay = true;
            } else {
                // Regular appointments need a time
                if (!apt.appointment_time) return null;
                const [hours, minutes] = apt.appointment_time.split(':').map(Number);
                start = new Date(year, month - 1, day, hours, minutes);
                // Default duration: 30 minutes
                end = new Date(start.getTime() + 30 * 60 * 1000);
            }

            // Get status color - follow-ups have a distinct color
            const statusColor = isFollowUp 
                ? {
                    backgroundColor: '#e7d5ff',
                    borderColor: '#9333ea',
                    textColor: '#581c87',
                }
                : getStatusColor(apt.status);

            return {
                id: apt.id.toString(),
                title: isFollowUp 
                    ? `${apt.pet_name} - Follow-up Check-up`
                    : `${apt.pet_name} - ${apt.appointment_type}`,
                start: start.toISOString(),
                end: end.toISOString(),
                allDay: allDay,
                backgroundColor: statusColor.backgroundColor,
                borderColor: statusColor.borderColor,
                textColor: statusColor.textColor,
                extendedProps: {
                    appointmentId: apt.id,
                    petName: apt.pet_name,
                    appointmentType: apt.appointment_type,
                    petType: apt.pet_type,
                    status: apt.status,
                    time: isFollowUp ? null : formatTime(apt.appointment_time),
                    isFollowUp: isFollowUp,
                    prescriptionId: apt.prescription_id || null,
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
    // Check if this is a disabled date event
    if (clickInfo.event.extendedProps.isDisabledDate && props.canManageDisabledDates) {
        const disabledDateId = clickInfo.event.extendedProps.disabledDateId;
        const disabledDate = props.disabledDates?.find(d => d.id === disabledDateId);
        console.log('Disabled date clicked - ID:', disabledDateId, 'found:', disabledDate);
        if (disabledDate) {
            showDisableDateDialog(disabledDate.date);
        } else {
            // Fallback: extract date from event start
            const eventDate = clickInfo.event.start;
            if (eventDate) {
                const dateStr = eventDate.toISOString().split('T')[0];
                console.log('Fallback: using event start date:', dateStr);
                showDisableDateDialog(dateStr);
            }
        }
        return;
    }
    
    // Check if this is a follow-up appointment
    if (clickInfo.event.extendedProps.isFollowUp) {
        const prescriptionId = clickInfo.event.extendedProps.prescriptionId;
        if (prescriptionId) {
            // Navigate to prescription page - handle both admin and client routes
            const isAdminRoute = props.routePrefix.includes('/admin');
            if (isAdminRoute) {
                router.visit(`/admin/prescriptions/${prescriptionId}`);
            } else {
                // For clients, just show a toast or don't navigate (prescriptions may not be directly viewable)
                // Alternatively, we could navigate to the appointment if available
                console.log('Follow-up check-up date clicked for prescription:', prescriptionId);
                // For now, don't navigate for clients - they'll see the follow-up date on the calendar
            }
        }
        return;
    }
    
    // Otherwise, handle as regular appointment click
    const appointmentId = clickInfo.event.extendedProps.appointmentId;
    if (appointmentId && typeof appointmentId !== 'string' || !appointmentId.toString().startsWith('followup-')) {
        router.visit(`${props.routePrefix}/${appointmentId}`);
    }
};

const handleDateSelect = (selectInfo: DateSelectArg) => {
    // This is for date range selection (click and drag)
    if (props.canManageDisabledDates) {
        const selectedDate = selectInfo.startStr.split('T')[0];
        showDisableDateDialog(selectedDate);
        selectInfo.view.calendar.unselect();
    }
};

const handleDateClick = (clickInfo: DateClickArg) => {
    // This is for single date clicks
    if (props.canManageDisabledDates) {
        // Extract date part (YYYY-MM-DD) from the dateStr
        // dateStr can be in format "2026-01-15" or "2026-01-15T00:00:00"
        const selectedDate = clickInfo.dateStr.includes('T') 
            ? clickInfo.dateStr.split('T')[0] 
            : clickInfo.dateStr;
        console.log('Date clicked:', selectedDate, 'Full dateStr:', clickInfo.dateStr);
        showDisableDateDialog(selectedDate);
    } else {
        console.log('Date clicked but canManageDisabledDates is false');
    }
};

const formatSelectedDate = (dateStr: string): string => {
    if (!dateStr) return '';
    try {
        // Parse the date string (format: YYYY-MM-DD)
        const [year, month, day] = dateStr.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return dateStr; // Return original string if invalid
        }
        
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } catch (error) {
        return dateStr; // Return original string on error
    }
};

const showDisableDateDialog = (date: string) => {
    console.log('showDisableDateDialog called with date:', date);
    selectedDateForDisable.value = date;
    disableReason.value = '';
    disableDateDialogOpen.value = true;
    console.log('selectedDateForDisable.value is now:', selectedDateForDisable.value);
};

const checkIfDateIsDisabled = (date: string): DisabledDate | null => {
    if (!date || !props.disabledDates) return null;
    const found = props.disabledDates.find(d => d.date === date);
    console.log('checkIfDateIsDisabled - date:', date, 'found:', found, 'all disabled dates:', props.disabledDates);
    return found || null;
};

const getCurrentReason = (): string => {
    const disabledDate = checkIfDateIsDisabled(selectedDateForDisable.value);
    const reason = disabledDate?.reason;
    console.log('getCurrentReason - disabledDate:', disabledDate, 'reason:', reason, 'type:', typeof reason);
    if (!reason || (typeof reason === 'string' && !reason.trim())) {
        return 'No reason provided';
    }
    return reason;
};

const currentReasonDisplay = computed(() => {
    return getCurrentReason();
});

const handleDisableDate = async () => {
    if (!selectedDateForDisable.value) return;

    const existing = checkIfDateIsDisabled(selectedDateForDisable.value);
    
    if (existing) {
        // Enable the date (remove from disabled dates)
        try {
            isDisabling.value = true;
            await axios.delete(`/admin/appointments/disabled-dates/${existing.id}`);
            showSuccess('Date enabled successfully', 'Clients can now book appointments on this date.');
            disableDateDialogOpen.value = false;
            router.reload({ only: ['disabledDates'] });
        } catch (error: any) {
            showError('Failed to enable date', error.response?.data?.message || 'An error occurred');
        } finally {
            isDisabling.value = false;
        }
    } else {
        // Disable the date
        try {
            isDisabling.value = true;
            await axios.post('/admin/appointments/disabled-dates', {
                date: selectedDateForDisable.value,
                reason: disableReason.value || null,
            });
            showSuccess('Date disabled successfully', 'Clients will not be able to book appointments on this date.');
            disableDateDialogOpen.value = false;
            router.reload({ only: ['disabledDates'] });
        } catch (error: any) {
            showError('Failed to disable date', error.response?.data?.message || 'An error occurred');
        } finally {
            isDisabling.value = false;
        }
    }
};

// Convert disabled dates to FullCalendar events format
const disabledDateEvents = computed<EventInput[]>(() => {
    if (!props.disabledDates || !Array.isArray(props.disabledDates)) {
        return [];
    }

    return props.disabledDates.map((disabledDate) => {
        const date = new Date(disabledDate.date);
        date.setHours(0, 0, 0, 0);
        const nextDay = new Date(date);
        nextDay.setDate(nextDay.getDate() + 1);

        return {
            id: `disabled-${disabledDate.id}`,
            title: 'Unavailable - Click to enable',
            start: date.toISOString(),
            end: nextDay.toISOString(),
            allDay: true,
            backgroundColor: '#dc3545',
            borderColor: '#dc3545',
            textColor: '#ffffff',
            display: 'background',
            interactive: true,
            extendedProps: {
                isDisabledDate: true,
                disabledDateId: disabledDate.id,
                reason: disabledDate.reason,
            },
        };
    });
});

// Combine appointment events and disabled date events
const allEvents = computed<EventInput[]>(() => {
    return [...events.value, ...disabledDateEvents.value];
});

// Calendar options
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: allEvents.value,
    eventClick: handleEventClick,
    dateClick: handleDateClick,
    select: handleDateSelect,
    selectable: props.canManageDisabledDates,
    selectMirror: false,
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

    <!-- Disable/Enable Date Dialog -->
    <Dialog v-model:open="disableDateDialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ checkIfDateIsDisabled(selectedDateForDisable) ? 'Enable Date' : 'Disable Date' }}
                </DialogTitle>
                <DialogDescription>
                    {{ checkIfDateIsDisabled(selectedDateForDisable) 
                        ? 'This date is currently disabled. Enable it to allow clients to book appointments.' 
                        : 'Disable this date to prevent clients from booking appointments. The veterinarian will not be available on this date.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div v-if="!checkIfDateIsDisabled(selectedDateForDisable)" class="space-y-2">
                    <Label for="reason">Reason (Optional)</Label>
                    <Textarea
                        id="reason"
                        v-model="disableReason"
                        placeholder="Enter reason for disabling this date..."
                        rows="3"
                        class="w-full"
                    />
                </div>

                <div v-else class="space-y-2">
                    <Label>Current Reason</Label>
                    <div class="flex h-10 w-full rounded-md border border-input bg-muted px-3 py-2 text-sm text-foreground">
                        {{ currentReasonDisplay }}
                    </div>
                    <p v-if="!currentReasonDisplay || currentReasonDisplay === 'No reason provided'" class="text-sm text-muted-foreground mt-1">
                        No reason was provided when this date was disabled.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="disableDateDialogOpen = false"
                    :disabled="isDisabling"
                >
                    Cancel
                </Button>
                <Button
                    :variant="checkIfDateIsDisabled(selectedDateForDisable) ? 'default' : 'destructive'"
                    @click="handleDisableDate"
                    :disabled="isDisabling"
                >
                    {{ isDisabling 
                        ? (checkIfDateIsDisabled(selectedDateForDisable) ? 'Enabling...' : 'Disabling...') 
                        : (checkIfDateIsDisabled(selectedDateForDisable) ? 'Enable Date' : 'Disable Date') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
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
