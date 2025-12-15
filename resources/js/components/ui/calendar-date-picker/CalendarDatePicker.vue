<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Calendar } from 'lucide-vue-next';

interface Props {
    modelValue?: string;
    minDate?: string;
    maxDate?: string;
    disabled?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    minDate: '',
    maxDate: '',
    disabled: false,
    class: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const currentMonth = ref(new Date());
const selectedDate = computed(() => {
    if (!props.modelValue) return null;
    return new Date(props.modelValue);
});

const minDateObj = computed(() => {
    if (!props.minDate) return null;
    const date = new Date(props.minDate);
    date.setHours(0, 0, 0, 0);
    return date;
});

const maxDateObj = computed(() => {
    if (!props.maxDate) return null;
    const date = new Date(props.maxDate);
    date.setHours(0, 0, 0, 0);
    return date;
});

const today = computed(() => {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    return date;
});

const monthYear = computed(() => {
    return currentMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const firstDayOfMonth = computed(() => {
    const firstDay = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1);
    return firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
});

const daysInMonth = computed(() => {
    return new Date(
        currentMonth.value.getFullYear(),
        currentMonth.value.getMonth() + 1,
        0
    ).getDate();
});

const calendarDays = computed(() => {
    const days: (Date | null)[] = [];
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < firstDayOfMonth.value; i++) {
        days.push(null);
    }
    
    // Add all days of the month
    for (let day = 1; day <= daysInMonth.value; day++) {
        const date = new Date(
            currentMonth.value.getFullYear(),
            currentMonth.value.getMonth(),
            day
        );
        days.push(date);
    }
    
    return days;
});

const isDateDisabled = (date: Date): boolean => {
    if (props.disabled) return true;
    
    const dateOnly = new Date(date);
    dateOnly.setHours(0, 0, 0, 0);
    
    if (minDateObj.value && dateOnly < minDateObj.value) return true;
    if (maxDateObj.value && dateOnly > maxDateObj.value) return true;
    
    // Disable past dates (before today)
    if (dateOnly < today.value) return true;
    
    return false;
};

const isDateSelected = (date: Date): boolean => {
    if (!selectedDate.value) return false;
    return (
        date.getDate() === selectedDate.value.getDate() &&
        date.getMonth() === selectedDate.value.getMonth() &&
        date.getFullYear() === selectedDate.value.getFullYear()
    );
};

const selectDate = (date: Date) => {
    if (isDateDisabled(date)) return;
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateString = `${year}-${month}-${day}`;
    
    emit('update:modelValue', dateString);
    isOpen.value = false;
};

const previousMonth = () => {
    currentMonth.value = new Date(
        currentMonth.value.getFullYear(),
        currentMonth.value.getMonth() - 1,
        1
    );
};

const nextMonth = () => {
    currentMonth.value = new Date(
        currentMonth.value.getFullYear(),
        currentMonth.value.getMonth() + 1,
        1
    );
};

const displayValue = computed(() => {
    if (!props.modelValue) return '';
    const date = new Date(props.modelValue);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
});

const toggleCalendar = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    
    // Set current month to selected date or today
    if (selectedDate.value) {
        currentMonth.value = new Date(selectedDate.value);
    } else {
        currentMonth.value = new Date();
    }
};

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
</script>

<template>
    <div ref="containerRef" class="relative" :class="props.class">
        <!-- Date input trigger -->
        <div
            @click="toggleCalendar"
            :class="cn(
                'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'cursor-pointer',
                isOpen && 'ring-2 ring-ring'
            )"
            :aria-disabled="disabled"
        >
            <span :class="{ 'text-muted-foreground': !modelValue }">
                {{ displayValue || 'Select date' }}
            </span>
            <Calendar class="h-4 w-4 text-muted-foreground" />
        </div>

        <!-- Calendar popover -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-[320px] rounded-md border bg-popover text-popover-foreground shadow-lg p-4"
        >
            <!-- Month/Year header with navigation -->
            <div class="flex items-center justify-between mb-4">
                <button
                    type="button"
                    @click="previousMonth"
                    class="p-1 rounded-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                    :disabled="props.disabled"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <h3 class="font-semibold text-base">{{ monthYear }}</h3>
                <button
                    type="button"
                    @click="nextMonth"
                    class="p-1 rounded-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                    :disabled="props.disabled"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <!-- Day names -->
            <div class="grid grid-cols-7 gap-1 mb-2">
                <div
                    v-for="dayName in dayNames"
                    :key="dayName"
                    class="text-center text-xs font-medium text-muted-foreground py-1"
                >
                    {{ dayName }}
                </div>
            </div>

            <!-- Calendar grid -->
            <div class="grid grid-cols-7 gap-1">
                <div
                    v-for="(date, index) in calendarDays"
                    :key="index"
                    class="aspect-square flex items-center justify-center"
                >
                    <button
                        v-if="date"
                        type="button"
                        @click="selectDate(date)"
                        :disabled="isDateDisabled(date)"
                        :class="cn(
                            'w-9 h-9 rounded-full text-sm font-medium transition-all duration-200',
                            'flex items-center justify-center',
                            'focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1',
                            isDateDisabled(date)
                                ? 'text-muted-foreground/50 cursor-not-allowed'
                                : isDateSelected(date)
                                ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary ring-offset-1 scale-110'
                                : 'text-primary cursor-pointer bg-primary/10 hover:bg-primary/20 hover:scale-105',
                        )"
                    >
                        {{ date.getDate() }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

