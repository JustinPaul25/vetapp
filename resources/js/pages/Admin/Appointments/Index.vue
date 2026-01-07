<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Calendar, Plus, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown, ChevronDown, ChevronUp, Users, CheckCircle2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

interface Pet {
    id: number;
    pet_name: string;
    pet_type: string;
    pet_breed: string;
    has_prescription?: boolean;
}

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_breed: string;
    owner_name: string;
    owner_email: string;
    owner_mobile: string;
    disease: string;
    pet_count?: number;
    is_multi_pet?: boolean;
    all_pets?: Pet[];
    created_at: string;
    updated_at: string;
}

interface Props {
    appointments: {
        data: Appointment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        search?: string;
        status?: string;
        sort_by?: string;
        sort_direction?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Appointments', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

// Track expanded multi-pet appointments
const expandedAppointments = ref<Set<number>>(new Set());

const toggleExpand = (appointmentId: number) => {
    if (expandedAppointments.value.has(appointmentId)) {
        expandedAppointments.value.delete(appointmentId);
    } else {
        expandedAppointments.value.add(appointmentId);
    }
};

const adminAppointmentsRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/appointments${path}`;
    }
    return `/admin/appointments${path}`;
};

const buildQueryString = (page?: number) => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (statusFilter.value && statusFilter.value !== 'all') params.set('status', statusFilter.value);
    if (sortBy.value) params.set('sort_by', sortBy.value);
    if (sortDirection.value) params.set('sort_direction', sortDirection.value);
    if (page) params.set('page', page.toString());
    return params.toString() ? `?${params.toString()}` : '';
};

const handleSearch = () => {
    router.get('/admin/appointments', {
        search: searchQuery.value,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleStatusChange = (status: string) => {
    statusFilter.value = status;
    router.get('/admin/appointments', {
        search: searchQuery.value,
        status: status !== 'all' ? status : undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchQuery.value = '';
    router.get('/admin/appointments', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleSort = (column: string) => {
    if (sortBy.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDirection.value = 'asc';
    }
    
    router.get('/admin/appointments', {
        search: searchQuery.value,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return ArrowUpDown;
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString();
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
</script>

<template>
    <Head title="Appointments Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Appointments Management
                            </CardTitle>
                            <CardDescription>
                                Manage all appointments in the system
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Status Tabs -->
                    <div class="mb-4">
                        <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                            <button
                                v-for="status in [
                                    { value: 'all', label: 'All' },
                                    { value: 'pending', label: 'Pending' },
                                    { value: 'approved', label: 'Approved' },
                                    { value: 'completed', label: 'Completed' },
                                    { value: 'canceled', label: 'Canceled' }
                                ]"
                                :key="status.value"
                                @click="handleStatusChange(status.value)"
                                :class="[
                                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors text-sm font-medium',
                                    statusFilter === status.value
                                        ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                        : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                                ]"
                            >
                                {{ status.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-4 flex gap-2">
                        <div class="relative flex-1 max-w-sm">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by pet type, owner, disease..."
                                class="pl-10"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                        <Button variant="outline" @click="handleSearch">
                            Search
                        </Button>
                        <Button v-if="searchQuery" variant="ghost" @click="clearSearch">
                            Clear
                        </Button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-3 font-semibold">Owner</th>
                                    <th class="text-left p-3 font-semibold">Email</th>
                                    <th class="text-left p-3 font-semibold">Phone</th>
                                    <th class="text-left p-3 font-semibold">Date & Time</th>
                                    <th class="text-left p-3 font-semibold">Status</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="appointment in appointments.data" :key="appointment.id">
                                    <!-- Standard table row for all appointments (consistent styling) -->
                                    <tr 
                                        class="border-b hover:bg-muted/50 transition-colors"
                                        :class="appointment.is_multi_pet ? 'bg-blue-50/30 dark:bg-blue-950/20' : ''"
                                    >
                                        <td class="p-3 text-sm">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    v-if="appointment.is_multi_pet"
                                                    @click="toggleExpand(appointment.id)"
                                                    class="flex items-center gap-1 hover:opacity-80 transition-opacity"
                                                >
                                                    <component
                                                        :is="expandedAppointments.has(appointment.id) ? ChevronUp : ChevronDown"
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                </button>
                                                <div class="flex items-center gap-2">
                                                    <Users 
                                                        v-if="appointment.is_multi_pet"
                                                        class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                                    />
                                                    <span>{{ appointment.owner_name }}</span>
                                                    <span
                                                        v-if="appointment.is_multi_pet"
                                                        class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                                    >
                                                        {{ appointment.pet_count }} Pet{{ appointment.pet_count > 1 ? 's' : '' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm">
                                            {{ appointment.owner_email }}
                                        </td>
                                        <td class="p-3 text-sm">
                                            {{ appointment.owner_mobile }}
                                        </td>
                                        <td class="p-3 text-sm">
                                            <div>{{ formatDate(appointment.appointment_date) }}</div>
                                            <div class="text-muted-foreground text-xs">{{ formatTime(appointment.appointment_time) }}</div>
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
                                                <Link :href="adminAppointmentsRoute(`/${appointment.id}`)">
                                                    <Button variant="outline" size="sm">
                                                        <Eye class="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Expanded pets list for multi-pet appointments -->
                                    <tr
                                        v-if="appointment.is_multi_pet && expandedAppointments.has(appointment.id)"
                                        class="border-b bg-muted/30"
                                    >
                                        <td class="p-3" colspan="6">
                                            <div class="pl-8 space-y-2">
                                                <div class="text-xs font-semibold text-muted-foreground mb-2">Pets in this appointment:</div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                    <div
                                                        v-for="pet in appointment.all_pets"
                                                        :key="pet.id"
                                                        class="p-3 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 relative"
                                                        :class="pet.has_prescription ? 'border-green-500 dark:border-green-700' : ''"
                                                    >
                                                        <div class="flex items-center justify-between gap-2">
                                                            <div class="flex-1">
                                                                <div class="font-medium text-sm flex items-center gap-2">
                                                                    {{ pet.pet_name }}
                                                                    <span
                                                                        v-if="pet.has_prescription"
                                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                                        title="Has prescription"
                                                                    >
                                                                        <CheckCircle2 class="h-3 w-3" />
                                                                        Prescribed
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-muted-foreground mt-1">
                                                                    {{ pet.pet_type }} • {{ pet.pet_breed }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="appointments.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No appointments found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="appointments.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (appointments.current_page - 1) * appointments.per_page + 1 }} to
                            {{ Math.min(appointments.current_page * appointments.per_page, appointments.total) }} of
                            {{ appointments.total }} appointments
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="appointments.current_page > 1"
                                :href="adminAppointmentsRoute(buildQueryString(appointments.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="appointments.current_page < appointments.last_page"
                                :href="adminAppointmentsRoute(buildQueryString(appointments.current_page + 1))"
                            >
                                <Button variant="outline" size="sm">Next</Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

















