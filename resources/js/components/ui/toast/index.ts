import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export { default as Toast } from './Toast.vue'
export { default as ToastTitle } from './ToastTitle.vue'
export { default as ToastDescription } from './ToastDescription.vue'
export { default as ToastClose } from './ToastClose.vue'
export { default as Toaster } from './Toaster.vue'

export const toastVariants = cva(
  'group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all',
  {
    variants: {
      variant: {
        default: 'border bg-background text-foreground',
        destructive:
          'border-destructive bg-destructive text-destructive-foreground',
        success:
          'border-green-500 bg-green-50 text-green-900 dark:bg-green-900/20 dark:text-green-100 dark:border-green-500',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)

export type ToastVariants = VariantProps<typeof toastVariants>

