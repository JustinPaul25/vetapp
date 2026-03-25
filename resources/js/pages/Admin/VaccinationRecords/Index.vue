<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Syringe, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard } from '@/routes';

interface Row {
    id: number;
    vaccine_name: string | null;
    administered_at: string | null;
    next_due_at: string | null;
    user_id: number | null;
    owner_label: string | null;
    patient_id: number | null;
    pet_label: string | null;
    source: string | null;
    created_at: string;
}

interface Props {
    records: {
        data: Row[];
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
    { title: 'Vaccination records', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'administered_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const base = '/admin/vaccination_records';

const buildQuery = (page?: number) => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (sortBy.value) params.set('sort_by', sortBy.value);
    if (sortDirection.value) params.set('sort_direction', sortDirection.value);
    if (page) params.set('page', String(page));
    const q = params.toString();

    return q ? `${base}?${q}` : base;
};

const handleSearch = () => {
    router.get(base, {
        search: searchQuery.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, { preserveState: true, replace: true });
};

const clearSearch = () => {
    searchQuery.value = '';
    router.get(base, {
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, { preserveState: true, replace: true });
};

const handleSort = (column: string) => {
    if (sortBy.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDirection.value = 'asc';
    }
    router.get(base, {
        search: searchQuery.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, { preserveState: true, replace: true });
};

const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return ArrowUpDown;

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const destroyRecord = (id: number, label: string) => {
    if (confirm(`Delete vaccination record: ${label}?`)) {
        router.delete(`${base}/${id}`);
    }
};

const formatDate = (d: string | null | undefined) => {
    if (!d) return '—';
    try {
        return new Date(d + 'T00:00:00').toLocaleDateString();
    } catch {
        return d;
    }
};
</script>

<template>
    <Head title="Vaccination records" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Syringe class="h-5 w-5" />
                                Vaccination records registry
                            </CardTitle>
                            <CardDescription>
                                Stand-alone vaccination history with optional links to owners and pets.
                            </CardDescription>
                        </div>
                        <Link :href="`${base}/create`">
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Add record
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="mb-4 flex flex-wrap gap-2">
                        <div class="relative max-w-sm flex-1 min-w-[200px]">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search vaccine, lot, notes..."
                                class="pl-10"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                        <Button variant="outline" @click="handleSearch">Search</Button>
                        <Button v-if="searchQuery" variant="ghost" @click="clearSearch">Clear</Button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b">
                                    <th class="p-3 text-left font-semibold">
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                            @click="handleSort('vaccine_name')"
                                        >
                                            Vaccine
                                            <component :is="getSortIcon('vaccine_name')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="p-3 text-left font-semibold">
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                            @click="handleSort('administered_at')"
                                        >
                                            Given
                                            <component :is="getSortIcon('administered_at')" class="h-4 w-4" />
                                        </button>
                                    </th>
                                    <th class="p-3 text-left font-semibold">Next due</th>
                                    <th class="p-3 text-left font-semibold">Owner</th>
                                    <th class="p-3 text-left font-semibold">Pet</th>
                                    <th class="p-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="rec in records.data" :key="rec.id" class="border-b hover:bg-muted/50">
                                    <td class="p-3 font-medium">{{ rec.vaccine_name || '—' }}</td>
                                    <td class="p-3 text-muted-foreground">{{ formatDate(rec.administered_at) }}</td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ rec.next_due_at ? formatDate(rec.next_due_at) : '—' }}
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ rec.owner_label || '—' }}
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ rec.pet_label || '—' }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-1">
                                            <Link :href="`${base}/${rec.id}`">
                                                <Button variant="ghost" size="icon" title="View">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="`${base}/${rec.id}/edit`">
                                                <Button variant="ghost" size="icon" title="Edit">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title="Delete"
                                                @click="destroyRecord(rec.id, rec.vaccine_name || 'Record')"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="records.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No vaccination records yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="records.last_page > 1"
                        class="mt-4 flex items-center justify-between text-sm text-muted-foreground"
                    >
                        <span>
                            Showing
                            {{ (records.current_page - 1) * records.per_page + 1 }}
                            –
                            {{ Math.min(records.current_page * records.per_page, records.total) }}
                            of {{ records.total }}
                        </span>
                        <div class="flex gap-2">
                            <Button v-if="records.current_page <= 1" variant="outline" size="sm" disabled type="button">
                                Previous
                            </Button>
                            <Link v-else :href="buildQuery(records.current_page - 1)">
                                <Button variant="outline" size="sm" type="button">Previous</Button>
                            </Link>
                            <Button
                                v-if="records.current_page >= records.last_page"
                                variant="outline"
                                size="sm"
                                disabled
                                type="button"
                            >
                                Next
                            </Button>
                            <Link v-else :href="buildQuery(records.current_page + 1)">
                                <Button variant="outline" size="sm" type="button">Next</Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
