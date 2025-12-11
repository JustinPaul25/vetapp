<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Pill, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Medicines', href: '/admin/medicines' },
    { title: 'Create Medicine', href: '#' },
];

const form = router.form({
    name: '',
    stock: 0,
    dosage: '',
    route: '',
});

const submit = () => {
    form.post('/admin/medicines');
};
</script>

<template>
    <Head title="Create Medicine" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/medicines">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Pill class="h-5 w-5" />
                                Create New Medicine
                            </CardTitle>
                            <CardDescription>
                                Add a new medicine to the system
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Medicine Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g., Amoxicillin trihydrate"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="stock">Stock</Label>
                                <Input
                                    id="stock"
                                    v-model="form.stock"
                                    type="number"
                                    required
                                    min="0"
                                    placeholder="0"
                                />
                                <InputError :message="form.errors.stock" />
                            </div>

                            <div class="space-y-2">
                                <Label for="route">Route</Label>
                                <Input
                                    id="route"
                                    v-model="form.route"
                                    type="text"
                                    required
                                    placeholder="e.g., IM, SC, PO"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.route" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="dosage">Dosage</Label>
                            <Input
                                id="dosage"
                                v-model="form.dosage"
                                type="text"
                                required
                                placeholder="e.g., 1ml/10 Kg body weight"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.dosage" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/medicines">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Create Medicine
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>




