<script setup lang="ts">
import { TicketCheck, Sun, Moon, SunMoon } from 'lucide-vue-next'
import { useTheme, type ThemeMode } from '@/composables/useTheme'

const { themeMode, setTheme } = useTheme()

const themes: { key: ThemeMode; icon: any }[] = [
  { key: 'light', icon: Sun },
  { key: 'semi-dark', icon: SunMoon },
  { key: 'dark', icon: Moon },
]
</script>

<template>
  <div class="min-h-screen flex font-sans">
    <!-- Left panel — decorative -->
    <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-sky-500 via-blue-600 to-indigo-700 items-center justify-center overflow-hidden">
      <!-- Decorative elements -->
      <div class="absolute inset-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-white/5 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-sky-300/10 rounded-full blur-[80px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:48px_48px]"></div>
      </div>
      <div class="relative z-10 text-center px-12">
        <div class="h-20 w-20 mx-auto rounded-3xl bg-white/10 backdrop-blur-sm flex items-center justify-center mb-8 shadow-2xl shadow-black/10 border border-white/20">
          <TicketCheck class="h-10 w-10 text-white" />
        </div>
        <h2 class="text-4xl font-black text-white mb-4 tracking-tight">Pandai Antrian</h2>
        <p class="text-lg text-white/70 font-medium max-w-md">Sistem manajemen antrean digital yang cerdas dan efisien</p>
        
        <div class="mt-12 grid grid-cols-3 gap-4 max-w-sm mx-auto">
          <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/10">
            <p class="text-2xl font-black text-white">3+</p>
            <p class="text-xs text-white/60 font-medium mt-1">Layanan</p>
          </div>
          <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/10">
            <p class="text-2xl font-black text-white">∞</p>
            <p class="text-xs text-white/60 font-medium mt-1">Loket</p>
          </div>
          <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/10">
            <p class="text-2xl font-black text-white">24/7</p>
            <p class="text-xs text-white/60 font-medium mt-1">Realtime</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right panel — auth form -->
    <div class="flex-1 flex items-center justify-center bg-white dark:bg-[#080c14] relative p-4">
      <!-- Theme toggle (top right) -->
      <div class="absolute top-4 right-4 flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800">
        <button
          v-for="t in themes"
          :key="t.key"
          @click="setTheme(t.key)"
          class="p-2 rounded-lg transition-all"
          :class="themeMode === t.key ? 'bg-white dark:bg-slate-700 shadow-sm text-sky-600 dark:text-sky-400' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
        >
          <component :is="t.icon" class="h-4 w-4" />
        </button>
      </div>

      <router-view />
    </div>
  </div>
</template>
