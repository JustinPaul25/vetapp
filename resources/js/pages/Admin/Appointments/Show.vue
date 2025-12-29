<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Calendar, ArrowLeft, CheckCircle, FileText, Download, CalendarClock } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { ref, computed } from 'vue';

interface Owner {
    id: number;
    name: string;
    email: string;
    mobile_number?: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: string;
    owner: Owner | null;
}

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

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string;
    appointment_time: string;
    symptoms: string;
    is_approved: boolean;
    is_completed: boolean;
    remarks: string | null;
    summary: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    appointment: Appointment;
    patient: Patient | null;
    patients?: Patient[];
    prescription: Prescription | null;
    medicines: Array<{
        id: number;
        name: string;
        dosage: string;
        stock: number;
    }>;
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => auth.value?.user?.roles?.includes('admin') ?? false);

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Appointments', href: '/admin/appointments' },
    { title: 'View Appointment', href: '#' },
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

const approveDialogOpen = ref(false);
const rescheduleDialogOpen = ref(false);

const approveForm = useForm({
    appointment_date: props.appointment.appointment_date,
    appointment_time: props.appointment.appointment_time,
    pet_gender: props.patient?.pet_gender || '',
    pet_allergies: props.patient?.pet_allergies || '',
});

const rescheduleForm = useForm({
    appointment_date: props.appointment.appointment_date,
    appointment_time: props.appointment.appointment_time,
});

const approveAppointment = () => {
    approveForm.patch(`/admin/appointments/${props.appointment.id}/approve`, {
        onSuccess: () => {
            approveDialogOpen.value = false;
        },
    });
};

const rescheduleAppointment = () => {
    rescheduleForm.patch(`/admin/appointments/${props.appointment.id}/reschedule`, {
        onSuccess: () => {
            rescheduleDialogOpen.value = false;
        },
    });
};

const downloadPrescription = () => {
    window.open(`/admin/appointments/${props.appointment.id}/prescription`, '_blank');
};
</script>

