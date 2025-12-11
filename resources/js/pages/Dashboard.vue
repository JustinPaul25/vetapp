<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Users, Shield, Dog, Heart, Pill, FileText, LayoutGrid, UserCheck, MapPin, Calendar } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => auth.value?.user?.roles?.includes('admin') ?? false);
const isStaff = computed(() => auth.value?.user?.roles?.includes('staff') ?? false);
const isClient = computed(() => !isAdmin.value && !isStaff.value);

const adminLinks = [
    {
        title: 'Appointments',
        href: '/admin/appointments',
        icon: Calendar,
        description: 'Manage appointments',
        color: 'text-blue-600 dark:text-blue-400',
    },
    {
        title: 'Patients',
        href: '/admin/patients',
        icon: Heart,
        description: 'Manage patient records',
        color: 'text-pink-600 dark:text-pink-400',
    },
    {
        title: 'Pet Owners',
        href: '/admin/pet_owners',
        icon: UserCheck,
        description: 'Manage pet owners',
        color: 'text-indigo-600 dark:text-indigo-400',
    },
    {
        title: 'Pet Types',
        href: '/admin/pet_types',
        icon: Dog,
        description: 'Manage pet types and species',
        color: 'text-blue-600 dark:text-blue-400',
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
        description: 'Manage user accounts',
        color: 'text-green-600 dark:text-green-400',
    },
    {
        title: 'Roles',
        href: '/admin/roles',
        icon: Shield,
        description: 'Manage user roles and permissions',
        color: 'text-purple-600 dark:text-purple-400',
    },
    {
        title: 'Medicines',
        href: '/admin/medicines',
        icon: Pill,
        description: 'Manage medicine inventory',
        color: 'text-orange-600 dark:text-orange-400',
    },
    {
        title: 'Prescriptions',
        href: '/admin/prescriptions',
        icon: FileText,
        description: 'View all prescriptions',
        color: 'text-teal-600 dark:text-teal-400',
    },
];

const staffLinks = [
    {
        title: 'Disease Outbreak',
        href: '/admin/diseases/map',
        icon: MapPin,
        description: 'View disease outbreak map',
        color: 'text-red-600 dark:text-red-400',
    },
    {
        title: 'Appointments',
        href: '/admin/appointments',
        icon: Calendar,
        description: 'Manage appointments',
        color: 'text-blue-600 dark:text-blue-400',
    },
    {
        title: 'Prescription',
        href: '/admin/prescriptions',
        icon: FileText,
        description: 'View and manage prescriptions',
        color: 'text-teal-600 dark:text-teal-400',
    },
    {
        title: 'Patients',
        href: '/admin/patients',
        icon: Heart,
        description: 'Manage patient records',
        color: 'text-pink-600 dark:text-pink-400',
    },
    {
        title: 'Medicines',
        href: '/admin/medicines',
        icon: Pill,
        description: 'Manage medicine inventory',
        color: 'text-orange-600 dark:text-orange-400',
    },
];

const clientLinks = [
    {
        title: 'My Pets',
        href: '/pets',
        icon: Heart,
        description: 'Manage your registered pets',
        color: 'text-pink-600 dark:text-pink-400',
    },
    {
        title: 'My Appointments',
        href: '/appointments',
        icon: Calendar,
        description: 'View and manage your appointments',
        color: 'text-blue-600 dark:text-blue-400',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Dashboard</h1>
                <p class="text-muted-foreground mt-2">Welcome to the veterinary management system</p>
            </div>

            <div v-if="isAdmin" class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Quick Links</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="link in adminLinks"
                        :key="link.href"
                        :href="link.href"
                        class="block"
                    >
                        <Card class="hover:shadow-lg transition-shadow cursor-pointer h-full">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="link.icon"
                                        :class="['h-6 w-6', link.color]"
                                    />
                                    <CardTitle>{{ link.title }}</CardTitle>
                                </div>
                                <CardDescription>{{ link.description }}</CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>

            <div v-if="isStaff && !isAdmin" class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Quick Links</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="link in staffLinks"
                        :key="link.href"
                        :href="link.href"
                        class="block"
                    >
                        <Card class="hover:shadow-lg transition-shadow cursor-pointer h-full">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="link.icon"
                                        :class="['h-6 w-6', link.color]"
                                    />
                                    <CardTitle>{{ link.title }}</CardTitle>
                                </div>
                                <CardDescription>{{ link.description }}</CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>

            <div v-if="isClient" class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Quick Links</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="link in clientLinks"
                        :key="link.href"
                        :href="link.href"
                        class="block"
                    >
                        <Card class="hover:shadow-lg transition-shadow cursor-pointer h-full">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="link.icon"
                                        :class="['h-6 w-6', link.color]"
                                    />
                                    <CardTitle>{{ link.title }}</CardTitle>
                                </div>
                                <CardDescription>{{ link.description }}</CardDescription>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Welcome</CardTitle>
                        <CardDescription>
                            Get started by managing your veterinary practice
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            Use the quick links above to navigate to different sections of the application.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>System Status</CardTitle>
                        <CardDescription>All systems operational</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            Your veterinary management system is running smoothly.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Need Help?</CardTitle>
                        <CardDescription>Get support when you need it</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            Contact your system administrator for assistance.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
