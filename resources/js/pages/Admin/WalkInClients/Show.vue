<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import LocationMapPicker from '@/components/LocationMapPicker.vue';
import { UserPlus, ArrowLeft, Edit, Heart } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { computed } from 'vue';

interface PetType {
    id: number;
    name: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    microchip_number: string | null;
    pet_type: PetType | null;
    created_at: string;
}

interface WalkInClient {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string;
    mobile_number: string | null;
    address: string | null;
    lat: number | null;
    lng: number | null;
    patients_count: number;
    patients: Patient[];
    appointments_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    walkInClient: WalkInClient;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Walk-In Clients', href: '/admin/walk_in_clients' },
    { title: 'View Walk-In Client', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
        return `${years} year${years > 1 ? 's' : ''}${months > 0 ? `, ${months} month${months > 1 ? 's' : ''}` : ''}`;
    }
    return `${months} month${months > 1 ? 's' : ''}`;
};

const location = computed(() => {
    if (props.walkInClient.lat && props.walkInClient.lng) {
        return { lat: props.walkInClient.lat, lng: props.walkInClient.lng };
    }
    return null;
});
</script>

<template>
    <Head title="View Walk-In Client" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/walk_in_clients">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <UserPlus class="h-5 w-5" />
                                    View Walk-In Client
                                </CardTitle>
                                <CardDescription>
                                    Walk-in client details and their pets
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/walk_in_clients/${walkInClient.id}/edit`">
                            <Button>
                                <Edit class="h-4 w-4 mr-2" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Client Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Client Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Full Name</Label>
                                    <div class="text-lg font-semibold">{{ walkInClient.name }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                    <div class="text-lg font-semibold">{{ walkInClient.email }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                    <div class="text-lg font-semibold">{{ walkInClient.mobile_number || '—' }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Address</Label>
                                    <div class="text-lg font-semibold">{{ walkInClient.address || '—' }}</div>
                                </div>
                            </div>
                            
                            <!-- Location Map -->
                            <div v-if="location" class="mt-6">
                                <Label class="text-sm font-medium text-muted-foreground mb-2 block">Location</Label>
                                <LocationMapPicker
                                    :model-value="location"
                                    height="300px"
                                />
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Statistics</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Card>
                                    <CardContent class="pt-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-muted-foreground">Total Patients</p>
                                                <p class="text-2xl font-bold">{{ walkInClient.patients_count }}</p>
                                            </div>
                                            <Heart class="h-8 w-8 text-pink-500" />
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="pt-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-muted-foreground">Total Appointments</p>
                                                <p class="text-2xl font-bold">{{ walkInClient.appointments_count }}</p>
                                            </div>
                                            <Badge variant="secondary" class="text-2xl">
                                                {{ walkInClient.appointments_count }}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <!-- Patients List -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">Patients</h3>
                                <Link :href="`/admin/patients/create?user_id=${walkInClient.id}`">
                                    <Button size="sm">
                                        Add Patient
                                    </Button>
                                </Link>
                            </div>
                            <div v-if="walkInClient.patients.length > 0" class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left p-3 font-semibold">Pet Name</th>
                                            <th class="text-left p-3 font-semibold">Type</th>
                                            <th class="text-left p-3 font-semibold">Breed</th>
                                            <th class="text-left p-3 font-semibold">Gender</th>
                                            <th class="text-left p-3 font-semibold">Age</th>
                                            <th class="text-right p-3 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="patient in walkInClient.patients"
                                            :key="patient.id"
                                            class="border-b hover:bg-muted/50"
                                        >
                                            <td class="p-3 font-medium">
                                                {{ patient.pet_name || '—' }}
                                            </td>
                                            <td class="p-3">{{ patient.pet_type?.name || '—' }}</td>
                                            <td class="p-3">{{ patient.pet_breed }}</td>
                                            <td class="p-3">{{ patient.pet_gender || '—' }}</td>
                                            <td class="p-3">{{ calculateAge(patient.pet_birth_date) }}</td>
                                            <td class="p-3">
                                                <div class="flex justify-end">
                                                    <Link :href="`/admin/patients/${patient.id}`">
                                                        <Button variant="outline" size="sm">
                                                            View
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center text-muted-foreground py-8">
                                No patients found. <Link :href="`/admin/patients/create?user_id=${walkInClient.id}`" class="text-primary underline">Add a patient</Link>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(walkInClient.created_at) }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(walkInClient.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>










