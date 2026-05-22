import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export { default as Button } from './Button.vue'

export const buttonVariants = cva(
  // Base styles — shared by ALL variants
  [
    'inline-flex items-center justify-center gap-1.5 whitespace-nowrap select-none',
    'font-semibold tracking-wide rounded-xl border border-transparent',
    'transition-all duration-200 ease-out outline-none',
    'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:border-ring',
    'active:scale-[0.97]',
    'disabled:pointer-events-none disabled:opacity-50',
    '[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
  ].join(' '),
  {
    variants: {
      variant: {
        // ── Primary (Blue) ────────────────────────────────────────────────
        default:
          'bg-primary text-primary-foreground shadow-md shadow-primary/20 hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 hover:-translate-y-px',

        // ── Secondary (Slate) ─────────────────────────────────────────────
        secondary:
          'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200 shadow-sm hover:bg-slate-200 dark:hover:bg-slate-700 hover:-translate-y-px',

        // ── Outline ───────────────────────────────────────────────────────
        outline:
          'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-800 hover:-translate-y-px',

        // ── Ghost ─────────────────────────────────────────────────────────
        ghost:
          'bg-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100',

        // ── Link ──────────────────────────────────────────────────────────
        link:
          'bg-transparent text-primary underline-offset-4 hover:underline p-0 h-auto shadow-none',

        // ── Success (Emerald) ─────────────────────────────────────────────
        success:
          'bg-emerald-600 text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700 hover:shadow-lg hover:shadow-emerald-500/25 hover:-translate-y-px',

        // ── Warning (Amber) ───────────────────────────────────────────────
        warning:
          'bg-amber-500 text-white shadow-md shadow-amber-400/20 hover:bg-amber-600 hover:shadow-lg hover:shadow-amber-400/25 hover:-translate-y-px',

        // ── Danger / Destructive (Red) ────────────────────────────────────
        destructive:
          'bg-red-600 text-white shadow-md shadow-red-500/20 hover:bg-red-700 hover:shadow-lg hover:shadow-red-500/25 hover:-translate-y-px',

        // ── Info (Blue-light / Indigo) ────────────────────────────────────
        info:
          'bg-blue-600 text-white shadow-md shadow-blue-500/20 hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-px',

        // ── Dark ──────────────────────────────────────────────────────────
        dark:
          'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-md shadow-slate-900/20 hover:bg-slate-800 dark:hover:bg-slate-100 hover:-translate-y-px',

        // ── Soft variants (light backgrounds, colored text) ───────────────
        'soft-primary':
          'bg-primary/10 text-primary dark:bg-primary/20 hover:bg-primary/20 dark:hover:bg-primary/30 hover:-translate-y-px',
        'soft-success':
          'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 hover:-translate-y-px',
        'soft-warning':
          'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50 hover:-translate-y-px',
        'soft-destructive':
          'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 hover:-translate-y-px',
        'soft-info':
          'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 hover:-translate-y-px',
      },
      size: {
        // Standard sizes
        xs:      'h-6 px-2 text-xs rounded-lg gap-1 [&_svg:not([class*=size-])]:size-3',
        sm:      'h-8 px-3 text-sm rounded-xl gap-1.5 [&_svg:not([class*=size-])]:size-3.5',
        default: 'h-10 px-4 text-sm rounded-xl gap-2',
        lg:      'h-12 px-6 text-base rounded-2xl gap-2.5 [&_svg:not([class*=size-])]:size-5',
        xl:      'h-14 px-8 text-lg rounded-2xl gap-3 [&_svg:not([class*=size-])]:size-6',

        // Icon-only
        'icon-xs':  'size-6 rounded-lg p-0 [&_svg:not([class*=size-])]:size-3',
        'icon-sm':  'size-8 rounded-xl p-0 [&_svg:not([class*=size-])]:size-4',
        'icon':     'size-10 rounded-xl p-0',
        'icon-lg':  'size-12 rounded-2xl p-0 [&_svg:not([class*=size-])]:size-5',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
