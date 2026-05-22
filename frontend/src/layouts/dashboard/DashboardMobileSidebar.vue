<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { X, TicketCheck } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import type { MenuGroup } from '@/composables/useDashboard'

defineProps<{
  menuGroups: MenuGroup[]
  sidebarBg: string
  isSidebarDark: boolean
  navItemClass: (href: string) => string
  iconBoxClass: (href: string) => string
  groupLabelClass: string
  activeBarColor: string
  userFooterClass: string
  userNameClass: string
  userRoleClass: string
  userInitials: string
  isActive: (href: string) => boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const auth = useAuthStore()
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-50 w-[280px] shadow-2xl border-r flex flex-col md:hidden transition-colors duration-300"
    :class="sidebarBg"
  >
    <!-- Header -->
    <div class="h-16 flex items-center justify-between px-5 border-b shrink-0" :class="isSidebarDark ? 'border-white/5' : 'border-slate-200'">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shrink-0">
          <TicketCheck class="h-4.5 w-4.5 text-white" />
        </div>
        <span class="text-sm font-black tracking-tight" :class="isSidebarDark ? 'text-white' : 'text-slate-800'">Pandai Antrian</span>
      </div>
      <Button variant="ghost" size="icon" @click="emit('close')" :class="isSidebarDark ? 'text-slate-400 hover:text-white hover:bg-white/10' : 'text-slate-500'">
        <X class="h-5 w-5" />
      </Button>
    </div>

    <!-- Nav -->
    <div class="flex-1 overflow-y-auto py-5 px-3 space-y-6">
      <div v-for="group in menuGroups" :key="group.title">
        <p class="px-3 mb-2 text-[10px] font-black uppercase tracking-[0.12em]" :class="groupLabelClass">{{ group.title }}</p>
        <div class="space-y-0.5">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.href"
            @click="emit('close')"
            class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all group"
            :class="navItemClass(item.href)"
          >
            <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-0.5 rounded-full" :class="activeBarColor"></span>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg shrink-0 transition-all" :class="iconBoxClass(item.href)">
              <component :is="item.icon" class="h-4 w-4" />
            </span>
            {{ item.name }}
          </router-link>
        </div>
      </div>
    </div>

    <!-- User Footer -->
    <div class="shrink-0 px-3 py-4" :class="userFooterClass">
      <div class="flex items-center gap-3 p-2.5">
        <div class="h-9 w-9 rounded-xl overflow-hidden flex items-center justify-center text-white font-black text-sm shrink-0">
          <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">{{ userInitials }}</div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="truncate text-sm font-bold" :class="userNameClass">{{ auth.user?.name ?? 'Pengguna' }}</p>
          <p class="truncate text-xs" :class="userRoleClass">{{ auth.user?.role_type ?? 'Guest' }}</p>
        </div>
      </div>
    </div>
  </aside>
</template>
