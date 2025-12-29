<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileText, Download, Calendar, X } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

interface Props {
    exportUrl: string;
    reportTitle: string;
    filters?: Record<string, any>;
}

const props = defineProps<Props>();

const { success: showSuccess, error: showError } = useToast();

const isOpen = ref(false);
const filterType = ref<'date' | 'month' | 'year' | 'range'>('date');
const selectedDate = ref('');
const selectedMonth = ref('');
const selectedYear = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 10 }, (_, i) => currentYear - i);
const months = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const canGenerate = computed(() => {
    switch (filterType.value) {
        case 'date':
            return !!selectedDate.value;
        case 'month':
            return !!selectedMonth.value && !!selectedYear.value;
        case 'year':
            return !!selectedYear.value;
        case 'range':
            return !!dateFrom.value && !!dateTo.value;
        default:
            return false;
    }
});

const resetFilters = () => {
    selectedDate.value = '';
    selectedMonth.value = '';
    selectedYear.value = '';
    dateFrom.value = '';
    dateTo.value = '';
};

const buildFilterParams = () => {
    const params: Record<string, any> = {
        filter_type: filterType.value,
        ...props.filters,
    };

    switch (filterType.value) {
        case 'date':
            params.date = selectedDate.value;
            break;
        case 'month':
            params.month = selectedMonth.value;
            params.year = selectedYear.value;
            break;
        case 'year':
            params.year = selectedYear.value;
            break;
        case 'range':
            params.date_from = dateFrom.value;
            params.date_to = dateTo.value;
            break;
    }

    return params;
};

const generateReport = async (format: 'pdf' | 'csv') => {
    if (!canGenerate.value) {
        showError('Please select a date filter before generating the report.');
        return;
    }

    const params = buildFilterParams();
    params.format = format;

    try {
        const response = await axios.get(props.exportUrl, {
            params,
            responseType: 'blob',
        });

        // Create a blob URL and trigger download
        const blob = new Blob([response.data], {
            type: format === 'pdf' ? 'application/pdf' : 'text/csv',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;

        // Generate filename
        const timestamp = new Date().toISOString().split('T')[0];
        let filename = `${props.reportTitle.replace(/\s+/g, '_')}_${timestamp}`;
        
        if (filterType.value === 'date' && selectedDate.value) {
            filename += `_${selectedDate.value}`;
        } else if (filterType.value === 'month' && selectedMonth.value && selectedYear.value) {
            filename += `_${selectedYear.value}-${selectedMonth.value}`;
        } else if (filterType.value === 'year' && selectedYear.value) {
            filename += `_${selectedYear.value}`;
        } else if (filterType.value === 'range' && dateFrom.value && dateTo.value) {
            filename += `_${dateFrom.value}_to_${dateTo.value}`;
        }

        filename += `.${format}`;
        link.download = filename;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        showSuccess(`Report generated successfully as ${format.toUpperCase()}.`);
        isOpen.value = false;
        resetFilters();
    } catch (error: any) {
        showError(error.response?.data?.message || 'Failed to generate report. Please try again.');
    }
};

const handleFilterTypeChange = () => {
    resetFilters();
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="outline">
                <FileText class="h-4 w-4 mr-2" />
                Generate Report
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Generate Report: {{ reportTitle }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Filter Type Selection -->
                <div class="space-y-2">
                    <Label>Filter By</Label>
                    <select
                        v-model="filterType"
                        @change="handleFilterTypeChange"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="date">Single Date</option>
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                        <option value="range">Date Range</option>
                    </select>
                </div>

                <!-- Single Date Filter -->
                <div v-if="filterType === 'date'" class="space-y-2">
                    <Label>Select Date</Label>
                    <Input
                        v-model="selectedDate"
                        type="date"
                        class="w-full"
                    />
                </div>

                <!-- Month Filter -->
                <div v-if="filterType === 'month'" class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-2">
                            <Label>Month</Label>
                            <select
                                v-model="selectedMonth"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Select Month</option>
                                <option v-for="month in months" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label>Year</Label>
                            <select
                                v-model="selectedYear"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Select Year</option>
                                <option v-for="year in years" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Year Filter -->
                <div v-if="filterType === 'year'" class="space-y-2">
                    <Label>Select Year</Label>
                    <select
                        v-model="selectedYear"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="">Select Year</option>
                        <option v-for="year in years" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div v-if="filterType === 'range'" class="space-y-2">
                    <div class="space-y-2">
                        <Label>From Date</Label>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>To Date</Label>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="w-full"
                        />
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 pt-4">
                    <Button
                        @click="generateReport('pdf')"
                        :disabled="!canGenerate"
                        class="flex-1"
                    >
                        <Download class="h-4 w-4 mr-2" />
                        Export PDF
                    </Button>
                    <Button
                        @click="generateReport('csv')"
                        :disabled="!canGenerate"
                        variant="outline"
                        class="flex-1"
                    >
                        <Download class="h-4 w-4 mr-2" />
                        Export CSV
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>


