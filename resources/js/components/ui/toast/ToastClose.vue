<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { X } from 'lucide-vue-next'

const props = defineProps<{
  class?: HTMLAttributes['class']
  onClick?: () => void
}>()

const emits = defineEmits<{
  (e: 'close'): void
}>()

const handleClick = () => {
  emits('close')
  props.onClick?.()
}
</script>

<template>
  <Button
    variant="ghost"
    size="sm"
    :class="cn(
      'absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100',
      props.class
    )"
    @click="handleClick"
  >
    <X class="h-4 w-4" />
  </Button>
</template>





