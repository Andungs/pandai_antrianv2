<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { TicketCheck } from 'lucide-vue-next'
import {
  ChevronRight, PanelLeftClose, PanelLeftOpen, LogOut
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import type { MenuGroup } from '@/composables/useDashboard'

const props = defineProps<{
  menuGroups: MenuGroup[]
  isCollapsed: boolean
  isSidebarDark: boolean
  sidebarBg: string
  navItemClass: (href: string) => string
  iconBoxClass: (href: string) => string
  groupLabelClass: string
  activeBarColor: string
  activeChevronClass: string
  userFooterClass: string
  userNameClass: string
  userRoleClass: string
  logoutBtnClass: string
  userInitials: string
  isActive: (href: string) => boolean
}>()

const emit = defineEmits<{
  (e: 'toggle-collapse'): void
  (e: 'logout'): void
}>()

const router = useRouter()
const auth   = useAuthStore()
</script>

<template>
  <aside
    class="hidden md:flex flex-col h-screen sticky top-0 z-40 shadow-lg border-r transition-all duration-300 ease-in-out"
    :class="[sidebarBg, isCollapsed ? 'w-[68px]' : 'w-[260px]']"
  >
    <!-- Logo + Toggle Button -->
    <div
      class="h-16 flex items-center shrink-0 border-b transition-colors duration-300 overflow-hidden"
      :class="[isSidebarDark ? 'border-white/5' : 'border-slate-200', isCollapsed ? 'px-3 justify-center' : 'px-4 justify-between']"
    >
      <!-- Logo: hidden when collapsed -->
      <div
        v-if="!isCollapsed"
        class="flex items-center gap-2 cursor-pointer flex-1 min-w-0"
        @click="router.push('/')"
      >
        <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shrink-0">
          <TicketCheck class="h-4.5 w-4.5 text-white" />
        </div>
        <span class="text-sm font-black tracking-tight" :class="isSidebarDark ? 'text-white' : 'text-slate-800'">Pandai Antrian</span>
      </div>

      <!-- Toggle Button -->
      <button
        @click="emit('toggle-collapse')"
        class="flex items-center justify-center h-8 w-8 rounded-lg shrink-0 transition-all duration-200"
        :class="isSidebarDark
          ? 'text-slate-400 hover:text-white hover:bg-white/10'
          : 'text-slate-400 hover:text-slate-700 hover:bg-slate-100'"
        :title="isCollapsed ? 'Buka Sidebar' : 'Tutup Sidebar'"
      >
        <PanelLeftClose v-if="!isCollapsed" class="h-6 w-6" />
        <PanelLeftOpen  v-else              class="h-6 w-6" />
      </button>
    </div>

    <!-- Nav -->
    <div class="flex-1 overflow-y-auto py-5 scrollbar-thin" :class="isCollapsed ? 'px-2 space-y-1' : 'px-3 space-y-6'">
      <div v-for="group in menuGroups" :key="group.title">
        <!-- Group label: hidden when collapsed -->
        <p
          v-if="!isCollapsed"
          class="px-3 mb-2 text-[10px] font-black uppercase tracking-[0.12em]"
          :class="groupLabelClass"
        >
          {{ group.title }}
        </p>
        <!-- Divider when collapsed -->
        <div v-else class="mb-1 mt-2 h-px mx-1" :class="isSidebarDark ? 'bg-white/5' : 'bg-slate-200'"></div>

        <div class="space-y-0.5">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.href"
            class="relative flex items-center rounded-xl text-sm font-semibold transition-all duration-200 group"
            :class="[navItemClass(item.href), isCollapsed ? 'px-2 py-2 justify-center' : 'gap-3 px-3 py-2.5']"
            :title="isCollapsed ? item.name : ''"
          >
            <span
              v-if="isActive(item.href)"
              class="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-0.5 rounded-full"
              :class="activeBarColor"
            ></span>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg shrink-0 transition-all duration-200" :class="iconBoxClass(item.href)">
              <component :is="item.icon" class="h-4 w-4" />
            </span>
            <span v-if="!isCollapsed" class="flex-1 leading-none">{{ item.name }}</span>
            <ChevronRight v-if="isActive(item.href) && !isCollapsed" class="h-3.5 w-3.5 shrink-0" :class="activeChevronClass" />
          </router-link>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
</style>
