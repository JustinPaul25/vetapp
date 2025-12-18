<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/searchable-select';
import InputError from '@/components/InputError.vue';
import { Calendar, ArrowLeft } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { computed } from 'vue';

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string;
    pet_type: string;
    owner_name: string;
}

interface AppointmentType {
    id: number;
    name: string;
}

interface Props {
    patients: Patient[];
    appointment_types: AppointmentType[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Appointments', href: '/admin/appointments' },
    { title: 'Create Appointment', href: '#' },
];

const form = router.form({
    patient_id: '',
    appointment_type: '',
    appointment_date: '',
    appointment_time: '',
});

const submit = () => {
    form.post('/admin/appointments');
};

// Transform patients for SearchableSelect
const patientOptions = computed(() => {
    return props.patients.map(patient => ({
        value: patient.id.toString(),
        label: `${patient.pet_name || 'Unnamed'} - ${patient.pet_breed} (${patient.pet_type}) - Owner: ${patient.owner_name}`,
    }));
});

// Transform appointment types for SearchableSelect
const appointmentTypeOptions = computed(() => {
    return props.appointment_types.map(type => ({
        value: type.id.toString(),
        label: type.name,
    }));
});

// Get today's date in YYYY-MM-DD format for min attribute
const today = computed(() => {
    const date = new Date();
    return date.toISOString().split('T')[0];
});
</script>

<template>
    <Head title="Create Appointment" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/appointments">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Create New Appointment
                            </CardTitle>
                            <CardDescription>
                                Schedule a new appointment for a patient
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <Label for="patient_id">Patient <span class="text-destructive">*</span></Label>
                                <SearchableSelect
                                    id="patient_id"
                                    v-model="form.patient_id"
                                    :options="patientOptions"
                                    placeholder="Select Patient"
                                    search-placeholder="Search patients..."
                                    :required="true"
                                />
                                <InputError :message="form.errors.patient_id" />
                            </div>

                            <div class="space-y-2">
                                <Label for="appointment_type">Appointment Type <span class="text-destructive">*</span></Label>
                                <SearchableSelect
                                    id="appointment_type"
                                    v-model="form.appointment_type"
                                    :options="appointmentTypeOptions"
                                    placeholder="Select Appointment Type"
                                    search-placeholder="Search appointment types..."
                                    :required="true"
                                />
                                <InputError :message="form.errors.appointment_type" />
                            </div>

                            <div class="space-y-2">
                                <Label for="appointment_date">Appointment Date <span class="text-destructive">*</span></Label>
                                <Input
                                    id="appointment_date"
                                    v-model="form.appointment_date"
                                    type="date"
                                    :min="today"
                                    required
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.appointment_date" />
                            </div>

                            <div class="space-y-2">
                                <Label for="appointment_time">Appointment Time <span class="text-destructive">*</span></Label>
                                <Input
                                    id="appointment_time"
                                    v-model="form.appointment_time"
                                    type="time"
                                    required
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.appointment_time" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/appointments">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Create Appointment
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>












