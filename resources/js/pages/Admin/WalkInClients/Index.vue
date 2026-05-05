<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { UserPlus, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown, ChevronDown, ChevronRight, CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import { useToast } from '@/composables/useToast';
import ReportGenerator from '@/components/ReportGenerator.vue';
import { displayEmailUnlessWalkInPlaceholder as displayEmail } from '@/lib/walkInPlaceholderEmail';

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_type: string | null;
    appointment_type: string | null;
    has_prescription: boolean;
    can_prescribe_checkup: boolean;
    checkup_appointment_id: number | null;
}

interface WalkInClient {
    id: number;
    name: string;
    email: string;
    mobile_number: string | null;
    address: string | null;
    patients_count: number;
    patients: Patient[];
    /** ISO 8601 from earliest appointment (visit date + time), or null */
    first_visit_at: string | null;
    /** Same as first visit when the client has appointments; otherwise user row created_at */
    created_at: string;
}

interface Props {
    walkInClients: {
        data: WalkInClient[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        search?: string;
        sort_by?: string;
        sort_direction?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Walk-In Clients', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');
const expandedClients = ref<Set<number>>(new Set());

const { error: showError } = useToast();

const deleteWalkInClient = (clientId: number, clientName: string) => {
    if (confirm(`Are you sure you want to delete ${clientName}? This will also delete all associated patients.`)) {
        router.delete(`/admin/walk_in_clients/${clientId}`, {
            onError: (errors) => {
                const errorMessage = errors.message || 'Failed to delete walk-in client. Please try again.';
                showError(errorMessage);
            },
        });
    }
};

const adminWalkInClientsRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/walk_in_clients${path}`;
    }
    return `/admin/walk_in_clients${path}`;
};

const buildQueryString = (page?: number) => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (sortBy.value) params.set('sort_by', sortBy.value);
    if (sortDirection.value) params.set('sort_direction', sortDirection.value);
    if (page) params.set('page', page.toString());
    return params.toString() ? `?${params.toString()}` : '';
};

const handleSearch = () => {
    router.get('/admin/walk_in_clients', {
        search: searchQuery.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchQuery.value = '';
    router.get('/admin/walk_in_clients', {
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
    
    router.get('/admin/walk_in_clients', {
        search: searchQuery.value,
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

const toggleClientPets = (clientId: number) => {
    if (expandedClients.value.has(clientId)) {
        expandedClients.value.delete(clientId);
    } else {
        expandedClients.value.add(clientId);
    }
};

function formatFirstVisit(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

</script>

<template>
    <Head title="Walk-In Clients Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <UserPlus class="h-5 w-5" />
                                Walk-In Clients Management
                            </CardTitle>
                            <CardDescription>
                                Manage walk-in clients who visit without prior appointments
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <ReportGenerator
                                export-url="/admin/walk_in_clients/export"
                                report-title="Walk-In Clients"
                                :filters="{ search: searchQuery, sort_by: sortBy, sort_direction: sortDirection }"
                            />
                            <Link :href="adminWalkInClientsRoute('/create')">
                                <Button>
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Walk-In Client
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Search -->
                    <div class="mb-4 flex gap-2">
                        <div class="relative flex-1 max-w-sm">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by name, email, phone..."
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
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('name')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Name
                                            <component :is="getSortIcon('name')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('email')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Email
                                            <component :is="getSortIcon('email')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">Mobile</th>
                                    <th class="text-left p-3 font-semibold">Pet</th>
                                    <th class="text-left p-3 font-semibold">First visit</th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('created_at')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Created
                                            <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="client in walkInClients.data" :key="client.id">
                                    <tr class="border-b hover:bg-muted/50">
                                        <td class="p-3 font-medium">
                                            {{ client.name }}
                                        </td>
                                        <td class="p-3 text-sm">
                                            {{ displayEmail(client.email) }}
                                        </td>
                                        <td class="p-3 text-sm">
                                            {{ client.mobile_number || '—' }}
                                        </td>
                                        <td class="p-3">
                                            <Badge variant="secondary">
                                                {{ client.patients_count }} pet{{ client.patients_count !== 1 ? 's' : '' }}
                                            </Badge>
                                            <div
                                                v-if="client.patients.length === 1"
                                                class="text-xs text-muted-foreground mt-1 flex items-center gap-2"
                                            >
                                                <span>{{ client.patients[0].pet_name || 'Unnamed' }} ({{ client.patients[0].pet_type }})</span>
                                                <span v-if="client.patients[0].appointment_type">
                                                    - {{ client.patients[0].appointment_type }}
                                                </span>
                                                <span
                                                    v-if="client.patients[0].has_prescription"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                >
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    Prescribed
                                                </span>
                                                <Link
                                                    v-else-if="client.patients[0].can_prescribe_checkup && client.patients[0].checkup_appointment_id"
                                                    :href="`/admin/appointments/${client.patients[0].checkup_appointment_id}/prescription/create?patient_id=${client.patients[0].id}`"
                                                >
                                                    <Button type="button" variant="outline" size="sm" class="h-6 px-2 text-xs">
                                                        Prescribe
                                                    </Button>
                                                </Link>
                                            </div>
                                            <Button
                                                v-else-if="client.patients.length >= 2"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="mt-1 h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                                @click="toggleClientPets(client.id)"
                                            >
                                                <component :is="expandedClients.has(client.id) ? ChevronDown : ChevronRight" class="h-3 w-3 shrink-0 opacity-70" />
                                                {{ expandedClients.has(client.id) ? 'Hide pets' : 'View pets' }}
                                            </Button>
                                        </td>
                                        <td class="p-3 text-sm text-muted-foreground whitespace-nowrap">
                                            {{ formatFirstVisit(client.first_visit_at) }}
                                        </td>
                                        <td class="p-3 text-sm text-muted-foreground">
                                            {{ new Date(client.created_at).toLocaleDateString() }}
                                        </td>
                                        <td class="p-3">
                                            <div class="flex justify-end gap-2">
                                                <Link :href="adminWalkInClientsRoute(`/${client.id}`)">
                                                    <Button variant="outline" size="sm">
                                                        <Eye class="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Link :href="adminWalkInClientsRoute(`/${client.id}/edit`)">
                                                    <Button variant="outline" size="sm">
                                                        <Edit class="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="deleteWalkInClient(client.id, client.name)"
                                                >
                                                    <Trash2 class="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="client.patients.length >= 2 && expandedClients.has(client.id)"
                                        class="border-b bg-muted/30"
                                    >
                                        <td class="p-3" colspan="7">
                                            <div class="pl-8 space-y-3">
                                                <div class="text-sm font-semibold mb-2">Pets for this walk-in client:</div>
                                                <div
                                                    v-for="patient in client.patients"
                                                    :key="patient.id"
                                                    class="flex items-center justify-between p-3 bg-muted/30 rounded-lg gap-3"
                                                >
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                                                        <div>
                                                            <span class="text-xs text-muted-foreground">Pet Name</span>
                                                            <p class="text-sm font-medium flex items-center gap-2">
                                                                {{ patient.pet_name || 'Unnamed' }}
                                                                <span
                                                                    v-if="patient.has_prescription"
                                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                                >
                                                                    <CheckCircle2 class="h-3 w-3" />
                                                                    Prescribed
                                                                </span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs text-muted-foreground">Pet Type</span>
                                                            <p class="text-sm">{{ patient.pet_type || '—' }}</p>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs text-muted-foreground">Appointment Type</span>
                                                            <p class="text-sm">{{ patient.appointment_type || '—' }}</p>
                                                        </div>
                                                    </div>
                                                    <Link
                                                        v-if="!patient.has_prescription && patient.can_prescribe_checkup && patient.checkup_appointment_id"
                                                        :href="`/admin/appointments/${patient.checkup_appointment_id}/prescription/create?patient_id=${patient.id}`"
                                                    >
                                                        <Button type="button" variant="outline" size="sm">
                                                            Prescribe
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="walkInClients.data.length === 0">
                                    <td colspan="7" class="p-8 text-center text-muted-foreground">
                                        No walk-in clients found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="walkInClients.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (walkInClients.current_page - 1) * walkInClients.per_page + 1 }} to
                            {{ Math.min(walkInClients.current_page * walkInClients.per_page, walkInClients.total) }} of
                            {{ walkInClients.total }} walk-in clients
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="walkInClients.current_page > 1"
                                :href="adminWalkInClientsRoute(buildQueryString(walkInClients.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="walkInClients.current_page < walkInClients.last_page"
                                :href="adminWalkInClientsRoute(buildQueryString(walkInClients.current_page + 1))"
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










