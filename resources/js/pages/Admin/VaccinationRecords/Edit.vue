<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Syringe, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { computed, watch } from 'vue';

interface UserOpt {
    id: number;
    name: string;
    email: string;
}

interface PatientOpt {
    id: number;
    pet_name: string | null;
    pet_breed: string | null;
    user_id: number | null;
    label: string;
}

interface RecordProps {
    id: number;
    user_id: number | null;
    patient_id: number | null;
    owner_name: string | null;
    pet_name: string | null;
    pet_sex: string | null;
    pet_date_of_birth: string | null;
    pet_breed: string | null;
    pet_color: string | null;
    vaccine_name: string | null;
    administered_at: string | null;
    next_due_at: string | null;
    batch_lot_number: string | null;
    veterinarian: string | null;
    notes: string | null;
    source: string | null;
}

interface Props {
    users: UserOpt[];
    patients: PatientOpt[];
    record: RecordProps;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Vaccination records', href: '/admin/vaccination_records' },
    { title: 'Edit', href: '#' },
];

const form = router.form({
    user_id: (props.record.user_id ?? '') as string | number,
    patient_id: (props.record.patient_id ?? '') as string | number,
    owner_name: props.record.owner_name ?? '',
    pet_name: props.record.pet_name ?? '',
    pet_sex: props.record.pet_sex ?? '',
    pet_date_of_birth: props.record.pet_date_of_birth ?? '',
    pet_breed: props.record.pet_breed ?? '',
    pet_color: props.record.pet_color ?? '',
    vaccine_name: props.record.vaccine_name ?? '',
    administered_at: props.record.administered_at ?? '',
    next_due_at: props.record.next_due_at ?? '',
    batch_lot_number: props.record.batch_lot_number ?? '',
    veterinarian: props.record.veterinarian ?? '',
    notes: props.record.notes ?? '',
    source: props.record.source ?? 'manual',
});

const filteredPatients = computed(() => {
    const pid = form.user_id;

    if (pid === '' || pid === null) {
        return props.patients;
    }

    const uid = Number(pid);

    return props.patients.filter((p) => p.user_id === null || p.user_id === uid);
});

watch(
    () => form.user_id,
    () => {
        if (form.patient_id === '' || form.patient_id === null) return;
        const p = props.patients.find((x) => x.id === Number(form.patient_id));
        if (p && p.user_id !== null && Number(form.user_id) !== p.user_id && form.user_id !== '') {
            form.patient_id = '';
        }
    },
);

const userSelectOptions = computed(() =>
    props.users.map((u) => ({
        value: u.id.toString(),
        label: `${u.name} (${u.email})`,
    })),
);

const patientSelectOptions = computed(() =>
    filteredPatients.value.map((p) => ({
        value: p.id.toString(),
        label: p.label,
    })),
);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            user_id: data.user_id === '' || data.user_id === null ? null : Number(data.user_id),
            patient_id: data.patient_id === '' || data.patient_id === null ? null : Number(data.patient_id),
            owner_name: data.owner_name === '' ? null : data.owner_name,
            pet_name: data.pet_name === '' ? null : data.pet_name,
            pet_sex: data.pet_sex === '' ? null : data.pet_sex,
            pet_date_of_birth: data.pet_date_of_birth === '' ? null : data.pet_date_of_birth,
            pet_breed: data.pet_breed === '' ? null : data.pet_breed,
            pet_color: data.pet_color === '' ? null : data.pet_color,
            vaccine_name: data.vaccine_name === '' ? null : data.vaccine_name,
            administered_at: data.administered_at === '' ? null : data.administered_at,
            next_due_at: data.next_due_at === '' ? null : data.next_due_at,
            batch_lot_number: data.batch_lot_number === '' ? null : data.batch_lot_number,
            veterinarian: data.veterinarian === '' ? null : data.veterinarian,
            notes: data.notes === '' ? null : data.notes,
            source: data.source === '' ? null : data.source,
        }))
        .put(`/admin/vaccination_records/${props.record.id}`);
};
</script>

