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
      path: '/tutor/dashboard',
      name: 'tutor-dashboard',
      component: () => import('../views/dashboards/TutorDashboardView.vue'),
      meta: { requiresAuth: true, allowedRoles: ['tutor'] },
    },
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: () => import('../views/dashboards/AdminDashboardView.vue'),
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