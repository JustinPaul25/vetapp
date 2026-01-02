<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { FileText, ArrowLeft, Download } from 'lucide-vue-next';
import { dashboard } from '@/routes';

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
    appointment_id: number;
    symptoms: string;
    notes: string;
    pet_weight: string;
    follow_up_date: string | null;
    created_at: string;
    updated_at: string;
}

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string;
    appointment_time: string;
    created_at: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: string;
}

interface Owner {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
    address?: string;
}

interface Props {
    prescription: Prescription;
    appointment: Appointment;
    patient: Patient;
    owner: Owner | null;
    diagnoses: Diagnosis[];
    medicines: PrescriptionMedicine[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Prescriptions', href: '/admin/prescriptions' },
    { title: 'View Prescription', href: '#' },
];

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeString: string | null) => {
    if (!timeString) return '—';
    return timeString;
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
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
    
    if (months < 0 || (months === 0 && today.getDate() < birth.getDate())) {
        years--;
        months += 12;
    }
    
    if (years === 0) {
        return `${months} month${months !== 1 ? 's' : ''}`;
    }
    return `${years} year${years !== 1 ? 's' : ''} ${months} month${months !== 1 ? 's' : ''}`;
};

const downloadPrescription = () => {
    window.open(`/admin/appointments/${props.prescription.appointment_id}/prescription`, '_blank');
};
</script>

<template>
    <Head title="View Prescription" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/prescriptions">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <FileText class="h-5 w-5" />
                                    View Prescription
                                </CardTitle>
                                <CardDescription>
                                    Prescription details and information
                                </CardDescription>
                            </div>
                        </div>
                        <Button @click="downloadPrescription">
                            <Download class="h-4 w-4 mr-2" />
                            Download PDF
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Prescription Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Prescription Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Prescription ID</Label>
                                    <div class="text-lg font-semibold">#{{ String(prescription.id).padStart(6, '0') }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Issued Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDateTime(prescription.created_at) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Symptoms</Label>
                                    <div class="text-lg font-semibold">{{ prescription.symptoms || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Pet Weight</Label>
                                    <div class="text-lg font-semibold">{{ prescription.pet_weight }} kg</div>
                                </div>
                                <div class="space-y-2" v-if="prescription.follow_up_date">
                                    <Label class="text-sm font-medium text-muted-foreground">Follow-up Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDate(prescription.follow_up_date) }}</div>
                                </div>
                                <div class="space-y-2 md:col-span-2" v-if="prescription.notes">
                                    <Label class="text-sm font-medium text-muted-foreground">Notes</Label>
                                    <div class="text-lg font-semibold">{{ prescription.notes }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Appointment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Type</Label>
                                    <div class="text-lg font-semibold">{{ appointment.appointment_type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Date</Label>
                                    <div class="text-lg font-semibold">{{ formatDate(appointment.appointment_date) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment Time</Label>
                                    <div class="text-lg font-semibold">{{ formatTime(appointment.appointment_time) }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Appointment ID</Label>
                                    <div class="text-lg font-semibold">
                                        <Link :href="`/admin/appointments/${appointment.id}`" class="text-blue-600 hover:underline">
                                            #{{ appointment.id }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Information -->
                        <div>
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
                                    <div class="text-lg font-semibold">{{ patient.pet_breed || '—' }}</div>
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
                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                    <div class="text-lg font-semibold">{{ patient.pet_allergies || '—' }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Patient ID</Label>
                                    <div class="text-lg font-semibold">
                                        <Link :href="`/admin/patients/${patient.id}`" class="text-blue-600 hover:underline">
                                            #{{ patient.id }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Owner Information -->
                        <div v-if="owner">
                            <h3 class="text-lg font-semibold mb-4">Owner Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Owner Name</Label>
                                    <div class="text-lg font-semibold">{{ owner.name }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                    <div class="text-lg font-semibold">{{ owner.email }}</div>
                                </div>
                                <div class="space-y-2" v-if="owner.mobile_number">
                                    <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                    <div class="text-lg font-semibold">{{ owner.mobile_number }}</div>
                                </div>
                                <div class="space-y-2" v-if="owner.address">
                                    <Label class="text-sm font-medium text-muted-foreground">Address</Label>
                                    <div class="text-lg font-semibold">{{ owner.address }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnoses -->
                        <div v-if="diagnoses.length > 0">
                            <h3 class="text-lg font-semibold mb-4">Diagnoses</h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="diagnosis in diagnoses"
                                    :key="diagnosis.id"
                                    class="px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full text-sm font-medium"
                                >
                                    {{ diagnosis.disease }}
                                </span>
                            </div>
                        </div>

                        <!-- Medicines -->
                        <div v-if="medicines.length > 0">
                            <h3 class="text-lg font-semibold mb-4">Medicines</h3>
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
                                            v-for="medicine in medicines"
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

                        <!-- Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(prescription.created_at) }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(prescription.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


