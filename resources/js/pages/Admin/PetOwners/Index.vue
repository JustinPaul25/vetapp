<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { UserCheck, Plus, Edit, Trash2, Eye, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_type: string | null;
}

interface PetOwner {
    id: number;
    name: string;
    email: string;
    mobile_number: string | null;
    address: string | null;
    patients_count: number;
    patients: Patient[];
    created_at: string;
}

interface Props {
    petOwners: {
        data: PetOwner[];
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
    { title: 'Pet Owners', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const sortBy = ref(props.filters?.sort_by || 'created_at');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

const deletePetOwner = (petOwnerId: number, petOwnerName: string) => {
    if (confirm(`Are you sure you want to delete ${petOwnerName}? This will also delete all associated patients.`)) {
        router.delete(`/admin/pet_owners/${petOwnerId}`);
    }
};

const adminPetOwnersRoute = (path: string) => {
    if (path.startsWith('?')) {
        return `/admin/pet_owners${path}`;
    }
    return `/admin/pet_owners${path}`;
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
    router.get('/admin/pet_owners', {
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
    router.get('/admin/pet_owners', {
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
    
    router.get('/admin/pet_owners', {
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
    <Head title="Pet Owners Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <UserCheck class="h-5 w-5" />
                                Pet Owners Management
                            </CardTitle>
                            <CardDescription>
                                Manage all pet owners and their information
                            </CardDescription>
                        </div>
                        <Link :href="adminPetOwnersRoute('/create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Add Pet Owner
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
                                    <th class="text-left p-3 font-semibold">Patients</th>
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
                                    v-for="petOwner in petOwners.data"
                                    :key="petOwner.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="p-3 font-medium">
                                        {{ petOwner.name }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ petOwner.email }}
                                    </td>
                                    <td class="p-3 text-sm">
                                        {{ petOwner.mobile_number || '—' }}
                                    </td>
                                    <td class="p-3">
                                        <Badge variant="secondary">
                                            {{ petOwner.patients_count }} pet{{ petOwner.patients_count !== 1 ? 's' : '' }}
                                        </Badge>
                                        <div v-if="petOwner.patients.length > 0" class="text-xs text-muted-foreground mt-1">
                                            <span v-for="(patient, idx) in petOwner.patients" :key="patient.id">
                                                {{ patient.pet_name || 'Unnamed' }} ({{ patient.pet_type }})
                                                <span v-if="idx < petOwner.patients.length - 1">, </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm text-muted-foreground">
                                        {{ new Date(petOwner.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="adminPetOwnersRoute(`/${petOwner.id}`)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link :href="adminPetOwnersRoute(`/${petOwner.id}/edit`)">
                                                <Button variant="outline" size="sm">
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="deletePetOwner(petOwner.id, petOwner.name)"
                                            >
                                                <Trash2 class="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="petOwners.data.length === 0">
                                    <td colspan="6" class="p-8 text-center text-muted-foreground">
                                        No pet owners found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="petOwners.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (petOwners.current_page - 1) * petOwners.per_page + 1 }} to
                            {{ Math.min(petOwners.current_page * petOwners.per_page, petOwners.total) }} of
                            {{ petOwners.total }} pet owners
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-if="petOwners.current_page > 1"
                                :href="adminPetOwnersRoute(buildQueryString(petOwners.current_page - 1))"
                            >
                                <Button variant="outline" size="sm">Previous</Button>
                            </Link>
                            <Link
                                v-if="petOwners.current_page < petOwners.last_page"
                                :href="adminPetOwnersRoute(buildQueryString(petOwners.current_page + 1))"
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
