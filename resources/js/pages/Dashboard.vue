<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Users, Shield, Dog, Heart, Pill, FileText, LayoutGrid, UserCheck, MapPin, Calendar, Stethoscope } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { Bar } from 'vue-chartjs';
import axios from 'axios';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend
);

interface DiseaseStat {
    id: number;
    name: string;
    count: number;
}

interface DiseaseStatistics {
    top_diseases: DiseaseStat[];
    total_diseases: number;
    total_cases: number;
    month: number;
    year: number;
}

interface Appointment {
    id: number;
    appointment_type: string;
    appointment_date: string | null;
    appointment_time: string | null;
    status: string;
    pet_type: string;
    pet_name: string;
}

interface DisabledDate {
    id: number;
    date: string;
    reason?: string | null;
}

interface Props {
    appointments?: Appointment[];
    disabledDates?: DisabledDate[];
}

const props = withDefaults(defineProps<Props>(), {
    appointments: () => [],
    disabledDates: () => [],
});

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
const pendingAppointmentsCount = computed(() => (page.props as any).pendingAppointmentsCount ?? 0);

const adminLinks = computed(() => [
    {
        title: 'Appointments',
        href: '/admin/appointments',
        icon: Calendar,
        description: pendingAppointmentsCount.value > 0 
            ? `Manage appointments (${pendingAppointmentsCount.value} pending)`
            : 'Manage appointments',
        color: 'text-blue-600 dark:text-blue-400',
        badge: pendingAppointmentsCount.value > 0 ? pendingAppointmentsCount.value : undefined,
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
        title: 'Diseases',
        href: '/admin/diseases',
        icon: Stethoscope,
        description: 'Manage diseases and symptoms',
        color: 'text-red-600 dark:text-red-400',
    },
    {
        title: 'Prescriptions',
        href: '/admin/prescriptions',
        icon: FileText,
        description: 'View all prescriptions',
        color: 'text-teal-600 dark:text-teal-400',
    },
]);

