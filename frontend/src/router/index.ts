import { createRouter, createWebHistory } from 'vue-router'

import { useAccessStore } from '@/stores/access'
import { useAuthStore } from '@/stores/auth'

import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/SignupView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: HomeView,
      beforeEnter: () => {
        const authStore = useAuthStore()
        const accessStore = useAccessStore()

        if (!authStore.isAuthenticated) {
          return { name: 'login' }
        }

        if (authStore.role === 'student') {
          return { name: 'student-dashboard' }
        }

        if (authStore.role === 'tutor') {
          return { name: 'tutor-dashboard' }
        }

        if (authStore.role === 'admin') {
          return { name: 'admin-dashboard' }
        }

        accessStore.setError(403)
        return false
      },
    },
    {
      path: '/student/dashboard',
      name: 'student-dashboard',
      component: () => import('../views/dashboards/StudentDashboardView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['student'] },
    },
    {
      path: '/student/services',
      name: 'student-services',
      component: () => import('../views/student/StudentServicesView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['student'] },
    },
    {
      path: '/student/bookings',
      name: 'student-bookings',
      component: () => import('../views/student/StudentBookingsView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['student'] },
    },
    {
      path: '/tutor/dashboard',
      name: 'tutor-dashboard',
      component: () => import('../views/dashboards/TutorDashboardView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['tutor'] },
    },
    {
      path: '/tutor/services',
      name: 'tutor-services',
      component: () => import('../views/tutor/TutorServicesView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['tutor'] },
    },
    {
      path: '/tutor/services/:id/timeslots',
      name: 'tutor-service-timeslots',
      component: () => import('../views/tutor/TutorTimeslotsView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['tutor'] },
    },
    {
      path: '/tutor/bookings',
      name: 'tutor-bookings',
      component: () => import('../views/tutor/TutorBookingsView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['tutor'] },
    },
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: () => import('../views/admin/AdminDashboardView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    },
    {
      path: '/admin/services',
      name: 'admin-services',
      component: () => import('../views/admin/AdminServicesView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    },
    {
      path: '/admin/bookings',
      name: 'admin-bookings',
      component: () => import('../views/admin/AdminBookingsView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('../views/admin/AdminUsersView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    },
    {
      path: '/admin/transactions',
      name: 'admin-transactions',
      component: () => import('../views/admin/AdminTransactionsView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['admin'] },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/errors/Error404View.vue'),
    },
  ],
})

router.beforeEach((to) => {
  const accessStore = useAccessStore()
  accessStore.clearError()

  const authStore = useAuthStore()

  const isGuestOnly = to.matched.some((record) => record.meta.guestOnly === true)
  if (isGuestOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth === true)
  if (!requiresAuth) {
    return true
  }

  if (!authStore.isAuthenticated) {
    accessStore.setError(401)
    return false
  }

  const allowedRoles = to.matched.flatMap((record) => {
    if (!Array.isArray(record.meta.allowedRoles)) {
      return []
    }

    return record.meta.allowedRoles.filter((role): role is string => typeof role === 'string')
  })

  if (allowedRoles.length > 0 && (!authStore.role || !allowedRoles.includes(authStore.role))) {
    accessStore.setError(403)
    return false
  }

  return true
})

export default router
