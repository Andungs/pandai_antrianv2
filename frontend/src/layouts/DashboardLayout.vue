<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useDashboard } from '@/composables/useDashboard'
import DashboardSidebar from './dashboard/DashboardSidebar.vue'
import DashboardMobileSidebar from './dashboard/DashboardMobileSidebar.vue'
import DashboardTopbar from './dashboard/DashboardTopbar.vue'

const {
  // State
  auth,
  themeMode, applyTheme,
  isSidebarOpen, toggleSidebar,
  isCollapsed, toggleCollapse,
  // Data
  menuGroups,
  userInitials,
  // Functions
  logout, getCurrentPageTitle, isActive,
  // Theme tokens
  sidebarBg, isSidebarDark,
  navItemClass, iconBoxClass,
  groupLabelClass, activeBarColor, activeChevronClass,
  userFooterClass, userNameClass, userRoleClass, logoutBtnClass,
} = useDashboard()

onMounted(() => auth.fetchMe())
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-[#080c14] flex w-full font-sans text-slate-900 dark:text-slate-100">

    <!-- ══ SIDEBAR DESKTOP ══════════════════════════════════════════════════════ -->
    <DashboardSidebar
      :menu-groups="menuGroups"
      :is-collapsed="isCollapsed"
      :is-sidebar-dark="isSidebarDark"
      :sidebar-bg="sidebarBg"
      :nav-item-class="navItemClass"
      :icon-box-class="iconBoxClass"
      :group-label-class="groupLabelClass"
      :active-bar-color="activeBarColor"
      :active-chevron-class="activeChevronClass"
      :user-footer-class="userFooterClass"
      :user-name-class="userNameClass"
      :user-role-class="userRoleClass"
      :logout-btn-class="logoutBtnClass"
      :user-initials="userInitials"
      :is-active="isActive"
      @toggle-collapse="toggleCollapse"
      @logout="logout"
    />

    <!-- ══ MOBILE OVERLAY ════════════════════════════════════════════════════════ -->
    <Transition name="fade">
      <div v-if="isSidebarOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden" @click="toggleSidebar"></div>
    </Transition>

    <!-- ══ MOBILE SIDEBAR ════════════════════════════════════════════════════════ -->
    <Transition name="slide">
      <DashboardMobileSidebar
        v-if="isSidebarOpen"
        :menu-groups="menuGroups"
        :sidebar-bg="sidebarBg"
        :is-sidebar-dark="isSidebarDark"
        :nav-item-class="navItemClass"
        :icon-box-class="iconBoxClass"
        :group-label-class="groupLabelClass"
        :active-bar-color="activeBarColor"
        :user-footer-class="userFooterClass"
        :user-name-class="userNameClass"
        :user-role-class="userRoleClass"
        :user-initials="userInitials"
        :is-active="isActive"
        @close="toggleSidebar"
      />
    </Transition>

    <!-- ══ MAIN CONTENT ═══════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col min-w-0 w-full relative">

      <!-- Topbar -->
      <DashboardTopbar
        :theme-mode="themeMode"
        :user-initials="userInitials"
        :page-title="getCurrentPageTitle()"
        @toggle-mobile-sidebar="toggleSidebar"
        @apply-theme="applyTheme"
        @logout="logout"
      />

      <!-- Page Content -->
      <main class="flex-1 w-full p-4 sm:p-6 lg:p-8 overflow-x-hidden">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
.slide-enter-from,
.slide-leave-to { transform: translateX(-100%); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
