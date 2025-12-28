<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { watch, ref, onMounted, nextTick } from 'vue'
import { useToast } from '@/composables/useToast'

const page = usePage()
const { success, error } = useToast()
const lastFlashId = ref<string>('')

function handleFlashMessages() {
  const flash = (page.props as any).flash
  if (!flash) return

  // Create a unique ID for this flash message to avoid duplicates
  const flashId = `${flash.success || ''}-${flash.error || ''}-${flash.message || ''}`
  
  // Skip if empty or same as last shown
  if (!flashId || flashId === lastFlashId.value) return
  
  lastFlashId.value = flashId

  if (flash.success) {
    success(flash.success)
  }

  if (flash.error) {
    error(flash.error)
  }

  if (flash.message) {
    // Treat generic 'message' as success
    success(flash.message)
  }
}

// Check on mount
onMounted(() => {
  nextTick(() => {
    handleFlashMessages()
  })
})

// Watch for page props changes (including flash messages)
watch(
  () => (page.props as any).flash,
  () => {
    nextTick(() => {
      handleFlashMessages()
    })
  },
  { deep: true, immediate: true }
)

// Watch for page URL changes to reset the lastFlashId
watch(
  () => page.url,
  () => {
    lastFlashId.value = ''
  }
)
</script>

<template>
  <!-- This component doesn't render anything, it just watches for flash messages -->
</template>

