<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    modelValue?: string;
    id?: string;
    name?: string;
    placeholder?: string;
    required?: boolean;
    autocomplete?: string;
    autofocus?: boolean;
    tabindex?: number;
    readonly?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: '',
    required: false,
    autofocus: false,
    readonly: false,
    class: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const showPassword = ref(false);

const inputValue = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :type="showPassword ? 'text' : 'password'"
            :name="name"
            v-model="inputValue"
            :placeholder="placeholder"
            :required="required"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :tabindex="tabindex"
            :readonly="readonly"
            :class="['pr-10', props.class]"
        />
        <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
            tabindex="-1"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
        >
            <Eye v-if="!showPassword" class="h-4 w-4" />
            <EyeOff v-else class="h-4 w-4" />
        </button>
    </div>
</template>
