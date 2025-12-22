<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Dog, ArrowLeft, Edit } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface PetType {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    pet_type: PetType;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pet Types', href: '/admin/pet_types' },
    { title: 'View Pet Type', href: '#' },
];
</script>

<template>
    <Head title="View Pet Type" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Link href="/admin/pet_types">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft class="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Dog class="h-5 w-5" />
                                    View Pet Type
                                </CardTitle>
                                <CardDescription>
                                    Pet type details
                                </CardDescription>
                            </div>
                        </div>
                        <Link :href="`/admin/pet_types/${pet_type.id}/edit`">
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
                            <div class="text-lg font-semibold">{{ pet_type.name }}</div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                            <div class="text-sm text-muted-foreground">
                                {{ new Date(pet_type.created_at).toLocaleString() }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-medium text-muted-foreground">Updated At</Label>
                            <div class="text-sm text-muted-foreground">
                                {{ new Date(pet_type.updated_at).toLocaleString() }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

















