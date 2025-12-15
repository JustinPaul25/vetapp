<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Pill, ArrowLeft, Edit } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface Medicine {
    id: number;
    name: string;
    stock: number;
    dosage: string;
    route: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    medicine: Medicine;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Medicines', href: '/admin/medicines' },
    { title: 'View Medicine', href: '#' },
];
</script>

<template>
    <Head title="View Medicine" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/medicines">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Pill class="h-5 w-5" />
                                    View Medicine
                                </CardTitle>
                                <CardDescription>
                                    Medicine details
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/medicines/${medicine.id}/edit`">
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
                            <Label class="text-sm font-medium text-muted-foreground">Medicine Name</Label>
                            <div class="text-lg font-semibold">{{ medicine.name }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Stock</Label>
                                <div :class="medicine.stock < 10 ? 'text-lg font-semibold text-destructive' : 'text-lg font-semibold'">
                                    {{ medicine.stock }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Route</Label>
                                <div class="text-lg font-semibold">{{ medicine.route }}</div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Dosage</Label>
                            <div class="text-lg font-semibold">{{ medicine.dosage }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ new Date(medicine.created_at).toLocaleString() }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                                <div class="text-sm text-muted-foreground">
                                    {{ new Date(medicine.updated_at).toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>







