<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Heart, ArrowLeft, Edit, Plus, TrendingUp } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';

interface PetType {
    id: number;
    name: string;
}

interface Owner {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
    address?: string;
}

interface Appointment {
    id: number;
    appointment_type: string | null;
    appointment_date: string | null;
    appointment_time: string | null;
    created_at: string;
}

interface Prescription {
    id: number;
    appointment_id: number;
    created_at: string;
}

interface WeightHistoryEntry {
    id: number;
    weight: number;
    recorded_at: string;
    notes: string | null;
    prescription_id: number | null;
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
    appointments: Appointment[];
    prescriptions: Prescription[];
    weight_history: WeightHistoryEntry[];
    created_at: string;
    updated_at: string;
}

interface Props {
    patient: Patient;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Patients', href: '/admin/patients' },
    { title: 'View Patient', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
    // Check if time already contains AM/PM (already formatted)
    if (timeString.includes('AM') || timeString.includes('PM')) {
        return timeString;
    }
    // Otherwise, format from 24-hour format
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
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

const weightDialogOpen = ref(false);
const weightForm = useForm({
    weight: '',
    recorded_at: new Date().toISOString().split('T')[0],
    notes: '',
});

const addWeightEntry = () => {
    weightForm.post(`/admin/patients/${props.patient.id}/weight-history`, {
        onSuccess: () => {
            weightDialogOpen.value = false;
            weightForm.reset();
            // Reload the page to show updated weight history
            window.location.reload();
        },
    });
};

const getCurrentWeight = () => {
    if (props.patient.weight_history.length === 0) return null;
    return props.patient.weight_history[0].weight;
};

const getWeightChange = () => {
    if (props.patient.weight_history.length < 2) return null;
    const current = props.patient.weight_history[0].weight;
    const previous = props.patient.weight_history[1].weight;
    const change = current - previous;
    return {
        value: change,
        percentage: ((change / previous) * 100).toFixed(1),
    };
};
</script>

<template>
    <Head title="View Patient" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/patients">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Heart class="h-5 w-5" />
                                    View Patient
                                </CardTitle>
                                <CardDescription>
                                    Patient details and history
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/patients/${patient.id}/edit`">
                            <Button>
                                <Edit class="h-4 w-4 mr-2" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Patient Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Patient Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Name</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_name || '—' }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Type</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_type?.name || '—' }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Breed</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_breed }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Gender</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_gender || '—' }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Birth Date</Label>
                                    <div class="text-lg font-semibold">
                                        {{ formatDate(patient.pet_birth_date) }}
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Age</Label>
                                    <div class="text-lg font-semibold">
                                        {{ calculateAge(patient.pet_birth_date) }}
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_allergies || '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Owner Information -->
                        <div v-if="patient.owner">
                            <h3 class="text-lg font-semibold mb-4">Owner Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Owner Name</Label>
                                    <div class="text-lg font-semibold">{{ patient.owner.name }}</div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                    <div class="text-lg font-semibold">{{ patient.owner.email }}</div>
                                </div>

                                <div class="space-y-2" v-if="patient.owner.mobile_number">
                                    <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                    <div class="text-lg font-semibold">{{ patient.owner.mobile_number }}</div>
                                </div>

                                <div class="space-y-2" v-if="patient.owner.address">
                                    <Label class="text-sm font-medium text-muted-foreground">Address</Label>
                                    <div class="text-lg font-semibold">{{ patient.owner.address }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment History -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">Appointment History</h3>
                            </div>
                            <div v-if="patient.appointments.length > 0" class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left p-3 font-semibold">Type</th>
                                            <th class="text-left p-3 font-semibold">Date</th>
                                            <th class="text-left p-3 font-semibold">Time</th>
                                            <th class="text-right p-3 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="appointment in patient.appointments"
                                            :key="appointment.id"
                                            class="border-b hover:bg-muted/50"
                                        >
                                            <td class="p-3">{{ appointment.appointment_type || '—' }}</td>
                                            <td class="p-3">{{ formatDate(appointment.appointment_date) }}</td>
                                            <td class="p-3">{{ formatTime(appointment.appointment_time) }}</td>
                                            <td class="p-3">
                                                <div class="flex justify-end">
                                                    <Link :href="`/admin/appointments/${appointment.id}`">
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
                                No appointments found
                            </div>
                        </div>

                        <!-- Weight History -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <TrendingUp class="h-5 w-5" />
                                    <h3 class="text-lg font-semibold">Weight History</h3>
                                    <div v-if="getCurrentWeight()" class="ml-4 text-sm text-muted-foreground">
                                        Current: <span class="font-semibold">{{ getCurrentWeight() }} kg</span>
                                        <span
                                            v-if="getWeightChange()"
                                            :class="[
                                                'ml-2',
                                                getWeightChange()!.value > 0
                                                    ? 'text-green-600'
                                                    : getWeightChange()!.value < 0
                                                      ? 'text-red-600'
                                                      : 'text-muted-foreground',
                                            ]"
                                        >
                                            ({{ getWeightChange()!.value > 0 ? '+' : '' }}{{ getWeightChange()!.value.toFixed(2) }} kg,
                                            {{ getWeightChange()!.value > 0 ? '+' : '' }}{{ getWeightChange()!.percentage }}%)
                                        </span>
                                    </div>
                                </div>
                                <Dialog v-model:open="weightDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button size="sm">
                                            <Plus class="h-4 w-4 mr-2" />
                                            Add Weight Entry
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Add Weight Entry</DialogTitle>
                                            <DialogDescription>
                                                Record a new weight measurement for {{ patient.pet_name || 'this pet' }}.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4 py-4">
                                            <div class="space-y-2">
                                                <Label for="weight">Weight (kg) *</Label>
                                                <Input
                                                    id="weight"
                                                    v-model="weightForm.weight"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="e.g., 25.5"
                                                />
                                                <InputError :message="weightForm.errors.weight" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="recorded_at">Date Recorded *</Label>
                                                <Input
                                                    id="recorded_at"
                                                    v-model="weightForm.recorded_at"
                                                    type="date"
                                                />
                                                <InputError :message="weightForm.errors.recorded_at" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="notes">Notes (Optional)</Label>
                                                <textarea
                                                    id="notes"
                                                    v-model="weightForm.notes"
                                                    placeholder="e.g., Routine checkup, After treatment..."
                                                    rows="3"
                                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                />
                                                <InputError :message="weightForm.errors.notes" />
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button variant="outline" @click="weightDialogOpen = false">Cancel</Button>
                                            <Button @click="addWeightEntry" :disabled="weightForm.processing">
                                                {{ weightForm.processing ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                            <div v-if="patient.weight_history.length > 0" class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left p-3 font-semibold">Date</th>
                                            <th class="text-left p-3 font-semibold">Weight (kg)</th>
                                            <th class="text-left p-3 font-semibold">Change</th>
                                            <th class="text-left p-3 font-semibold">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(entry, index) in patient.weight_history"
                                            :key="entry.id"
                                            class="border-b hover:bg-muted/50"
                                        >
                                            <td class="p-3">{{ formatDateTime(entry.recorded_at) }}</td>
                                            <td class="p-3 font-semibold">{{ entry.weight.toFixed(2) }} kg</td>
                                            <td class="p-3">
                                                <span
                                                    v-if="index < patient.weight_history.length - 1"
                                                    :class="[
                                                        entry.weight - patient.weight_history[index + 1].weight > 0
                                                            ? 'text-green-600'
                                                            : entry.weight - patient.weight_history[index + 1].weight < 0
                                                              ? 'text-red-600'
                                                              : 'text-muted-foreground',
                                                    ]"
                                                >
                                                    {{
                                                        entry.weight - patient.weight_history[index + 1].weight > 0
                                                            ? '+'
                                                            : ''
                                                    }}{{ (
                                                        entry.weight - patient.weight_history[index + 1].weight
                                                    ).toFixed(2) }}
                                                    kg
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="p-3 text-sm text-muted-foreground">
                                                {{ entry.notes || '—' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center text-muted-foreground py-8">
                                No weight history recorded yet
                            </div>
                        </div>

                        <!-- Prescription History -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Prescription History</h3>
                            <div v-if="patient.prescriptions.length > 0" class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left p-3 font-semibold">Date</th>
                                            <th class="text-right p-3 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="prescription in patient.prescriptions"
                                            :key="prescription.id"
                                            class="border-b hover:bg-muted/50"
                                        >
                                            <td class="p-3">{{ formatDateTime(prescription.created_at) }}</td>
                                            <td class="p-3">
                                                <div class="flex justify-end">
                                                    <Link :href="`/admin/prescriptions/${prescription.id}`">
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
                                No prescriptions found
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(patient.created_at) }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(patient.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
