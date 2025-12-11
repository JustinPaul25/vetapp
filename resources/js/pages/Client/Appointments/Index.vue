<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
// Using native textarea
import { Calendar, Plus, Eye, Search, List } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import axios from 'axios';

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
}

interface Pet {
    id: number;
    pet_name: string;
    pet_type: string;
}

interface AppointmentType {
    id: number;
    name: string;
}

interface Props {
    pets: Pet[];
    appointment_types: AppointmentType[];
}

const props = defineProps<Props>();
const page = usePage();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Appointments', href: '#' },
];

const appointments = ref<Appointment[]>([]);
const loading = ref(false);
const submitting = ref(false);
const searchQuery = ref('');
const isModalOpen = ref(false);
const viewMode = ref<'list' | 'calendar'>('list');

// Booking form
const form = ref({
    pet_id: '',
    appointment_type_id: '',
    appointment_date: '',
    appointment_time: '',
    symptoms: '',
});

const availableTimes = ref<string[]>([]);
const loadingTimes = ref(false);
const errors = ref<Record<string, string[]>>({});

// Get tomorrow's date as minimum date
const minDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().split('T')[0];
});

// Watch for date changes to fetch available times
watch(() => form.value.appointment_date, (newDate) => {
    if (newDate) {
        fetchAvailableTimes(newDate);
    } else {
        availableTimes.value = [];
        form.value.appointment_time = '';
    }
});

const fetchAppointments = async () => {
    loading.value = true;
    try {
        const params: Record<string, any> = {};
        if (searchQuery.value && searchQuery.value.trim()) {
            params['search[value]'] = searchQuery.value.trim();
        }
        
        const response = await axios.get('/appointments', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            params,
        });
        appointments.value = response.data?.data || [];
    } catch (error) {
        console.error('Error fetching appointments:', error);
        appointments.value = [];
    } finally {
        loading.value = false;
    }
};

const fetchAvailableTimes = async (date: string) => {
    loadingTimes.value = true;
    form.value.appointment_time = '';
    try {
        const response = await axios.get('/appointments/times/available', {
            params: { selectedDate: date },
        });
        availableTimes.value = response.data.availableTimes || [];
    } catch (error) {
        console.error('Error fetching available times:', error);
        availableTimes.value = [];
    } finally {
        loadingTimes.value = false;
    }
};

