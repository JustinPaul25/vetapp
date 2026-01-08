<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-vue-next';

interface Props {
    modelValue: string;
    options: string[];
    placeholder?: string;
    class?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Type or select...',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const inputValue = ref(props.modelValue);
const containerRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

const filteredOptions = computed(() => {
    if (!inputValue.value) {
        return props.options;
    }
    const query = inputValue.value.toLowerCase();
    return props.options.filter(option =>
        option.toLowerCase().includes(query)
    );
});

const handleInput = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    inputValue.value = value;
    emit('update:modelValue', value);
    isOpen.value = true;
};

const handleFocus = () => {
    if (!props.disabled) {
        isOpen.value = true;
    }
};

const selectOption = (option: string) => {
    inputValue.value = option;
    emit('update:modelValue', option);
    isOpen.value = false;
    inputRef.value?.blur();
};

const handleClickOutside = (event: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        inputRef.value?.focus();
    }
};

// Sync inputValue with modelValue when it changes externally
watch(() => props.modelValue, (newValue) => {
    inputValue.value = newValue;
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative" :class="props.class">
        <div class="relative">
            <Input
                ref="inputRef"
                :model-value="inputValue"
                type="text"
                :placeholder="placeholder"
                :disabled="disabled"
                @input="handleInput"
                @focus="handleFocus"
                class="pr-8"
            />
            <button
                type="button"
                @click="toggleDropdown"
                :disabled="disabled"
                class="absolute right-0 top-0 h-full px-2 flex items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <ChevronDown
                    class="h-4 w-4 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                />
            </button>
        </div>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen && props.options.length > 0"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md max-h-[200px] overflow-auto"
        >
            <div
                v-for="(option, index) in filteredOptions"
                :key="index"
                @click="selectOption(option)"
                :class="cn(
                    'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm',
                    'outline-none hover:bg-accent hover:text-accent-foreground',
                    'focus:bg-accent focus:text-accent-foreground',
                    inputValue === option && 'bg-accent text-accent-foreground'
                )"
            >
                {{ option }}
            </div>
        </div>
    </div>
</template>

