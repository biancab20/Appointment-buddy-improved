import { computed, ref } from 'vue'

import { defineStore } from 'pinia'

export type UserRole = 'admin' | 'tutor' | 'student'

const ACCESS_TOKEN_KEY = 'appointment_buddy_access_token'
const USER_ROLE_KEY = 'appointment_buddy_user_role'

export const useAuthStore = defineStore('auth', () => {
  const accessToken = ref<string | null>(localStorage.getItem(ACCESS_TOKEN_KEY))
  const role = ref<UserRole | null>((localStorage.getItem(USER_ROLE_KEY) as UserRole | null) ?? null)

  const isAuthenticated = computed(() => Boolean(accessToken.value))

  function setSession(token: string, userRole: UserRole): void {
    accessToken.value = token
    role.value = userRole

    localStorage.setItem(ACCESS_TOKEN_KEY, token)
    localStorage.setItem(USER_ROLE_KEY, userRole)
  }

  function clearSession(): void {
    accessToken.value = null
    role.value = null

    localStorage.removeItem(ACCESS_TOKEN_KEY)
    localStorage.removeItem(USER_ROLE_KEY)
  }

  return {
    accessToken,
    clearSession,
    isAuthenticated,
    role,
    setSession,
  }
})