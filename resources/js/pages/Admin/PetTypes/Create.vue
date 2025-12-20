<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Dog, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pet Types', href: '/admin/pet_types' },
    { title: 'Create Pet Type', href: '#' },
];

const form = router.form({
    name: '',
});

const submit = () => {
    form.post('/admin/pet_types');
};
</script>

<template>
    <Head title="Create Pet Type" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/pet_types">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Dog class="h-5 w-5" />
                                Create New Pet Type
                            </CardTitle>
                            <CardDescription>
                                Add a new pet type to the system
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Pet Type Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g., Dog, Cat, Rabbit"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/pet_types">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Create Pet Type
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
