const handleBookAppointment = () => {
    errors.value = {};
    submitting.value = true;
    router.post(
        '/appointments',
        {
            pet_id: form.value.pet_id,
            appointment_type_id: form.value.appointment_type_id,
            appointment_date: form.value.appointment_date,
            appointment_time: form.value.appointment_time,
            symptoms: form.value.symptoms,
        },
        {
            onSuccess: () => {
                isModalOpen.value = false;
                resetForm();
                fetchAppointments();
                submitting.value = false;
            },
            onError: (err) => {
                errors.value = err.errors || {};
                submitting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
};

const resetForm = () => {
    form.value = {
        pet_id: '',
        appointment_type_id: '',
        appointment_date: '',
        appointment_time: '',
        symptoms: '',
    };
    availableTimes.value = [];
    errors.value = {};
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString();
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
    // Convert 24-hour format to 12-hour format if needed
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const getStatusBadgeClass = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'approved':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

// Fetch appointments on mount
fetchAppointments();
</script>

<template>
    <Head title="My Appointments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                My Appointments
                            </CardTitle>
                            <CardDescription>
                                View and manage your pet appointments
                            </CardDescription>
                        </div>
                        <Dialog v-model:open="isModalOpen">
                            <DialogTrigger as-child>
                                <Button @click="resetForm()">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Book Appointment
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-[600px]">
                                <DialogHeader>
                                    <DialogTitle>Book an Appointment</DialogTitle>
                                    <DialogDescription>
                                        Fill in the details to book a new appointment for your pet.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="grid gap-4 py-4">
                                    <div class="grid gap-2">
                                        <Label for="pet_id">Pet</Label>
                                        <SearchableSelect
                                            v-model="form.pet_id"
                                            :options="(props.pets || []).map(pet => ({ value: pet.id.toString(), label: `${pet.pet_name} (${pet.pet_type})` }))"
                                            placeholder="Select your pet"
                                            :disabled="!props.pets || props.pets.length === 0"
                                        />
                                        <p
                                            v-if="errors.pet_id"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors.pet_id[0] }}
                                        </p>
                                        <p
                                            v-if="!props.pets || props.pets.length === 0"
                                            class="text-sm text-muted-foreground"
                                        >
                                            No pets registered. Please add a pet first.
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="appointment_type_id">Appointment Type</Label>
                                        <SearchableSelect
                                            v-model="form.appointment_type_id"
                                            :options="(props.appointment_types || []).map(type => ({ value: type.id.toString(), label: type.name }))"
                                            placeholder="Select appointment type"
                                        />
                                        <p
                                            v-if="errors.appointment_type_id"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors.appointment_type_id[0] }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="grid gap-2">
                                            <Label for="appointment_date">Appointment Date</Label>
                                            <Input
                                                id="appointment_date"
                                                v-model="form.appointment_date"
                                                type="date"
                                                :min="minDate"
                                                required
                                            />
                                            <p
                                                v-if="errors.appointment_date"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.appointment_date[0] }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="appointment_time">Appointment Time</Label>
                                            <SearchableSelect
                                                v-model="form.appointment_time"
                                                :options="availableTimes.map(time => ({ value: time, label: time }))"
                                                :placeholder="
                                                    loadingTimes
                                                        ? 'Loading...'
                                                        : !form.appointment_date
                                                          ? 'Select date first'
                                                          : !availableTimes || availableTimes.length === 0
                                                            ? 'No available times'
                                                            : 'Select time'
                                                "
                                                :disabled="!form.appointment_date || loadingTimes || !availableTimes || availableTimes.length === 0"
                                            />
                                            <p
                                                v-if="errors.appointment_time"
                                                class="text-sm text-destructive"
                                            >
                                                {{ errors.appointment_time[0] }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="symptoms">Symptoms (Optional)</Label>
                                        <textarea
                                            id="symptoms"
                                            v-model="form.symptoms"
                                            placeholder="Describe any symptoms your pet is experiencing..."
                                            rows="3"
                                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <p
                                            v-if="errors.symptoms"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors.symptoms[0] }}
                                        </p>
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        @click="isModalOpen = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        @click="handleBookAppointment"
                                        :disabled="!form.pet_id || !form.appointment_type_id || !form.appointment_date || !form.appointment_time || submitting"
                                    >
                                        <span v-if="submitting">Booking...</span>
                                        <span v-else>Book Appointment</span>
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- View Toggle and Search -->
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div class="flex gap-2 flex-1 max-w-sm">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search appointments..."
                                    class="pl-10"
                                    @keyup.enter="fetchAppointments"
                                />
                            </div>
                            <Button
                                variant="outline"
                                @click="fetchAppointments"
                            >
                                Search
                            </Button>
                            <Button
                                v-if="searchQuery"
                                variant="ghost"
                                @click="searchQuery = ''; fetchAppointments()"
                            >
                                Clear
                            </Button>
                        </div>
                        
                        <!-- View Toggle -->
                        <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
                            <Button
                                :variant="viewMode === 'list' ? 'default' : 'ghost'"
                                size="sm"
                                @click="viewMode = 'list'"
                                class="gap-2"
                            >
                                <List class="h-4 w-4" />
                                List
                            </Button>
                            <Button
                                :variant="viewMode === 'calendar' ? 'default' : 'ghost'"
                                size="sm"
                                @click="viewMode = 'calendar'"
                                class="gap-2"
                            >
                                <Calendar class="h-4 w-4" />
                                Calendar
                            </Button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div
                        v-if="loading"
                        class="flex items-center justify-center p-8"
                    >
                        <div class="text-muted-foreground">Loading appointments...</div>
                    </div>

                    <!-- Calendar View -->
                    <div
                        v-else-if="viewMode === 'calendar'"
                    >
                        <AppointmentCalendar :appointments="appointments || []" />
                    </div>

                    <!-- List View -->
                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-3 font-semibold">Appointment Type</th>
                                    <th class="text-left p-3 font-semibold">Pet Type</th>
                                    <th class="text-left p-3 font-semibold">Pet Name</th>
                                    <th class="text-left p-3 font-semibold">Date</th>
                                    <th class="text-left p-3 font-semibold">Time</th>
                                    <th class="text-left p-3 font-semibold">Status</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="appointment in (appointments || [])"
                                    :key="appointment.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 text-sm font-medium">
                                        {{ appointment.appointment_type }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ appointment.pet_type }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ appointment.pet_name }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ formatDate(appointment.appointment_date) }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ formatTime(appointment.appointment_time) }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(appointment.status)]"
                                        >
                                            {{ appointment.status }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="`/appointments/${appointment.id}`">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!appointments || appointments.length === 0">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        No appointments found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
