<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Heart, Plus, Edit, Trash2, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

interface PetType {
    id: number;
    name: string;
}

interface Pet {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    microchip_number: string | null;
    pet_allergies: string | null;
    pet_type: PetType | null;
    created_at: string;
}

interface Props {
    pets: {
        data: Pet[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    pet_types: PetType[];
    filters?: {
        search?: string;
        sort_by?: string;
        sort_direction?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Pets', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const deletePet = (petId: number, petName: string) => {
    const name = petName || 'this pet';
    if (confirm(`Are you sure you want to delete ${name}? This action cannot be undone.`)) {
        router.delete(`/pets/${petId}`);
    }
};

const clientPetsRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/pets${path}`;
    }
    return `/pets${path}`;
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
    router.get('/pets', {
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
    router.get('/pets', {
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
    
    router.get('/pets', {
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
    <Head title="My Pets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Heart class="h-5 w-5" />
                                My Pets
                            </CardTitle>
                            <CardDescription>
                                Manage your registered pets
                            </CardDescription>
                        </div>
                        <Link :href="clientPetsRoute('/create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Add Pet
                            </Button>
                        </Link>
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
                                placeholder="Search by name, breed, microchip..."
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
                                    <th class="text-left p-3 font-semibold">Microchip</th>
                                    <th class="text-right p-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pet in pets.data"
                                    :key="pet.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">
                                        {{ pet.pet_name || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ pet.pet_type?.name || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ pet.pet_breed }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ pet.pet_gender || '—' }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ calculateAge(pet.pet_birth_date) }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ pet.microchip_number || '—' }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="clientPetsRoute(`/${pet.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deletePet(pet.id, pet.pet_name || '')"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pets.data.length === 0">
                                    <td colspan="7" class="p-8 text-center text-muted-foreground">
                                        No pets found. <Link href="/pets/create" class="text-primary hover:underline">Add your first pet</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pets.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (pets.current_page - 1) * pets.per_page + 1 }} to
                            {{ Math.min(pets.current_page * pets.per_page, pets.total) }} of
                            {{ pets.total }} pets
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="pets.current_page > 1"
                                :href="clientPetsRoute(buildQueryString(pets.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="pets.current_page < pets.last_page"
                                :href="clientPetsRoute(buildQueryString(pets.current_page + 1))"
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















