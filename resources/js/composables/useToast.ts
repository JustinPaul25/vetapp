import { ref, type Ref } from 'vue'

export interface Toast {
  id: string
  title?: string
  description?: string
  variant?: 'default' | 'destructive' | 'success'
  duration?: number
}

// Singleton state
const toasts: Ref<Toast[]> = ref([])
let toastIdCounter = 0

function generateToastId(): string {
  return `toast-${Date.now()}-${toastIdCounter++}`
}

function removeToast(id: string) {
  const index = toasts.value.findIndex((t) => t.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

export function useToast() {
  const toast = (options: Omit<Toast, 'id'>) => {
    const id = generateToastId()
    const toastItem: Toast = {
      id,
      duration: 5000,
      ...options,
    }

    toasts.value.push(toastItem)

    // Auto remove after duration
    if (toastItem.duration && toastItem.duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, toastItem.duration)
    }

    return id
  }

  const success = (title: string, description?: string) => {
    return toast({
      title,
      description,
      variant: 'success',
    })
  }

  const error = (title: string, description?: string) => {
    return toast({
      title,
      description,
      variant: 'destructive',
    })
  }

  const info = (title: string, description?: string) => {
    return toast({
      title,
      description,
      variant: 'default',
    })
  }

  const removeAllToasts = () => {
    toasts.value = []
  }

  return {
    toasts,
    toast,
    success,
    error,
    info,
    removeToast,
    removeAllToasts,
  }
}

