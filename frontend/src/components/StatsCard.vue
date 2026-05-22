<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  value: string | number
  subtitle?: string
  /** Icon component from lucide-vue-next */
  icon?: any
  /** 'primary' | 'amber' | 'red' | 'emerald' */
  variant?: 'primary' | 'amber' | 'red' | 'emerald'
  /** Whether to pulse/animate the icon area */
  pulse?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  pulse: false,
  loading: false,
})

const variantConfig = computed(() => {
  const configs = {
    primary: {
      iconBg: 'bg-blue-100 dark:bg-blue-900/30',
      iconColor: 'text-blue-600 dark:text-blue-400',
      glow: 'shadow-blue-500/10',
      gradient: 'from-blue-50/80 to-white dark:from-blue-950/20 dark:to-slate-950',
      border: 'border-blue-100 dark:border-blue-900/30 hover:border-blue-300/60 dark:hover:border-blue-700/40',
      accent: 'bg-blue-500',
      valueColor: 'text-slate-800 dark:text-white',
    },
    amber: {
      iconBg: 'bg-amber-100 dark:bg-amber-900/30',
      iconColor: 'text-amber-600 dark:text-amber-400',
      glow: 'shadow-amber-500/10',
      gradient: 'from-amber-50/80 to-white dark:from-amber-950/20 dark:to-slate-950',
      border: 'border-amber-100 dark:border-amber-900/30 hover:border-amber-300/60 dark:hover:border-amber-700/40',
      accent: 'bg-amber-500',
      valueColor: 'text-amber-600 dark:text-amber-400',
    },
    red: {
      iconBg: 'bg-red-100 dark:bg-red-900/30',
      iconColor: 'text-red-600 dark:text-red-400',
      glow: 'shadow-red-500/10',
      gradient: 'from-red-50/80 to-white dark:from-red-950/20 dark:to-slate-950',
      border: 'border-red-100 dark:border-red-900/30 hover:border-red-300/60 dark:hover:border-red-700/40',
      accent: 'bg-red-500',
      valueColor: 'text-red-600 dark:text-red-400',
    },
    emerald: {
      iconBg: 'bg-emerald-100 dark:bg-emerald-900/30',
      iconColor: 'text-emerald-600 dark:text-emerald-400',
      glow: 'shadow-emerald-500/10',
      gradient: 'from-emerald-50/80 to-white dark:from-emerald-950/20 dark:to-slate-950',
      border: 'border-emerald-100 dark:border-emerald-900/30 hover:border-emerald-300/60 dark:hover:border-emerald-700/40',
      accent: 'bg-emerald-500',
      valueColor: 'text-emerald-600 dark:text-emerald-400',
    },
  }
  return configs[props.variant]
})
</script>

<template>
  <!-- Skeleton -->
  <div
    v-if="loading"
    class="relative rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 sm:p-6 overflow-hidden"
  >
    <div class="flex items-start justify-between mb-3 sm:mb-5">
      <div class="h-2.5 w-16 sm:w-24 bg-slate-200 dark:bg-slate-800 rounded-full animate-pulse"></div>
      <div class="h-9 w-9 sm:h-12 sm:w-12 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
    </div>
    <div class="h-7 sm:h-9 w-14 sm:w-16 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse mb-2"></div>
    <div class="h-2.5 w-24 sm:w-32 bg-slate-100 dark:bg-slate-800/60 rounded-full animate-pulse"></div>
  </div>

  <!-- Actual Card -->
  <div
    v-else
    :class="[variantConfig.border, variantConfig.glow]"
    class="group relative rounded-2xl sm:rounded-3xl border bg-white dark:bg-slate-950 shadow-md sm:shadow-lg hover:shadow-xl sm:hover:shadow-2xl transition-all duration-300 overflow-hidden hover:-translate-y-0.5 cursor-default"
  >
    <!-- Gradient background fill -->
    <div :class="`bg-gradient-to-br ${variantConfig.gradient}`" class="absolute inset-0 opacity-70 pointer-events-none"></div>
    
    <!-- Top accent bar -->
    <div :class="variantConfig.accent" class="absolute top-0 left-0 right-0 h-0.5 opacity-60 group-hover:opacity-100 transition-opacity"></div>

    <!-- Decorative circle glow -->
    <div :class="variantConfig.iconBg" class="absolute -right-6 -top-6 h-24 w-24 rounded-full opacity-40 blur-2xl pointer-events-none"></div>

    <div class="relative z-10 p-4 sm:p-6">
      <!-- Header: Title + Icon -->
      <div class="flex items-start justify-between mb-3 sm:mb-5">
        <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 leading-tight pr-2">
          {{ title }}
        </p>
        <div
          v-if="icon"
          :class="[variantConfig.iconBg, variantConfig.iconColor]"
          class="h-9 w-9 sm:h-12 sm:w-12 rounded-xl sm:rounded-2xl flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform duration-300 relative shrink-0"
        >
          <component :is="icon" class="h-4 w-4 sm:h-5 sm:w-5" />
          <!-- Pulse ring for active alerts -->
          <span
            v-if="pulse"
            :class="variantConfig.accent"
            class="absolute top-0 right-0 h-2.5 w-2.5 sm:h-3 sm:w-3 rounded-full border-2 border-white dark:border-slate-950 animate-pulse"
          ></span>
        </div>
      </div>

      <!-- Value -->
      <div :class="variantConfig.valueColor" class="text-2xl sm:text-4xl font-black tracking-tight leading-none mb-1 sm:mb-1.5">
        {{ value }}
      </div>

      <!-- Subtitle / status line -->
      <p v-if="subtitle" class="text-[10px] sm:text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1 sm:gap-1.5 mt-1">
        <slot name="subtitle-icon" />
        {{ subtitle }}
      </p>
      <slot v-else name="subtitle" />
    </div>
  </div>
</template>
