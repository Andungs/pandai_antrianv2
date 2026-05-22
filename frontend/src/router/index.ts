import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Root Redirect ──────────────────────────────────────────────
    {
      path: '/',
      redirect: '/login',
    },

    // ── Auth Routes ────────────────────────────────────────────────
    {
      path: '/login',
      component: () => import('@/layouts/AuthLayout.vue'),
      children: [
        {
          path: '',
          name: 'login',
          component: () => import('@/views/auth/LoginView.vue'),
          meta: { guest: true },
        },
      ],
    },

    // ── Dashboard Routes (Protected) ───────────────────────────────
    {
      path: '/dashboard',
      component: () => import('@/layouts/DashboardLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        // Superadmin
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/views/dashboard/AdminDashboard.vue'),
          meta: { role: 'superadmin' },
        },
        {
          path: 'services',
          name: 'admin-services',
          component: () => import('@/views/dashboard/AdminServices.vue'),
          meta: { role: 'superadmin' },
        },
        {
          path: 'counters',
          name: 'admin-counters',
          component: () => import('@/views/dashboard/AdminCounters.vue'),
          meta: { role: 'superadmin' },
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/dashboard/AdminUsers.vue'),
          meta: { role: 'superadmin' },
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('@/views/dashboard/AdminSettings.vue'),
          meta: { role: 'superadmin' },
        },
        // Loket
        {
          path: 'loket',
          name: 'loket-dashboard',
          component: () => import('@/views/dashboard/LoketDashboard.vue'),
          meta: { role: 'loket' },
        },
        {
          path: 'loket/history',
          name: 'loket-history',
          component: () => import('@/views/dashboard/LoketHistory.vue'),
          meta: { role: 'loket' },
        },
      ],
    },

    // ── Guest/Public Routes ────────────────────────────────────────
    {
      path: '/kiosk',
      component: () => import('@/layouts/GuestLayout.vue'),
      children: [
        {
          path: '',
          name: 'kiosk',
          component: () => import('@/views/guest/KioskView.vue'),
        },
      ],
    },
    {
      path: '/display',
      component: () => import('@/layouts/GuestLayout.vue'),
      children: [
        {
          path: '',
          name: 'display',
          component: () => import('@/views/guest/DisplayView.vue'),
        },
      ],
    },
    {
      path: '/track/:queueNumber',
      component: () => import('@/layouts/GuestLayout.vue'),
      children: [
        {
          path: '',
          name: 'track',
          component: () => import('@/views/guest/TrackView.vue'),
        },
      ],
    },
  ],
})

// ── Navigation Guard ─────────────────────────────────────────────
router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('auth_token')

  // Protected routes
  if (to.matched.some(r => r.meta.requiresAuth) && !token) {
    return next({ name: 'login' })
  }

  // Guest-only routes (redirect to dashboard if logged in)
  if (to.matched.some(r => r.meta.guest) && token) {
    const user = JSON.parse(localStorage.getItem('auth_user') ?? '{}')
    const defaultRoute = user?.default_route ?? '/dashboard'
    return next(defaultRoute)
  }

  next()
})

export default router
