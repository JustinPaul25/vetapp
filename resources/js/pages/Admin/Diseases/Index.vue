<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Stethoscope, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown, MapPin } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';

interface Disease {
    id: number;
    name: string;
    symptoms_count: number;
    medicines_count: number;
    home_remedy: string | null;
    created_at: string;
}

interface Props {
    diseases: {
        data: Disease[];
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
    { title: 'Diseases', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const deleteDisease = (diseaseId: number, diseaseName: string) => {
    if (confirm(`Are you sure you want to delete the disease "${diseaseName}"?`)) {
        router.delete(`/admin/diseases/${diseaseId}`);
    }
};

const adminDiseasesRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/diseases${path}`;
    }
    return `/admin/diseases${path}`;
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
    router.get('/admin/diseases', {
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
    router.get('/admin/diseases', {
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
    
    router.get('/admin/diseases', {
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
</script>

<template>
    <Head title="Disease Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Stethoscope class="h-5 w-5" />
                                Disease Management
                            </CardTitle>
                            <CardDescription>
                                Manage all diseases, their symptoms, and recommended medicines
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Link href="/admin/diseases/map">
                                <Button variant="outline">
                                    <MapPin class="h-4 w-4 mr-2" />
                                    Disease Map
                                </Button>
                            </Link>
                            <Link :href="adminDiseasesRoute('/create')">
                                <Button>
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Disease
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
                                placeholder="Search by name..."
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
                                    <th class="text-left p-3 font-semibold">Symptoms</th>
                                    <th class="text-left p-3 font-semibold">Medicines</th>
                                    <th class="text-left p-3 font-semibold">Home Remedy</th>
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
                                <tr
                                    v-for="disease in diseases.data"
                                    :key="disease.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">{{ disease.name }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-300">
                                            {{ disease.symptoms_count }} symptom{{ disease.symptoms_count !== 1 ? 's' : '' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-300">
                                            {{ disease.medicines_count }} medicine{{ disease.medicines_count !== 1 ? 's' : '' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground max-w-xs truncate">
                                        {{ disease.home_remedy || 'N/A' }}
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ new Date(disease.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="adminDiseasesRoute(`/${disease.id}`)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="adminDiseasesRoute(`/${disease.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deleteDisease(disease.id, disease.name)"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="diseases.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No diseases found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="diseases.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (diseases.current_page - 1) * diseases.per_page + 1 }} to
                            {{ Math.min(diseases.current_page * diseases.per_page, diseases.total) }} of
                            {{ diseases.total }} diseases
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="diseases.current_page > 1"
                                :href="adminDiseasesRoute(buildQueryString(diseases.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="diseases.current_page < diseases.last_page"
                                :href="adminDiseasesRoute(buildQueryString(diseases.current_page + 1))"
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

