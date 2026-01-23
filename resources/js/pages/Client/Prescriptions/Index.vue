<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FileText, Download, Search, ArrowUpDown, ArrowUp, ArrowDown, CalendarCheck, Printer, Eye, Pill } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import { router } from '@inertiajs/vue3';

interface Diagnosis {
    id: number;
    disease: string;
}

interface Prescription {
    id: number;
    appointment_id: number;
    appointment_type: string;
    appointment_date: string | null;
    pet_name: string;
    pet_type: string;
    pet_breed: string;
    symptoms: string;
    issued_on: string;
    created_at: string;
    follow_up_date: string | null;
    diagnoses: Diagnosis[];
    medicines_count: number;
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
    { title: 'My Prescriptions', href: '#' },
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
    router.get('/prescriptions', {
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
    router.get('/prescriptions', {
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
    
    router.get('/prescriptions', {
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

const downloadPrescription = (prescriptionId: number) => {
    window.open(`/prescriptions/${prescriptionId}/download`, '_blank');
};

const printPrescription = (prescriptionId: number) => {
    window.open(`/prescriptions/${prescriptionId}/print`, '_blank');
};

const viewPrescription = (prescriptionId: number) => {
    router.visit(`/prescriptions/${prescriptionId}`);
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="My Prescriptions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                My Prescriptions
                            </CardTitle>
                            <CardDescription>
                                View all prescriptions given to your pets by the veterinarian
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
                                placeholder="Search by pet name, type, or disease..."
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
                                    <th class="text-left p-3 font-semibold">Pet Name</th>
                                    <th class="text-left p-3 font-semibold">Pet Type</th>
                                    <th class="text-left p-3 font-semibold">Appointment Type</th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('appointment_date')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Appointment Date
                                            <component :is="getSortIcon('appointment_date')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('created_at')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Issued On
                                            <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">Medicines</th>
                                    <th class="text-left p-3 font-semibold">Follow-up</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="prescription in prescriptions.data"
                                    :key="prescription.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">{{ prescription.pet_name }}</td>
                                    <td class="p-3">
                                        <div class="text-sm">
                                            <div class="font-medium">{{ prescription.pet_type }}</div>
                                            <div class="text-muted-foreground text-xs">{{ prescription.pet_breed }}</div>
                                        </div>
                                    </td>
                                    <td class="p-3">{{ prescription.appointment_type }}</td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ formatDate(prescription.appointment_date) }}
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ formatDate(prescription.issued_on) }}
                                    </td>
                                    <td class="p-3">
                                        <Badge variant="outline" class="flex items-center gap-1 w-fit">
                                            <Pill class="h-3 w-3" />
                                            {{ prescription.medicines_count }} {{ prescription.medicines_count === 1 ? 'medicine' : 'medicines' }}
                                        </Badge>
                                    </td>
                                    <td class="p-3">
                                        <div v-if="prescription.follow_up_date" class="flex items-center gap-1 text-sm">
                                            <CalendarCheck class="h-4 w-4 text-primary" />
                                            <span class="font-medium">{{ formatDate(prescription.follow_up_date) }}</span>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="viewPrescription(prescription.id)"
                                            >
                                                <Eye class="h-4 w-4 mr-2" />
                                                View
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="printPrescription(prescription.id)"
                                            >
                                                <Printer class="h-4 w-4 mr-2" />
                                                Print
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="downloadPrescription(prescription.id)"
                                            >
                                                <Download class="h-4 w-4 mr-2" />
                                                Download
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="prescriptions.data.length === 0">
                                    <td colspan="8" class="p-8 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center gap-2">
                                            <FileText class="h-12 w-12 opacity-50" />
                                            <p>No prescriptions found</p>
                                            <p class="text-sm">Prescriptions will appear here once your pets receive treatment from the veterinarian.</p>
                                        </div>
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
                                :href="`/prescriptions${buildQueryString(prescriptions.current_page - 1)}`"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="prescriptions.current_page < prescriptions.last_page"
                                :href="`/prescriptions${buildQueryString(prescriptions.current_page + 1)}`"
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
