<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { ChevronDown, X } from 'lucide-vue-next';

interface Option {
    value: string | number;
    label: string;
}

interface Props {
    modelValue?: (string | number)[];
    options: Option[];
    placeholder?: string;
    searchPlaceholder?: string;
    class?: string;
    disabled?: boolean;
    required?: boolean;
    maxSelected?: number;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    placeholder: 'Select options',
    searchPlaceholder: 'Search...',
    disabled: false,
    required: false,
    maxSelected: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: (string | number)[]];
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref<HTMLElement | null>(null);

const selectedValues = computed(() => {
    return props.modelValue || [];
});

const selectedOptions = computed(() => {
    return props.options.filter(opt => 
        selectedValues.value.includes(opt.value.toString())
    );
});

const filteredOptions = computed(() => {
    let filtered = props.options;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(option =>
            option.label.toLowerCase().includes(query)
        );
    }
    
    // Filter out already selected options
    return filtered.filter(opt => 
        !selectedValues.value.includes(opt.value.toString())
    );
});

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        // Focus the search input after dropdown opens
        setTimeout(() => {
            const searchInput = containerRef.value?.querySelector('input[type="text"]') as HTMLInputElement;
            if (searchInput) {
                searchInput.focus();
            }
        }, 0);
    }
};

const selectOption = (option: Option) => {
    const currentValues = [...selectedValues.value];
    const valueStr = option.value.toString();
    
    if (!currentValues.includes(valueStr)) {
        // Check max-selected limit
        if (props.maxSelected !== undefined && currentValues.length >= props.maxSelected) {
            return; // Don't allow selection if max limit reached
        }
        currentValues.push(valueStr);
        emit('update:modelValue', currentValues);
    }
    
    searchQuery.value = '';
    // Keep dropdown open for multiple selections
};

const removeOption = (value: string | number, e: Event) => {
    e.stopPropagation();
    const currentValues = [...selectedValues.value];
    const valueStr = value.toString();
    const index = currentValues.indexOf(valueStr);
    
    if (index > -1) {
        currentValues.splice(index, 1);
        emit('update:modelValue', currentValues);
    }
};

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
        searchQuery.value = '';
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative" :class="props.class">
        <!-- Selected values display / trigger -->
        <div
            @click="toggleDropdown"
            :class="cn(
                'flex min-h-10 w-full flex-wrap items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'cursor-pointer',
                isOpen && 'ring-2 ring-ring'
            )"
            :aria-disabled="disabled"
        >
            <!-- Selected tags -->
            <div v-if="selectedOptions.length > 0" class="flex flex-wrap gap-1.5 flex-1">
                <Badge
                    v-for="option in selectedOptions"
                    :key="option.value"
                    variant="default"
                    class="flex items-center gap-1.5 px-2 py-0.5"
                >
                    <span>{{ option.label }}</span>
                    <button
                        type="button"
                        @click.stop="removeOption(option.value, $event)"
                        :disabled="disabled"
                        class="ml-0.5 rounded-sm hover:bg-primary/20 focus:outline-none focus:ring-1 focus:ring-ring disabled:pointer-events-none"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </Badge>
            </div>
            
            <!-- Placeholder when nothing selected -->
            <span
                v-else
                class="text-muted-foreground flex-1"
            >
                {{ placeholder }}
            </span>
            
            <!-- Dropdown icon -->
            <ChevronDown
                class="h-4 w-4 text-muted-foreground transition-transform shrink-0"
                :class="{ 'rotate-180': isOpen }"
            />
        </div>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
        >
            <!-- Search input -->
            <div class="p-2 border-b">
                <Input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="h-9"
                    @click.stop
                />
            </div>

            <!-- Options list -->
            <div class="max-h-[200px] overflow-auto p-1">
                <div
                    v-if="filteredOptions.length === 0"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ searchQuery ? 'No options found' : (props.maxSelected && selectedValues.length >= props.maxSelected ? `Maximum ${props.maxSelected} selection${props.maxSelected !== 1 ? 's' : ''} reached` : 'All options selected') }}
                </div>
                <div
                    v-for="option in filteredOptions"
                    :key="option.value"
                    @click="selectOption(option)"
                    :class="cn(
                        'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm',
                        'outline-none hover:bg-accent hover:text-accent-foreground',
                        'focus:bg-accent focus:text-accent-foreground',
                        props.maxSelected !== undefined && selectedValues.length >= props.maxSelected && 'opacity-50 cursor-not-allowed'
                    )"
                >
                    {{ option.label }}
                </div>
            </div>
        </div>
    </div>
</template>










