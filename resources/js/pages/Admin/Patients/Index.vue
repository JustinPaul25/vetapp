<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Heart, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';
import ReportGenerator from '@/components/ReportGenerator.vue';

interface PetType {
    id: number;
    name: string;
}

interface Owner {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: PetType | null;
    owner: Owner | null;
    created_at: string;
}

interface Props {
    patients: {
        data: Patient[];
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
    { title: 'Patients', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const deletePatient = (patientId: number, patientName: string) => {
    const name = patientName || 'this patient';
    if (confirm(`Are you sure you want to delete ${name}?`)) {
        router.delete(`/admin/patients/${patientId}`);
    }
};

const adminPatientsRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/patients${path}`;
    }
    return `/admin/patients${path}`;
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
    router.get('/admin/patients', {
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
    router.get('/admin/patients', {
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
    
    router.get('/admin/patients', {
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

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString();
};

const calculateAge = (birthDate: string | null) => {
    if (!birthDate) return '—';
    const birth = new Date(birthDate);
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    let months = today.getMonth() - birth.getMonth();
    if (months < 0) {
        years--;
        months += 12;
    }
    if (years > 0) {
        return `${years} year${years > 1 ? 's' : ''}`;
    }
    return `${months} month${months > 1 ? 's' : ''}`;
};
</script>

<template>
    <Head title="Patients Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Heart class="h-5 w-5" />
                                Patients Management
                            </CardTitle>
                            <CardDescription>
                                Manage all patients in the system
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <ReportGenerator
                                export-url="/admin/patients/export"
                                report-title="Patients"
                                :filters="{ search: searchQuery, sort_by: sortBy, sort_direction: sortDirection }"
                            />
                            <Link :href="adminPatientsRoute('/create')">
                                <Button>
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Patient
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
                                placeholder="Search by name, breed, owner..."
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
                                            @click="handleSort('pet_name')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Pet Name
                                            <component :is="getSortIcon('pet_name')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">Type</th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('pet_breed')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Breed
                                            <component :is="getSortIcon('pet_breed')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('pet_gender')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Gender
                                            <component :is="getSortIcon('pet_gender')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">Age</th>
                                    <th class="text-left p-3 font-semibold">Owner</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="patient in patients.data"
                                    :key="patient.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">
                                        {{ patient.pet_name || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ patient.pet_type?.name || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ patient.pet_breed }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ patient.pet_gender || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ calculateAge(patient.pet_birth_date) }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        <div v-if="patient.owner">
                                            <div class="font-medium">{{ patient.owner.name }}</div>
                                            <div class="text-xs text-muted-foreground">{{ patient.owner.email }}</div>
                                        </div>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="adminPatientsRoute(`/${patient.id}`)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="adminPatientsRoute(`/${patient.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deletePatient(patient.id, patient.pet_name || '')"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="patients.data.length === 0">
                                    <td colspan="7" class="p-8 text-center text-muted-foreground">
                                        No patients found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="patients.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (patients.current_page - 1) * patients.per_page + 1 }} to
                            {{ Math.min(patients.current_page * patients.per_page, patients.total) }} of
                            {{ patients.total }} patients
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="patients.current_page > 1"
                                :href="adminPatientsRoute(buildQueryString(patients.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="patients.current_page < patients.last_page"
                                :href="adminPatientsRoute(buildQueryString(patients.current_page + 1))"
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
