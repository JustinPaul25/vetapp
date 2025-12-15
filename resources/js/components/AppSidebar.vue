<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Users, Shield, Dog, Pill, FileText, Heart, UserCheck, MapPin, Calendar, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => auth.value?.user?.roles?.includes('admin') ?? false);
const isStaff = computed(() => auth.value?.user?.roles?.includes('staff') ?? false);
const isClient = computed(() => !isAdmin.value && !isStaff.value);
const pendingAppointmentsCount = computed(() => (page.props as any).pendingAppointmentsCount ?? 0);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (isAdmin.value) {
        items.push(
            // Core Operations - Most frequently used
            {
                title: 'Appointments',
                href: '/admin/appointments',
                icon: Calendar,
                badge: pendingAppointmentsCount.value > 0 ? pendingAppointmentsCount.value : undefined,
            },
            {
                title: 'Patients',
                href: '/admin/patients',
                icon: Heart,
            },
            {
                title: 'Pet Owners',
                href: '/admin/pet_owners',
                icon: UserCheck,
            },
            {
                title: 'Walk-In Clients',
                href: '/admin/walk_in_clients',
                icon: UserPlus,
            },
            // Clinical Operations
            {
                title: 'Prescriptions',
                href: '/admin/prescriptions',
                icon: FileText,
            },
            {
                title: 'Medicines',
                href: '/admin/medicines',
                icon: Pill,
            },
            // Reference Data
            {
                title: 'Pet Types',
                href: '/admin/pet_types',
                icon: Dog,
            },
            // Reports & Analytics
            {
                title: 'Disease Outbreak Map',
                href: '/admin/diseases/map',
                icon: MapPin,
            },
            // System Administration - Least frequently used, placed last
            {
                title: 'Users',
                href: '/admin/users',
                icon: Users,
            },
            {
                title: 'Roles',
                href: '/admin/roles',
                icon: Shield,
            }
        );
    } else if (isStaff.value) {
        items.push(
            // Daily Operations - Most frequently used
            {
                title: 'Appointments',
                href: '/admin/appointments',
                icon: Calendar,
                badge: pendingAppointmentsCount.value > 0 ? pendingAppointmentsCount.value : undefined,
            },
            // Patient Care
            {
                title: 'Patients',
                href: '/admin/patients',
                icon: Heart,
            },
            {
                title: 'Prescriptions',
                href: '/admin/prescriptions',
                icon: FileText,
            },
            // Clinical Resources
            {
                title: 'Medicines',
                href: '/admin/medicines',
                icon: Pill,
            },
            // Public Health
            {
                title: 'Disease Outbreak',
                href: '/admin/diseases/map',
                icon: MapPin,
            }
        );
    } else if (isClient.value) {
        items.push(
            {
                title: 'My Pets',
                href: '/pets',
                icon: Heart,
            },
            {
                title: 'My Appointments',
                href: '/appointments',
                icon: Calendar,
            }
        );
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
