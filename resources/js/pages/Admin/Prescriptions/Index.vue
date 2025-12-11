<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FileText, Download, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import { router } from '@inertiajs/vue3';

interface Prescription {
    id: number;
    appointment_id: number;
    appointment_type: string;
    pet_type: string;
    pet_breed: string;
    owner_name: string;
    owner_mobile: string;
    owner_email: string;
    issued_on: string;
    created_at: string;
}

interface Props {
    prescriptions: {
        data: Prescription[];
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
    { title: 'Prescriptions', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const buildQueryString = (page?: number) => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (sortBy.value) params.set('sort_by', sortBy.value);
    if (sortDirection.value) params.set('sort_direction', sortDirection.value);
    if (page) params.set('page', page.toString());
    return params.toString() ? `?${params.toString()}` : '';
};

const handleSearch = () => {
    router.get('/admin/prescriptions', {
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
    router.get('/admin/prescriptions', {
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
    
    router.get('/admin/prescriptions', {
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

const downloadPrescription = (appointmentId: number) => {
    window.open(`/admin/appointments/${appointmentId}/prescription`, '_blank');
};
</script>

<template>
    <Head title="Prescriptions Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Prescriptions Management
                            </CardTitle>
                            <CardDescription>
                                View and manage all prescriptions in the system
                            </CardDescription>
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
                                placeholder="Search by pet type, owner name, or disease..."
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
                                    <th class="text-left p-3 font-semibold">Appointment Type</th>
                                    <th class="text-left p-3 font-semibold">Pet Type</th>
                                    <th class="text-left p-3 font-semibold">Breed</th>
                                    <th class="text-left p-3 font-semibold">Owner</th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('created_at')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Issued On
                                            <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="prescription in prescriptions.data"
                                    :key="prescription.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3">{{ prescription.appointment_type }}</td>
                                    <td class="p-3 font-medium">{{ prescription.pet_type }}</td>
                                    <td class="p-3 text-sm text-muted-foreground">{{ prescription.pet_breed }}</td>
                                    <td class="p-3">
                                        <div class="text-sm">
                                            <div class="font-medium">{{ prescription.owner_name }}</div>
                                            <div class="text-muted-foreground">{{ prescription.owner_email }}</div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ prescription.issued_on }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="downloadPrescription(prescription.appointment_id)"
                                            >
                                                <Download class="h-4 w-4 mr-2" />
                                                Download
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="prescriptions.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No prescriptions found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="prescriptions.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (prescriptions.current_page - 1) * prescriptions.per_page + 1 }} to
                            {{ Math.min(prescriptions.current_page * prescriptions.per_page, prescriptions.total) }} of
                            {{ prescriptions.total }} prescriptions
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="prescriptions.current_page > 1"
                                :href="`/admin/prescriptions${buildQueryString(prescriptions.current_page - 1)}`"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="prescriptions.current_page < prescriptions.last_page"
                                :href="`/admin/prescriptions${buildQueryString(prescriptions.current_page + 1)}`"
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
