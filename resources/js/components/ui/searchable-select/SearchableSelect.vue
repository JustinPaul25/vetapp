<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ChevronDown, X, Plus } from 'lucide-vue-next';

interface Option {
    value: string | number;
    label: string;
}

interface Props {
    modelValue?: string | number | null;
    options: Option[];
    placeholder?: string;
    searchPlaceholder?: string;
    class?: string;
    disabled?: boolean;
    required?: boolean;
    allowCreate?: boolean;
    createPrefix?: string;
    customValue?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select an option',
    searchPlaceholder: 'Search...',
    disabled: false,
    required: false,
    allowCreate: false,
    createPrefix: 'Create',
    customValue: null,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
    'update:customValue': [value: string | null];
    'create': [value: string];
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref<HTMLElement | null>(null);

const filteredOptions = computed(() => {
    if (!searchQuery.value) {
        return props.options;
    }
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option =>
        option.label.toLowerCase().includes(query)
    );
});

// Check if search query exactly matches an existing option
const hasExactMatch = computed(() => {
    if (!searchQuery.value) return true;
    const query = searchQuery.value.toLowerCase().trim();
    return props.options.some(option => option.label.toLowerCase() === query);
});

// Show create option when allowCreate is true, no exact match, and search query is not empty
const showCreateOption = computed(() => {
    return props.allowCreate && !hasExactMatch.value && searchQuery.value.trim().length > 0;
});

const selectedOption = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return null;
    return props.options.find(opt => opt.value.toString() === props.modelValue?.toString()) || null;
});

const displayValue = computed(() => {
    // If we have a custom value set, show that
    if (props.customValue) {
        return props.customValue;
    }
    return selectedOption.value?.label || '';
});

const selectOption = (option: Option) => {
    emit('update:modelValue', option.value.toString());
    emit('update:customValue', null); // Clear custom value when selecting existing option
    searchQuery.value = '';
    isOpen.value = false;
};

const createNewOption = () => {
    const newValue = searchQuery.value.trim();
    if (newValue) {
        emit('update:modelValue', '__new__'); // Special marker for new value
        emit('update:customValue', newValue);
        emit('create', newValue);
        searchQuery.value = '';
        isOpen.value = false;
    }
};

const clearSelection = (e: Event) => {
    e.stopPropagation();
    emit('update:modelValue', '');
    emit('update:customValue', null);
    searchQuery.value = '';
    isOpen.value = false;
};

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
        <!-- Selected value display / trigger -->
        <div
            @click="toggleDropdown"
            :class="cn(
                'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'cursor-pointer',
                isOpen && 'ring-2 ring-ring'
            )"
            :aria-disabled="disabled"
        >
            <span :class="{ 'text-muted-foreground': !selectedOption }">
                {{ displayValue || placeholder }}
            </span>
            <div class="flex items-center gap-1">
                <X
                    v-if="selectedOption && !disabled"
                    class="h-4 w-4 text-muted-foreground hover:text-foreground"
                    @click.stop="clearSelection"
                />
                <ChevronDown
                    class="h-4 w-4 text-muted-foreground transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                />
            </div>
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
                    v-if="filteredOptions.length === 0 && !showCreateOption"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    No options found
                </div>
                <div
                    v-for="option in filteredOptions"
                    :key="option.value"
                    @click="selectOption(option)"
                    :class="cn(
                        'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm',
                        'outline-none hover:bg-accent hover:text-accent-foreground',
                        'focus:bg-accent focus:text-accent-foreground',
                        selectedOption?.value === option.value && 'bg-accent text-accent-foreground'
                    )"
                >
                    {{ option.label }}
                </div>
                <!-- Create new option -->
                <div
                    v-if="showCreateOption"
                    @click="createNewOption"
                    :class="cn(
                        'relative flex cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm',
                        'outline-none hover:bg-accent hover:text-accent-foreground',
                        'focus:bg-accent focus:text-accent-foreground',
                        'text-primary font-medium border-t mt-1 pt-2'
                    )"
                >
                    <Plus class="h-4 w-4" />
                    {{ createPrefix }} "{{ searchQuery.trim() }}"
                </div>
            </div>
        </div>
    </div>
</template>