<template>
    <Head title="View Appointment" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-6xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/appointments">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Calendar class="h-5 w-5" />
                                    View Appointment
                                </CardTitle>
                                <CardDescription>
                                    Appointment details and actions
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Dialog v-if="!appointment.is_approved" v-model:open="approveDialogOpen">
                                <DialogTrigger as-child>
                                    <Button>
                                        <CheckCircle class="h-4 w-4 mr-2" />
                                        Approve Appointment
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Approve/Reschedule Appointment</DialogTitle>
                                        <DialogDescription>
                                            Update appointment details and approve the appointment
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="approveAppointment" class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <Label for="appointment_date">Appointment Date <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="appointment_date"
                                                    v-model="approveForm.appointment_date"
                                                    type="date"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                    required
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="appointment_time">Appointment Time <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="appointment_time"
                                                    v-model="approveForm.appointment_time"
                                                    type="time"
                                                    required
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="pet_gender">Pet Gender</Label>
                                                <select
                                                    id="pet_gender"
                                                    v-model="approveForm.pet_gender"
                                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                >
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                </select>
                                            </div>
                                            <div class="space-y-2 md:col-span-2">
                                                <Label for="pet_allergies">Pet Allergies</Label>
                                                <Input
                                                    id="pet_allergies"
                                                    v-model="approveForm.pet_allergies"
                                                    type="text"
                                                    placeholder="e.g., Peanuts, Pollen"
                                                />
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" @click="approveDialogOpen = false">
                                                Cancel
                                            </Button>
                                            <Button type="submit" :disabled="approveForm.processing">
                                                Approve Appointment
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            <Dialog v-if="appointment.is_approved && !appointment.is_completed" v-model:open="rescheduleDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline">
                                        <CalendarClock class="h-4 w-4 mr-2" />
                                        Reschedule
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Reschedule Appointment</DialogTitle>
                                        <DialogDescription>
                                            Update the appointment date and time
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="rescheduleAppointment" class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <Label for="reschedule_appointment_date">Appointment Date <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="reschedule_appointment_date"
                                                    v-model="rescheduleForm.appointment_date"
                                                    type="date"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                    required
                                                />
                                                <div v-if="rescheduleForm.errors.appointment_date" class="text-sm text-destructive">
                                                    {{ rescheduleForm.errors.appointment_date }}
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="reschedule_appointment_time">Appointment Time <span class="text-destructive">*</span></Label>
                                                <Input
                                                    id="reschedule_appointment_time"
                                                    v-model="rescheduleForm.appointment_time"
                                                    type="time"
                                                    required
                                                />
                                                <div v-if="rescheduleForm.errors.appointment_time" class="text-sm text-destructive">
                                                    {{ rescheduleForm.errors.appointment_time }}
                                                </div>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" @click="rescheduleDialogOpen = false">
                                                Cancel
                                            </Button>
                                            <Button type="submit" :disabled="rescheduleForm.processing">
                                                Reschedule Appointment
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            <Link
                                v-if="isAdmin && appointment.is_approved && !appointment.is_completed"
                                :href="`/admin/appointments/${appointment.id}/prescription/create`"
                            >
                                <Button>
                                    <FileText class="h-4 w-4 mr-2" />
                                    Create Prescription
                                </Button>
                            </Link>
                            <Button
                                v-if="appointment.is_completed && prescription"
                                @click="downloadPrescription"
                            >
                                <Download class="h-4 w-4 mr-2" />
                                Download Prescription
                            </Button>
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
                                                appointment.is_completed
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : appointment.is_approved
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            ]"
                                        >
                                            {{ appointment.is_completed ? 'Completed' : appointment.is_approved ? 'Approved' : 'Pending' }}
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
                        <div v-if="patients && patients.length > 0">
                            <h3 class="text-lg font-semibold mb-4">Patient Information</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <!-- Multiple pets displayed in separate cards -->
                                <Card
                                    v-for="(pet, index) in patients"
                                    :key="pet.id"
                                    class="border-2"
                                >
                                    <CardHeader class="pb-3">
                                        <CardTitle class="text-base flex items-center gap-2">
                                            <span class="text-muted-foreground">Pet {{ index + 1 }}</span>
                                            <span class="text-foreground">{{ pet.pet_name || 'Unnamed Pet' }}</span>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Pet Name</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_name || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Pet Type</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_type }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Breed</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_breed || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Gender</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_gender || '—' }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Birth Date</Label>
                                                    <div class="text-lg font-semibold">{{ formatDate(pet.pet_birth_date) }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Age</Label>
                                                    <div class="text-lg font-semibold">{{ calculateAge(pet.pet_birth_date) }}</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium text-muted-foreground">Allergies</Label>
                                                    <div class="text-lg font-semibold">{{ pet.pet_allergies || '—' }}</div>
                                                </div>
                                            </div>
                                            <!-- Owner Information for each pet -->
                                            <div v-if="pet.owner" class="pt-4 border-t">
                                                <h4 class="text-sm font-semibold mb-3 text-muted-foreground">Owner Information</h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="space-y-2">
                                                        <Label class="text-sm font-medium text-muted-foreground">Owner Name</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.name }}</div>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.email }}</div>
                                                    </div>
                                                    <div class="space-y-2" v-if="pet.owner.mobile_number">
                                                        <Label class="text-sm font-medium text-muted-foreground">Mobile Number</Label>
                                                        <div class="text-lg font-semibold">{{ pet.owner.mobile_number }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                        <!-- Fallback for backward compatibility with single patient -->
                        <div v-else-if="patient">
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
                            </div>
                            
                            <!-- Owner Information -->
                            <div v-if="patient.owner" class="mt-6">
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
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Summary -->
                        <div v-if="appointment.is_completed && appointment.summary">
                            <h3 class="text-lg font-semibold mb-4">Appointment Summary</h3>
                            <Card>
                                <CardContent class="p-6">
                                    <pre class="whitespace-pre-wrap text-sm font-mono bg-muted p-4 rounded-md">{{ appointment.summary }}</pre>
                                </CardContent>
                            </Card>
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




