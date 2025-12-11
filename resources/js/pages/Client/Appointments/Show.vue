<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Calendar, ArrowLeft, X } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { ref } from 'vue';

interface Diagnosis {
    id: number;
    disease: string;
}

interface PrescriptionMedicine {
    id: number;
    medicine: string;
    dosage: string;
    instructions: string;
    quantity: string;
}

interface Prescription {
    id: number;
    symptoms: string;
    notes: string;
    pet_weight: string;
    diagnoses: Diagnosis[];
    medicines: PrescriptionMedicine[];
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    microchip_number: string | null;
    pet_allergies: string | null;
    pet_type: string;
}

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string;
    appointment_time: string;
    symptoms: string;
    is_approved: boolean;
    is_completed: boolean;
    is_canceled: boolean;
    remarks: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    appointment: Appointment;
    patient: Patient | null;
    prescription: Prescription | null;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My Appointments', href: '/appointments' },
    { title: 'Appointment Details', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
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
        return `${years} year${years > 1 ? 's' : ''}`;
    }
    return `${months} month${months > 1 ? 's' : ''}`;
};

const cancelDialogOpen = ref(false);
const canceling = ref(false);

const cancelAppointment = () => {
    console.log('Cancel appointment called', {
        id: props.appointment.id,
        is_approved: props.appointment.is_approved,
        is_completed: props.appointment.is_completed
    });
    
    if (!props.appointment.id) {
        console.error('Appointment ID is missing');
        alert('Appointment ID is missing. Please refresh the page and try again.');
        return;
    }
    
    // Double check that appointment can be canceled
    if (props.appointment.is_canceled) {
        alert('This appointment is already canceled.');
        cancelDialogOpen.value = false;
        return;
    }
    
    if (props.appointment.is_approved || props.appointment.is_completed) {
        alert('Only pending appointments can be canceled.');
        cancelDialogOpen.value = false;
        return;
    }
    
    canceling.value = true;
    const url = `/appointments/${props.appointment.id}`;
    console.log('Sending DELETE request to:', url);
    
    router.delete(url, {
        preserveScroll: false,
        onSuccess: (page) => {
            console.log('Appointment canceled successfully');
            cancelDialogOpen.value = false;
            // Inertia will handle the redirect automatically
        },
        onError: (errors) => {
            canceling.value = false;
            console.error('Error canceling appointment:', errors);
            const errorMessage = errors?.error || errors?.message || 'Failed to cancel appointment. Please try again.';
            alert(errorMessage);
        },
        onFinish: () => {
            canceling.value = false;
        },
    });
};
</script>

<template>
    <Head title="Appointment Details" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/appointments">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    Appointment Details
                                </CardTitle>
                                <CardDescription>
                                    View detailed information about your appointment
                                </CardDescription>
                            </div>
                        </div>
                        <div v-if="!appointment.is_approved && !appointment.is_completed && !appointment.is_canceled">
                            <Dialog v-model:open="cancelDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="destructive" type="button">
                                        <X class="h-4 w-4 mr-2" />
                                        Cancel Appointment
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Cancel Appointment</DialogTitle>
                                        <DialogDescription>
                                            Are you sure you want to cancel this appointment? This action cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="cancelDialogOpen = false"
                                            :disabled="canceling"
                                        >
                                            No, Keep Appointment
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            @click="cancelAppointment"
                                            :disabled="canceling"
                                        >
                                            <span v-if="canceling">Canceling...</span>
                                            <span v-else>Yes, Cancel Appointment</span>
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Appointment Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Appointment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Type</Label>
                                    <div class="text-lg font-semibold">{{ appointment.appointment_type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Status</Label>
                                    <div class="text-lg font-semibold">
                                        <span
                                            :class="[
                                                'px-2 py-1 rounded-full text-xs font-medium',
                                                appointment.is_canceled
                                                    ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    : appointment.is_completed
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : appointment.is_approved
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            ]"
                                        >
                                            {{ appointment.is_canceled ? 'Canceled' : appointment.is_completed ? 'Completed' : appointment.is_approved ? 'Approved' : 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDate(appointment.appointment_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Time</Label>
                                    <div class="text-lg font-semibold">{{ formatTime(appointment.appointment_time) }}</div>
                                </div>
                                <div class="space-y-2 md:col-span-2" v-if="appointment.symptoms">
                                    <Label class="text-sm font-medium text-muted-foreground">Symptoms</Label>
                                    <div class="text-lg font-semibold">{{ appointment.symptoms || '—' }}</div>
                                </div>
                                <div class="space-y-2 md:col-span-2" v-if="appointment.remarks">
                                    <Label class="text-sm font-medium text-muted-foreground">Remarks</Label>
                                    <div class="text-lg font-semibold">{{ appointment.remarks }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Details -->
                        <div v-if="patient">
                            <h3 class="text-lg font-semibold mb-4">Patient Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Name</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_name || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Type</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_type }}</div>
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
                                    <div class="text-lg font-semibold">{{ formatDate(patient.pet_birth_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Age</Label>
                                    <div class="text-lg font-semibold">{{ calculateAge(patient.pet_birth_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Microchip Number</Label>
                                    <div class="text-lg font-semibold">{{ patient.microchip_number || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_allergies || '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Prescription Details -->
                        <div v-if="prescription">
                            <h3 class="text-lg font-semibold mb-4">Prescription Details</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Symptoms</Label>
                                        <div class="text-lg font-semibold">{{ prescription.symptoms || '—' }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium text-muted-foreground">Pet Weight</Label>
                                        <div class="text-lg font-semibold">{{ prescription.pet_weight }} kg</div>
                                    </div>
                                    <div class="space-y-2 md:col-span-2" v-if="prescription.notes">
                                        <Label class="text-sm font-medium text-muted-foreground">Notes</Label>
                                        <div class="text-lg font-semibold">{{ prescription.notes }}</div>
                                    </div>
                                </div>

                                <div v-if="prescription.diagnoses.length > 0">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2 block">Diagnoses</Label>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="diagnosis in prescription.diagnoses"
                                            :key="diagnosis.id"
                                            class="px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full text-sm font-medium"
                                        >
                                            {{ diagnosis.disease }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="prescription.medicines.length > 0">
                                    <Label class="text-sm font-medium text-muted-foreground mb-2 block">Medicines</Label>
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse">
                                            <thead>
                                                <tr class="border-b">
                                                    <th class="text-left p-3 font-semibold">Medicine</th>
                                                    <th class="text-left p-3 font-semibold">Dosage</th>
                                                    <th class="text-left p-3 font-semibold">Quantity</th>
                                                    <th class="text-left p-3 font-semibold">Instructions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="medicine in prescription.medicines"
                                                    :key="medicine.id"
                                                    class="border-b hover:bg-muted/50"
                                                >
                                                    <td class="p-3">{{ medicine.medicine }}</td>
                                                    <td class="p-3">{{ medicine.dosage }}</td>
                                                    <td class="p-3">{{ medicine.quantity }}</td>
                                                    <td class="p-3">{{ medicine.instructions }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(appointment.created_at) }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(appointment.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
