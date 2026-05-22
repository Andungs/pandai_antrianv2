import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard, Settings, Users, Monitor,
  TicketCheck, History, Layers
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTheme, type ThemeMode } from '@/composables/useTheme'

// ── Menu Definition ──────────────────────────────────────────────────────────
export interface MenuItem {
  name: string
  href: string
  icon: any
  roles: string[]  // role_type yang bisa melihat menu ini
}

export interface MenuGroup {
  title: string
  items: MenuItem[]
}

const allMenuGroups: MenuGroup[] = [
  {
    title: 'Superadmin',
    items: [
      { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, roles: ['superadmin'] },
      { name: 'Kelola Layanan', href: '/dashboard/services', icon: Layers, roles: ['superadmin'] },
      { name: 'Kelola Loket', href: '/dashboard/counters', icon: Monitor, roles: ['superadmin'] },
      { name: 'Manajemen User', href: '/dashboard/users', icon: Users, roles: ['superadmin'] },
      { name: 'Pengaturan', href: '/dashboard/settings', icon: Settings, roles: ['superadmin'] },
    ]
  },
  {
    title: 'Loket',
    items: [
      { name: 'Dashboard Panggilan', href: '/dashboard/loket', icon: TicketCheck, roles: ['loket', 'superadmin'] },
      { name: 'Riwayat Antrean', href: '/dashboard/loket/history', icon: History, roles: ['loket', 'superadmin'] },
    ]
  },
]

// ── Composable ───────────────────────────────────────────────────────────────
export function useDashboard() {
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const { themeMode, setTheme: applyTheme } = useTheme()

  // ── Sidebar state ───────────────────────────────────────────────────────
  const isSidebarOpen = ref(false)
  const toggleSidebar = () => { isSidebarOpen.value = !isSidebarOpen.value }

  const isCollapsed = ref(localStorage.getItem('sidebarCollapsed') === 'true')
  const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value
    localStorage.setItem('sidebarCollapsed', String(isCollapsed.value))
  }

  // ── Auth actions ────────────────────────────────────────────────────────
  const logout = async () => {
    await auth.logout()
    router.push('/login')
  }

  // ── Menu (filtered by role) ─────────────────────────────────────────────
  const menuGroups = computed(() =>
    allMenuGroups
      .map(group => ({
        ...group,
        items: group.items.filter(item =>
          auth.user ? item.roles.includes(auth.user.role_type) : false
        )
      }))
      .filter(group => group.items.length > 0)
  )

  // ── Derived data ────────────────────────────────────────────────────────
  const userInitials = computed(() => {
    const name = auth.user?.name ?? 'U'
    return name.split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase()
  })

  const getCurrentPageTitle = () => {
    for (const group of allMenuGroups) {
      const found = group.items.find(item => item.href === route.path)
      if (found) return found.name
    }
    return 'Dashboard'
  }

  const isActive = (href: string) => route.path === href

  // ── Sidebar theme tokens ────────────────────────────────────────────────
  const sidebarBg = computed(() => {
    if (themeMode.value === 'light') return 'bg-white border-slate-200'
    if (themeMode.value === 'semi-dark') return 'bg-slate-900 border-white/5'
    return 'bg-[#0d1117] border-white/5'
  })

  const isSidebarDark = computed(() =>
    themeMode.value === 'semi-dark' || themeMode.value === 'dark'
  )

  const navItemClass = (href: string) => {
    const active = isActive(href)
    if (isSidebarDark.value) {
      return active
        ? 'bg-blue-600/20 text-blue-300'
        : 'text-slate-400 hover:bg-white/5 hover:text-slate-100'
    }
    return active
      ? 'bg-primary/10 text-primary'
      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
  }

  const iconBoxClass = (href: string) => {
    const active = isActive(href)
    if (isSidebarDark.value) {
      return active
        ? 'bg-blue-500/20 text-blue-400'
        : 'bg-white/5 text-slate-500 group-hover:bg-white/10 group-hover:text-slate-300'
    }
    return active
      ? 'bg-primary/10 text-primary'
      : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200 group-hover:text-slate-600'
  }

  const groupLabelClass = computed(() =>
    isSidebarDark.value ? 'text-slate-500' : 'text-slate-400'
  )

  const activeBarColor = computed(() =>
    isSidebarDark.value ? 'bg-blue-400' : 'bg-primary'
  )

  const activeChevronClass = computed(() =>
    isSidebarDark.value ? 'text-blue-400' : 'text-primary'
  )

  const userFooterClass = computed(() =>
    isSidebarDark.value
      ? 'border-t border-white/5 bg-black/20'
      : 'border-t border-slate-200 bg-slate-50'
  )

  const userNameClass = computed(() =>
    isSidebarDark.value ? 'text-slate-200' : 'text-slate-800'
  )

  const userRoleClass = computed(() =>
    isSidebarDark.value ? 'text-slate-500' : 'text-slate-400'
  )

  const logoutBtnClass = computed(() =>
    isSidebarDark.value
      ? 'text-slate-600 hover:text-red-400 hover:bg-red-500/10'
      : 'text-slate-400 hover:text-red-500 hover:bg-red-50'
  )

  return {
    route, router, auth,
    themeMode, applyTheme,
    isSidebarOpen, toggleSidebar,
    isCollapsed, toggleCollapse,
    menuGroups, allMenuGroups,
    userInitials,
    logout, getCurrentPageTitle, isActive,
    sidebarBg, isSidebarDark,
    navItemClass, iconBoxClass,
    groupLabelClass, activeBarColor, activeChevronClass,
    userFooterClass, userNameClass, userRoleClass, logoutBtnClass,
  }
}
