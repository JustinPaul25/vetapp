<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { PawPrint, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { dashboard } from '@/routes';

interface PetType {
    id: number;
    name: string;
}

interface PetBreed {
    id: number;
    name: string;
    pet_type_id: number;
    pet_type_name: string;
    created_at: string;
}

interface Props {
    pet_breeds: {
        data: PetBreed[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    pet_types: PetType[];
    filters?: {
        search?: string;
        pet_type_id?: string;
        sort_by?: string;
        sort_direction?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pet Breeds', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const selectedPetType = ref(props.filters?.pet_type_id || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const petTypeOptions = computed(() => [
    { value: '', label: 'All Pet Types' },
    ...props.pet_types.map(pt => ({ value: String(pt.id), label: pt.name }))
]);

const deletePetBreed = (petBreedId: number, petBreedName: string) => {
    if (confirm(`Are you sure you want to delete the pet breed "${petBreedName}"?`)) {
        router.delete(`/admin/pet_breeds/${petBreedId}`);
    }
};

const adminPetBreedsRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/pet_breeds${path}`;
    }
    return `/admin/pet_breeds${path}`;
};

const buildQueryString = (page?: number) => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (selectedPetType.value) params.set('pet_type_id', selectedPetType.value);
    if (sortBy.value) params.set('sort_by', sortBy.value);
    if (sortDirection.value) params.set('sort_direction', sortDirection.value);
    if (page) params.set('page', page.toString());
    return params.toString() ? `?${params.toString()}` : '';
};

const handleSearch = () => {
    router.get('/admin/pet_breeds', {
        search: searchQuery.value,
        pet_type_id: selectedPetType.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handlePetTypeFilter = (value: string | number | null) => {
    selectedPetType.value = value?.toString() || '';
    router.get('/admin/pet_breeds', {
        search: searchQuery.value,
        pet_type_id: selectedPetType.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedPetType.value = '';
    router.get('/admin/pet_breeds', {
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
    
    router.get('/admin/pet_breeds', {
        search: searchQuery.value,
        pet_type_id: selectedPetType.value,
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
    <Head title="Pet Breeds Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <PawPrint class="h-5 w-5" />
                                Pet Breeds Management
                            </CardTitle>
                            <CardDescription>
                                Manage all pet breeds in the system
                            </CardDescription>
                        </div>
                        <Link :href="adminPetBreedsRoute('/create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Add Pet Breed
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Search and Filters -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <div class="relative flex-1 min-w-[200px] max-w-sm">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by name..."
                                class="pl-10"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                        <SearchableSelect
                            :model-value="selectedPetType"
                            :options="petTypeOptions"
                            placeholder="Filter by Pet Type"
                            class="w-[200px]"
                            @update:model-value="handlePetTypeFilter"
                        />
                        <Button variant="outline" @click="handleSearch">
                            Search
                        </Button>
                        <Button v-if="searchQuery || selectedPetType" variant="ghost" @click="clearFilters">
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
                                            @click="handleSort('pet_type_id')"
                                            class="flex items-center gap-1 hover:text-primary transition-colors"
                                        >
                                            Pet Type
                                            <component :is="getSortIcon('pet_type_id')" class="h-4 w-4" />
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
                                    v-for="pet_breed in pet_breeds.data"
                                    :key="pet_breed.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">{{ pet_breed.name }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                            {{ pet_breed.pet_type_name }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ new Date(pet_breed.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="adminPetBreedsRoute(`/${pet_breed.id}`)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="adminPetBreedsRoute(`/${pet_breed.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deletePetBreed(pet_breed.id, pet_breed.name)"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pet_breeds.data.length === 0">
                                    <td colspan="4" class="p-8 text-center text-muted-foreground">
                                        No pet breeds found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pet_breeds.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (pet_breeds.current_page - 1) * pet_breeds.per_page + 1 }} to
                            {{ Math.min(pet_breeds.current_page * pet_breeds.per_page, pet_breeds.total) }} of
                            {{ pet_breeds.total }} pet breeds
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="pet_breeds.current_page > 1"
                                :href="adminPetBreedsRoute(buildQueryString(pet_breeds.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="pet_breeds.current_page < pet_breeds.last_page"
                                :href="adminPetBreedsRoute(buildQueryString(pet_breeds.current_page + 1))"
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

