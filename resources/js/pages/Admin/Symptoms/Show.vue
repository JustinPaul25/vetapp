<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Activity, ArrowLeft, Edit } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface Disease {
    id: number;
    name: string;
}

interface Symptom {
    id: number;
    name: string;
    diseases_count: number;
    diseases: Disease[];
    created_at: string;
    updated_at: string;
}

interface Props {
    symptom: Symptom;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Symptoms', href: '/admin/symptoms' },
    { title: 'View Symptom', href: '#' },
];
</script>

<template>
    <Head title="View Symptom" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/symptoms">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Activity class="h-5 w-5" />
                                    View Symptom
                                </CardTitle>
                                <CardDescription>
                                    Symptom details
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/symptoms/${symptom.id}/edit`">
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
                            <Label class="text-sm font-medium text-muted-foreground">Name</Label>
                            <div class="text-lg font-semibold">{{ symptom.name }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Associated Diseases</Label>
                            <div class="text-sm">
                                <div v-if="symptom.diseases && symptom.diseases.length > 0" class="space-y-1">
                                    <div
                                        v-for="disease in symptom.diseases"
                                        :key="disease.id"
                                        class="flex items-center gap-2"
                                    >
                                        <Link
                                            :href="`/admin/diseases/${disease.id}`"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                                        >
                                            {{ disease.name }}
                                        </Link>
                                    </div>
                                </div>
                                <div v-else class="text-muted-foreground">
                                    No diseases associated with this symptom
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                            <div class="text-sm text-muted-foreground">
                                {{ new Date(symptom.created_at).toLocaleString() }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                            <div class="text-sm text-muted-foreground">
                                {{ new Date(symptom.updated_at).toLocaleString() }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>



