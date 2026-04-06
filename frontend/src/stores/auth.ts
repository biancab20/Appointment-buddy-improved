import { computed, ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export type UserRole = 'admin' | 'tutor' | 'student'

const ACCESS_TOKEN_KEY = 'appointment_buddy_access_token'
const REFRESH_TOKEN_KEY = 'appointment_buddy_refresh_token'
const USER_ROLE_KEY = 'appointment_buddy_user_role'

export const useAuthStore = defineStore('auth', () => {
  const accessToken = ref<string | null>(localStorage.getItem(ACCESS_TOKEN_KEY))
  const refreshToken = ref<string | null>(localStorage.getItem(REFRESH_TOKEN_KEY))
  const role = ref<UserRole | null>((localStorage.getItem(USER_ROLE_KEY) as UserRole | null) ?? null)

  const isAuthenticated = computed(() => Boolean(accessToken.value))

  function setSession(token: string, userRole: UserRole, newRefreshToken: string | null = null): void {
    accessToken.value = token
    role.value = userRole
    refreshToken.value = newRefreshToken

    localStorage.setItem(ACCESS_TOKEN_KEY, token)
    localStorage.setItem(USER_ROLE_KEY, userRole)

    if (newRefreshToken) {
      localStorage.setItem(REFRESH_TOKEN_KEY, newRefreshToken)
    } else {
      localStorage.removeItem(REFRESH_TOKEN_KEY)
    }
  }

  function clearSession(): void {
    accessToken.value = null
    refreshToken.value = null
    role.value = null

    localStorage.removeItem(ACCESS_TOKEN_KEY)
    localStorage.removeItem(REFRESH_TOKEN_KEY)
    localStorage.removeItem(USER_ROLE_KEY)
  }

  async function logout(): Promise<void> {
    const token = accessToken.value
    const currentRefreshToken = refreshToken.value

    try {
      if (token) {
        await api.post(
          '/auth/logout',
          {
            refresh_token: currentRefreshToken,
          },
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          },
        )
      }
    } catch {
      // Clear client session even if backend logout fails.
    } finally {
      clearSession()
    }
  }

  return {
    accessToken,
    clearSession,
    isAuthenticated,
    logout,
    refreshToken,
    role,
    setSession,
  }
})