<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Syringe, ArrowLeft, Edit, Trash2 } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { computed } from 'vue';

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface Patient {
    id: number;
    pet_name: string | null;
    pet_breed: string | null;
    pet_type: string | null;
}

interface Props {
    record: {
        id: number;
        vaccine_name: string | null;
        administered_at: string | null;
        next_due_at: string | null;
        owner_name: string | null;
        pet_name: string | null;
        pet_sex: string | null;
        pet_date_of_birth: string | null;
        pet_breed: string | null;
        pet_color: string | null;
        batch_lot_number: string | null;
        veterinarian: string | null;
        notes: string | null;
        source: string | null;
        owner: Owner | null;
        patient: Patient | null;
        created_at: string;
        updated_at: string;
    };
}

const props = defineProps<Props>();

const displayTitle = computed(() => props.record.vaccine_name || 'Vaccination record');

const breadcrumbs = computed(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Vaccination records', href: '/admin/vaccination_records' },
    { title: displayTitle.value, href: '#' },
]);

const formatDate = (d: string | null | undefined) => {
    if (!d) return '—';
    try {
        return new Date(d + 'T00:00:00').toLocaleDateString();
    } catch {
        return d;
    }
};

const destroyRecord = () => {
    if (confirm('Delete this vaccination record?')) {
        router.delete(`/admin/vaccination_records/${props.record.id}`);
    }
};
</script>

<template>
    <Head :title="displayTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto max-w-3xl p-6">
            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-2">
                            <Link href="/admin/vaccination_records">
                                <Button variant="ghost" size="sm" type="button">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Syringe class="h-5 w-5" />
                                    {{ displayTitle }}
                                </CardTitle>
                                <CardDescription>
                                    <span v-if="record.administered_at">Vaccination date {{ formatDate(record.administered_at) }}</span>
                                    <span v-else>Vaccination date not set</span>
                                    <span v-if="record.next_due_at"> · Next due {{ formatDate(record.next_due_at) }}</span>
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Link :href="`/admin/vaccination_records/${record.id}/edit`">
                                <Button variant="outline" size="sm" type="button">
                                    <Edit class="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Button variant="destructive" size="sm" type="button" @click="destroyRecord">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-8">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Snapshot</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Owner name</p>
                                <p class="mt-1">{{ record.owner_name || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pet name</p>
                                <p class="mt-1">{{ record.pet_name || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pet sex</p>
                                <p class="mt-1">{{ record.pet_sex || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pet date of birth</p>
                                <p class="mt-1">{{ formatDate(record.pet_date_of_birth) }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Breed</p>
                                <p class="mt-1">{{ record.pet_breed || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pet color</p>
                                <p class="mt-1">{{ record.pet_color || '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Linked accounts</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Owner</p>
                                <p v-if="record.owner" class="mt-1">
                                    <Link :href="`/admin/pet_owners/${record.owner.id}`" class="text-primary hover:underline">
                                        {{ record.owner.name }}
                                    </Link>
                                    <span class="block text-sm text-muted-foreground">{{ record.owner.email }}</span>
                                </p>
                                <p v-else class="mt-1 text-muted-foreground">Not linked</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Pet</p>
                                <p v-if="record.patient" class="mt-1">
                                    <Link :href="`/admin/patients/${record.patient.id}`" class="text-primary hover:underline">
                                        {{ record.patient.pet_name || 'Unnamed' }}
                                    </Link>
                                    <span class="block text-sm text-muted-foreground">
                                        {{ record.patient.pet_type || 'Pet' }}
                                        <span v-if="record.patient.pet_breed"> · {{ record.patient.pet_breed }}</span>
                                    </span>
                                </p>
                                <p v-else class="mt-1 text-muted-foreground">Not linked</p>
                            </div>
                            <div v-if="record.batch_lot_number">
                                <p class="text-sm font-medium text-muted-foreground">Batch / lot</p>
                                <p class="mt-1">{{ record.batch_lot_number }}</p>
                            </div>
                            <div v-if="record.veterinarian">
                                <p class="text-sm font-medium text-muted-foreground">Veterinarian</p>
                                <p class="mt-1">{{ record.veterinarian }}</p>
                            </div>
                            <div v-if="record.source">
                                <p class="text-sm font-medium text-muted-foreground">Source</p>
                                <p class="mt-1">{{ record.source }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="record.notes">
                        <p class="text-sm font-medium text-muted-foreground">Notes</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ record.notes }}</p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Created {{ new Date(record.created_at).toLocaleString() }} · Updated
                        {{ new Date(record.updated_at).toLocaleString() }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