const staffLinks = computed(() => [
    {
        title: 'Disease Outbreak',
        href: '/admin/diseases/map',
        icon: MapPin,
        description: 'View disease map',
        color: 'text-red-600 dark:text-red-400',
    },
    {
        title: 'Appointments',
        href: '/admin/appointments',
        icon: Calendar,
        description: pendingAppointmentsCount.value > 0 
            ? `Manage appointments (${pendingAppointmentsCount.value} pending)`
            : 'Manage appointments',
        color: 'text-blue-600 dark:text-blue-400',
        badge: pendingAppointmentsCount.value > 0 ? pendingAppointmentsCount.value : undefined,
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
    {
        title: 'Diseases',
        href: '/admin/diseases',
        icon: Stethoscope,
        description: 'Manage diseases and symptoms',
        color: 'text-red-600 dark:text-red-400',
    },
]);

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

// Disease statistics
const diseaseStats = ref<DiseaseStatistics | null>(null);
const loading = ref(false);
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const fetchDiseaseStatistics = async () => {
    if (!isAdmin.value && !isStaff.value) return;
    
    loading.value = true;
    try {
        const response = await axios.get('/admin/diseases/statistics', {
            params: {
                month: selectedMonth.value,
                year: selectedYear.value,
            },
        });
        
        diseaseStats.value = response.data;
    } catch (error) {
        console.error('Error fetching disease statistics:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchDiseaseStatistics();
});

const chartData = computed(() => {
    if (!diseaseStats.value || !diseaseStats.value.top_diseases.length) {
        return {
            labels: [],
            datasets: [],
        };
    }

    const top10 = diseaseStats.value.top_diseases.slice(0, 10);
    
    return {
        labels: top10.map(d => d.name),
        datasets: [
            {
                label: 'Number of Cases',
                data: top10.map(d => d.count),
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',  // blue-500
                    'rgba(16, 185, 129, 0.8)',  // emerald-500
                    'rgba(245, 158, 11, 0.8)',  // amber-500
                    'rgba(239, 68, 68, 0.8)',   // red-500
                    'rgba(139, 92, 246, 0.8)',  // violet-500
                    'rgba(236, 72, 153, 0.8)',  // pink-500
                    'rgba(14, 165, 233, 0.8)',  // sky-500
                    'rgba(34, 197, 94, 0.8)',   // green-500
                    'rgba(251, 146, 60, 0.8)',  // orange-500
                    'rgba(168, 85, 247, 0.8)',  // purple-500
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(139, 92, 246, 1)',
                    'rgba(236, 72, 153, 1)',
                    'rgba(14, 165, 233, 1)',
                    'rgba(34, 197, 94, 1)',
                    'rgba(251, 146, 60, 1)',
                    'rgba(168, 85, 247, 1)',
                ],
                borderWidth: 1,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
                weight: 'bold' as const,
            },
            bodyFont: {
                size: 13,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
            },
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 45,
                minRotation: 45,
                font: {
                    size: 11,
                },
            },
        },
    },
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Dashboard</h1>
                <p class="text-muted-foreground mt-2">Welcome to the veterinary management system</p>
            </div>

            <!-- Appointments Calendar Section (Admin and Staff only) -->
            <div v-if="(isAdmin || isStaff) && !isClient" class="mb-8">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold">Scheduled Appointments</h2>
                    <p class="text-sm text-muted-foreground mt-1">View and manage all scheduled appointments</p>
                </div>
                <Card>
                    <CardContent class="p-6">
                        <AppointmentCalendar 
                            :appointments="props.appointments || []" 
                            :disabled-dates="props.disabledDates || []"
                            :can-manage-disabled-dates="true"
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- Disease Statistics Section (Admin and Staff only) -->
            <div v-if="(isAdmin || isStaff) && !isClient" class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Disease Statistics</h2>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedMonth"
                            @change="fetchDiseaseStatistics"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white dark:bg-gray-800 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="(name, index) in monthNames" :key="index" :value="index + 1">
                                {{ name }}
                            </option>
                        </select>
                        <select
                            v-model="selectedYear"
                            @change="fetchDiseaseStatistics"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white dark:bg-gray-800 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="year in Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Diseases by Month Chart -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                Top Diseases by Month
                            </CardTitle>
                            <CardDescription>
                                Most common diseases in {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="flex items-center justify-center h-64">
                                <div class="text-muted-foreground">Loading...</div>
                            </div>
                            <div v-else-if="diseaseStats && diseaseStats.top_diseases.length > 0" class="h-64">
                                <Bar :data="chartData" :options="chartOptions" />
                            </div>
                            <div v-else class="flex items-center justify-center h-64 text-muted-foreground">
                                No disease data available for this month
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Number of Diseases Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                Disease Overview
                            </CardTitle>
                            <CardDescription>
                                Statistics for {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="loading" class="flex items-center justify-center h-64">
                                <div class="text-muted-foreground">Loading...</div>
                            </div>
                            <div v-else-if="diseaseStats" class="space-y-6">
                                <div class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg">
                                    <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                        {{ diseaseStats.total_diseases }}
                                    </div>
                                    <div class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                        Number of Diseases
                                    </div>
                                </div>
                                <div class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-lg">
                                    <div class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">
                                        {{ diseaseStats.total_cases }}
                                    </div>
                                    <div class="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                        Total Cases
                                    </div>
                                </div>
                                <div v-if="diseaseStats.top_diseases.length > 0" class="mt-4">
                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        Top 3 Diseases:
                                    </div>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(disease, index) in diseaseStats.top_diseases.slice(0, 3)"
                                            :key="disease.id"
                                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-xs font-semibold">
                                                    {{ index + 1 }}
                                                </span>
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ disease.name }}
                                                </span>
                                            </div>
                                            <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                                {{ disease.count }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center h-64 text-muted-foreground">
                                No disease data available for this month
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
