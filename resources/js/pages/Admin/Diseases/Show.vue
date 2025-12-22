<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Stethoscope, ArrowLeft, Edit, Pill, Activity } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface Symptom {
    id: number;
    name: string;
}

interface Medicine {
    id: number;
    name: string;
    dosage: string;
    stock: number;
}

interface Disease {
    id: number;
    name: string;
    home_remedy: string | null;
    symptoms: Symptom[];
    medicines: Medicine[];
    created_at: string;
    updated_at: string;
}

interface Props {
    disease: Disease;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Diseases', href: '/admin/diseases' },
    { title: props.disease.name, href: '#' },
];
</script>

<template>
    <Head :title="`View ${disease.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/diseases">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Stethoscope class="h-5 w-5" />
                                    {{ disease.name }}
                                </CardTitle>
                                <CardDescription>
                                    Disease details and associations
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/diseases/${disease.id}/edit`">
                            <Button>
                                <Edit class="h-4 w-4 mr-2" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Disease Name</Label>
                            <div class="text-2xl font-semibold">{{ disease.name }}</div>
                        </div>

                        <div class="space-y-3">
                            <Label class="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                <Activity class="h-4 w-4" />
                                Associated Symptoms
                            </Label>
                            <div v-if="disease.symptoms.length > 0" class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="symptom in disease.symptoms"
                                    :key="symptom.id"
                                    variant="secondary"
                                    class="px-3 py-1.5"
                                >
                                    {{ symptom.name }}
                                </Badge>
                            </div>
                            <div v-else class="text-sm text-muted-foreground italic">
                                No symptoms associated with this disease
                            </div>
                        </div>

                        <div class="space-y-3">
                            <Label class="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                <Pill class="h-4 w-4" />
                                Recommended Medicines
                            </Label>
                            <div v-if="disease.medicines.length > 0" class="space-y-2">
                                <div
                                    v-for="medicine in disease.medicines"
                                    :key="medicine.id"
                                    class="border rounded-lg p-3 hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium">{{ medicine.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ medicine.dosage }}</div>
                                        </div>
                                        <div class="text-sm">
                                            <span :class="medicine.stock < 10 ? 'text-destructive font-semibold' : 'text-muted-foreground'">
                                                Stock: {{ medicine.stock }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-muted-foreground italic">
                                No medicines recommended for this disease
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Home Remedy & Care Instructions</Label>
                            <div v-if="disease.home_remedy" class="text-sm leading-relaxed bg-muted/50 p-4 rounded-lg">
                                {{ disease.home_remedy }}
                            </div>
                            <div v-else class="text-sm text-muted-foreground italic">
                                No home remedy information available
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ new Date(disease.created_at).toLocaleString() }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ new Date(disease.updated_at).toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


