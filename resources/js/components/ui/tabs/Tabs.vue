<script setup lang="ts">
import { ref, provide, watch } from 'vue';

interface Props {
    modelValue: string;
    class?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const activeTab = ref(props.modelValue);

watch(() => props.modelValue, (newValue) => {
    activeTab.value = newValue;
});

watch(activeTab, (newValue) => {
    emit('update:modelValue', newValue);
});

provide('activeTab', activeTab);
</script>

<template>
    <div :class="['tabs', props.class]">
        <slot />
    </div>
</template>

