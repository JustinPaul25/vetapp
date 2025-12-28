<script setup lang="ts">
import { Toast, ToastTitle, ToastDescription, ToastClose } from '.'
import { useToast } from '@/composables/useToast'
import { CheckCircle2, XCircle, Info } from 'lucide-vue-next'

const { toasts, removeToast } = useToast()

const getVariantIcon = (variant?: string) => {
  switch (variant) {
    case 'success':
      return CheckCircle2
    case 'destructive':
      return XCircle
    default:
      return Info
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]"
    >
      <TransitionGroup
        name="toast"
        tag="div"
        class="flex flex-col gap-2"
      >
        <Toast
          v-for="toast in toasts"
          :key="toast.id"
          :variant="toast.variant"
          class="pointer-events-auto group relative"
        >
          <div class="flex items-start gap-3">
            <component
              :is="getVariantIcon(toast.variant)"
              class="h-5 w-5 mt-0.5 flex-shrink-0"
            />
            <div class="flex-1 space-y-1">
              <ToastTitle v-if="toast.title">
                {{ toast.title }}
              </ToastTitle>
              <ToastDescription v-if="toast.description">
                {{ toast.description }}
              </ToastDescription>
            </div>
          </div>
          <ToastClose @close="removeToast(toast.id)" />
        </Toast>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>

