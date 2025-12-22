<script setup lang="ts">
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';

interface Props {
    password: string;
}

const props = defineProps<Props>();

const requirements = computed(() => {
    const password = props.password || '';
    
    return [
        {
            label: 'At least 8 characters',
            met: password.length >= 8,
        },
        {
            label: 'Contains at least one letter',
            met: /[a-zA-Z]/.test(password),
        },
        {
            label: 'Contains at least one number',
            met: /\d/.test(password),
        },
        {
            label: 'Contains uppercase and lowercase letters',
            met: /[a-z]/.test(password) && /[A-Z]/.test(password),
        },
    ];
});

const allMet = computed(() => {
    return requirements.value.every(req => req.met);
});
</script>

<template>
    <div class="mt-2 space-y-1.5">
        <p class="text-xs font-medium text-muted-foreground mb-2">
            Password requirements:
        </p>
        <div class="space-y-1">
            <div
                v-for="(requirement, index) in requirements"
                :key="index"
                class="flex items-center gap-2 text-xs"
            >
                <component
                    :is="requirement.met ? Check : X"
                    :class="[
                        'h-3.5 w-3.5 flex-shrink-0',
                        requirement.met
                            ? 'text-green-600 dark:text-green-500'
                            : 'text-muted-foreground',
                    ]"
                />
                <span
                    :class="[
                        requirement.met
                            ? 'text-green-600 dark:text-green-500'
                            : 'text-muted-foreground',
                    ]"
                >
                    {{ requirement.label }}
                </span>
            </div>
        </div>
    </div>
</template>












