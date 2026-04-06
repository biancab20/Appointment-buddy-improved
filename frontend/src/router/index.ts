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
      path: '/signup',
      name: 'signup',
      component: () => import('../views/SignupView.vue'),
      meta: { guestOnly: true },
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
    return { name: 'home' }
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