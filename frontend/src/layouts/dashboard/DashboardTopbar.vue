<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu'
import {
  Sun, Moon, SunMoon, Bell, Menu, Settings, LogOut,
  ChevronRight, Check
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { type ThemeMode } from '@/composables/useTheme'

const props = defineProps<{
  themeMode: ThemeMode
  userInitials: string
  pageTitle: string
}>()

const emit = defineEmits<{
  (e: 'toggle-mobile-sidebar'): void
  (e: 'apply-theme', mode: ThemeMode): void
  (e: 'logout'): void
}>()

const router = useRouter()
const auth   = useAuthStore()

const themeModes = [
  { key: 'light' as ThemeMode,     label: 'Light',     icon: Sun,     desc: 'Terang penuh' },
  { key: 'semi-dark' as ThemeMode, label: 'Semi Dark', icon: SunMoon, desc: 'Sidebar gelap' },
  { key: 'dark' as ThemeMode,      label: 'Dark',      icon: Moon,    desc: 'Gelap penuh'  },
]
</script>

<template>
  <header class="h-16 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 border-b border-slate-200 dark:border-white/5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl shadow-sm">
    <div class="flex items-center gap-3">
      <Button variant="ghost" size="icon" class="md:hidden" @click="emit('toggle-mobile-sidebar')">
        <Menu class="h-5 w-5" />
      </Button>
      <div class="flex items-center gap-2">
        <span class="text-xs text-slate-400 dark:text-slate-600 hidden sm:block">Pandai Antrian</span>
        <ChevronRight class="h-3 w-3 text-slate-300 dark:text-slate-700 hidden sm:block" />
        <h2 class="text-sm font-black tracking-tight text-slate-800 dark:text-slate-200 hidden sm:block">{{ pageTitle }}</h2>
      </div>
    </div>

    <div class="flex items-center gap-1 sm:gap-1.5">

      <!-- ── Theme Mode Selector ────────────────────────────────────────── -->
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button
            class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold transition-all shadow-sm"
            title="Ubah Tema"
          >
            <component
              :is="themeMode === 'light' ? Sun : themeMode === 'dark' ? Moon : SunMoon"
              class="h-4 w-4"
            />
            <span class="hidden sm:block">
              {{ themeModes.find(m => m.key === themeMode)?.label }}
            </span>
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48 rounded-2xl p-1.5 shadow-xl border border-slate-200 dark:border-slate-800">
          <p class="px-3 pt-2 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Tema Tampilan</p>
          <button
            v-for="mode in themeModes"
            :key="mode.key"
            @click="emit('apply-theme', mode.key)"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all text-left"
            :class="themeMode === mode.key
              ? 'bg-primary/10 text-primary dark:bg-primary/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'"
          >
            <span class="flex h-7 w-7 items-center justify-center rounded-lg shrink-0"
              :class="themeMode === mode.key ? 'bg-primary/15 text-primary' : 'bg-slate-100 dark:bg-slate-800 text-slate-400'"
            >
              <component :is="mode.icon" class="h-3.5 w-3.5" />
            </span>
            <div class="flex-1">
              <p class="leading-none">{{ mode.label }}</p>
              <p class="text-[10px] opacity-60 mt-0.5">{{ mode.desc }}</p>
            </div>
            <Check v-if="themeMode === mode.key" class="h-3.5 w-3.5 text-primary shrink-0" />
          </button>
        </DropdownMenuContent>
      </DropdownMenu>

      <!-- Notifications -->
      <button class="relative w-9 h-9 flex items-center justify-center rounded-xl hover:bg-slate-100 dark:hover:bg-white/10 transition-colors text-slate-500 dark:text-slate-400">
        <Bell class="h-4.5 w-4.5" />
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-900"></span>
      </button>

      <div class="h-6 w-px bg-slate-200 dark:bg-white/10 mx-0.5"></div>

      <!-- User Dropdown -->
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-xl hover:bg-slate-100 dark:hover:bg-white/10 transition-all">
            <div class="w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center text-white font-black text-xs shadow-md shadow-blue-500/20">
              <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                {{ userInitials }}
              </div>
            </div>
            <div class="hidden sm:block text-left">
              <p class="text-xs font-bold text-slate-700 dark:text-slate-200 leading-none">{{ auth.user?.name?.split(' ')[0] ?? 'User' }}</p>
              <p class="text-[10px] text-slate-400 leading-none mt-0.5">{{ auth.user?.role_type ?? 'Guest' }}</p>
            </div>
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 rounded-2xl p-2 shadow-xl border border-slate-200 dark:border-slate-800">
          <DropdownMenuLabel class="font-normal px-2 pb-3">
            <div class="flex flex-col space-y-1">
              <p class="text-sm font-black leading-none">{{ auth.user?.name }}</p>
              <p class="text-xs leading-none text-slate-400">@{{ auth.user?.username }}</p>
              <p class="text-xs text-blue-500 font-bold mt-1">{{ auth.user?.role_type }}</p>
            </div>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem @click="router.push('/dashboard/profile')" class="cursor-pointer rounded-xl gap-2 p-2.5">
            <Settings class="h-4 w-4 text-slate-400" />
            <span>Pengaturan Profil</span>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem @click="emit('logout')" class="cursor-pointer rounded-xl gap-2 p-2.5 text-red-500 focus:bg-red-50 dark:focus:bg-red-900/20 focus:text-red-600">
            <LogOut class="h-4 w-4" />
            <span>Keluar</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
