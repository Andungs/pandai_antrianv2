import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export { default as Badge } from './Badge.vue'

export const badgeVariants = cva(
  'inline-flex w-fit shrink-0 items-center justify-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold tracking-wide transition-all whitespace-nowrap overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 [&>svg]:size-3 [&>svg]:pointer-events-none',
  {
    variants: {
      variant: {
        default:
          'border-transparent bg-primary text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/90',
        secondary:
          'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 shadow-sm hover:bg-slate-200 dark:hover:bg-slate-700',
        destructive:
          'border-transparent bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 shadow-sm shadow-red-500/10 hover:bg-red-200 dark:hover:bg-red-900/50',
        outline:
          'border-current bg-transparent text-foreground hover:bg-muted',
        success:
          'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 shadow-sm shadow-emerald-500/10',
        warning:
          'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 shadow-sm shadow-amber-500/10',
        info:
          'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 shadow-sm shadow-blue-500/10',
        ghost:
          'border-transparent bg-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
