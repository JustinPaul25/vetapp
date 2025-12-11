<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Shield, ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

interface Role {
    id: number;
    name: string;
}

interface Props {
    role: Role;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Roles', href: '/admin/roles' },
    { title: 'Edit Role', href: '#' },
];

const form = router.form({
    name: props.role.name,
});

const submit = () => {
    form.put(`/admin/roles/${props.role.id}`);
};
</script>

<template>
    <Head title="Edit Role" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/roles">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="h-5 w-5" />
                                Edit Role
                            </CardTitle>
                            <CardDescription>
                                Update role information
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Role Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/roles">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Update Role
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
