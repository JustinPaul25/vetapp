<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import type { ToastVariants } from '.'
import { toastVariants } from '.'

const props = defineProps<{
  class?: HTMLAttributes['class']
  variant?: ToastVariants['variant']
}>()
</script>

<template>
  <div
    data-slot="toast"
    :class="cn(toastVariants({ variant }), props.class)"
    role="alert"
  >
    <slot />
  </div>
</template>