<template>
    <Head title="Edit vaccination record" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto max-w-3xl p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link :href="`/admin/vaccination_records/${record.id}`">
                            <Button variant="ghost" size="sm" type="button">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Syringe class="h-5 w-5" />
                                Edit vaccination record
                            </CardTitle>
                            <CardDescription> Snapshot fields and links are all optional. </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Snapshot</h3>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="owner_name">Owner name</Label>
                                <Input id="owner_name" v-model="form.owner_name" type="text" autocomplete="off" />
                                <InputError :message="form.errors.owner_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="pet_name_snapshot">Pet name</Label>
                                <Input id="pet_name_snapshot" v-model="form.pet_name" type="text" autocomplete="off" />
                                <InputError :message="form.errors.pet_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="pet_sex">Pet sex</Label>
                                <Input id="pet_sex" v-model="form.pet_sex" type="text" placeholder="e.g. M, F" />
                                <InputError :message="form.errors.pet_sex" />
                            </div>
                            <div class="space-y-2">
                                <Label for="pet_date_of_birth">Pet date of birth</Label>
                                <Input id="pet_date_of_birth" v-model="form.pet_date_of_birth" type="date" />
                                <InputError :message="form.errors.pet_date_of_birth" />
                            </div>
                            <div class="space-y-2">
                                <Label for="pet_breed">Breed</Label>
                                <Input id="pet_breed" v-model="form.pet_breed" type="text" />
                                <InputError :message="form.errors.pet_breed" />
                            </div>
                            <div class="space-y-2">
                                <Label for="pet_color">Pet color</Label>
                                <Input id="pet_color" v-model="form.pet_color" type="text" />
                                <InputError :message="form.errors.pet_color" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="administered_at">Vaccination date</Label>
                                <Input id="administered_at" v-model="form.administered_at" type="date" />
                                <InputError :message="form.errors.administered_at" />
                            </div>
                            <div class="md:col-span-2 mt-2 border-t pt-6">
                                <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Clinical / registry</h3>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="vaccine_name">Vaccine name</Label>
                                <Input id="vaccine_name" v-model="form.vaccine_name" type="text" autocomplete="off" />
                                <InputError :message="form.errors.vaccine_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="next_due_at">Next due</Label>
                                <Input id="next_due_at" v-model="form.next_due_at" type="date" />
                                <InputError :message="form.errors.next_due_at" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="user_id">Linked owner</Label>
                                <SearchableSelect
                                    id="user_id"
                                    v-model="form.user_id"
                                    :options="userSelectOptions"
                                    placeholder="— Not linked —"
                                    search-placeholder="Search owners by name or email…"
                                />
                                <InputError :message="form.errors.user_id" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="patient_id">Linked pet</Label>
                                <SearchableSelect
                                    id="patient_id"
                                    v-model="form.patient_id"
                                    :options="patientSelectOptions"
                                    placeholder="— Not linked —"
                                    search-placeholder="Search pets…"
                                />
                                <InputError :message="form.errors.patient_id" />
                            </div>
                            <div class="space-y-2">
                                <Label for="batch_lot_number">Batch / lot</Label>
                                <Input id="batch_lot_number" v-model="form.batch_lot_number" type="text" />
                                <InputError :message="form.errors.batch_lot_number" />
                            </div>
                            <div class="space-y-2">
                                <Label for="veterinarian">Veterinarian</Label>
                                <Input id="veterinarian" v-model="form.veterinarian" type="text" />
                                <InputError :message="form.errors.veterinarian" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="source">Source tag</Label>
                                <Input id="source" v-model="form.source" type="text" placeholder="manual, import, …" />
                                <InputError :message="form.errors.source" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label for="notes">Notes</Label>
                                <Textarea id="notes" v-model="form.notes" rows="3" />
                                <InputError :message="form.errors.notes" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-4">
                            <Link :href="`/admin/vaccination_records/${record.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">Save changes</Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
