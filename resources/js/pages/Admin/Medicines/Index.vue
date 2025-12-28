<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Pill, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import ReportGenerator from '@/components/ReportGenerator.vue';

interface Medicine {
    id: number;
    name: string;
    stock: number;
    dosage: string;
    route: string;
    created_at: string;
}

interface Props {
    medicines: {
        data: Medicine[];
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
    { title: 'Medicines', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const deleteMedicine = (medicineId: number, medicineName: string) => {
    if (confirm(`Are you sure you want to delete the medicine "${medicineName}"?`)) {
        router.delete(`/admin/medicines/${medicineId}`);
    }
};

const adminMedicinesRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/medicines${path}`;
    }
    return `/admin/medicines${path}`;
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
    router.get('/admin/medicines', {
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
    router.get('/admin/medicines', {
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
    
    router.get('/admin/medicines', {
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
    <Head title="Medicines Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Pill class="h-5 w-5" />
                                Medicines Management
                            </CardTitle>
                            <CardDescription>
                                Manage all medicines in the system
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <ReportGenerator
                                export-url="/admin/medicines/export"
                                report-title="Medicines"
                                :filters="{ search: searchQuery, sort_by: sortBy, sort_direction: sortDirection }"
                            />
                            <Link :href="adminMedicinesRoute('/create')">
                                <Button>
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Medicine
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
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('stock')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Stock
                                            <component :is="getSortIcon('stock')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('dosage')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Dosage
                                            <component :is="getSortIcon('dosage')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="text-left p-3 font-semibold">
                                        <button
                                            @click="handleSort('route')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Route
                                            <component :is="getSortIcon('route')" class="h-4 w-4" />
                                        </button>
                                    </th>
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
                                    v-for="medicine in medicines.data"
                                    :key="medicine.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">{{ medicine.name }}</td>
                                    <td class="p-3">
                                        <span :class="medicine.stock < 10 ? 'text-destructive font-semibold' : ''">
                                            {{ medicine.stock }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">{{ medicine.dosage }}</td>
                                    <td class="p-3 text-sm text-muted-foreground">{{ medicine.route }}</td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ new Date(medicine.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="adminMedicinesRoute(`/${medicine.id}`)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="adminMedicinesRoute(`/${medicine.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deleteMedicine(medicine.id, medicine.name)"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="medicines.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No medicines found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="medicines.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (medicines.current_page - 1) * medicines.per_page + 1 }} to
                            {{ Math.min(medicines.current_page * medicines.per_page, medicines.total) }} of
                            {{ medicines.total }} medicines
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="medicines.current_page > 1"
                                :href="adminMedicinesRoute(buildQueryString(medicines.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="medicines.current_page < medicines.last_page"
                                :href="adminMedicinesRoute(buildQueryString(medicines.current_page + 1))"
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
