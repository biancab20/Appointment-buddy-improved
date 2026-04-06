import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

import { getActivePinia } from 'pinia'

import {
  ACCESS_TOKEN_KEY,
  REFRESH_TOKEN_KEY,
  USER_ROLE_KEY,
  type UserRole,
  useAuthStore,
} from '@/stores/auth'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost'

interface RefreshResponse {
  access_token: string
  refresh_token?: string | null
  user?: {
    role?: UserRole
  }
}

export const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
  },
})

let refreshPromise: Promise<string | null> | null = null

function isUserRole(value: unknown): value is UserRole {
  return value === 'admin' || value === 'tutor' || value === 'student'
}

function isAuthEndpoint(url: string): boolean {
  return url.includes('/auth/login') || url.includes('/auth/refresh') || url.includes('/auth/logout')
}

function getAuthStoreSafe() {
  const activePinia = getActivePinia()
  if (!activePinia) {
    return null
  }

  return useAuthStore(activePinia)
}

function persistSession(accessToken: string, refreshToken: string | null, role: UserRole): void {
  const authStore = getAuthStoreSafe()
  if (authStore) {
    authStore.setSession(accessToken, role, refreshToken)
    return
  }

  localStorage.setItem(ACCESS_TOKEN_KEY, accessToken)
  localStorage.setItem(USER_ROLE_KEY, role)

  if (refreshToken) {
    localStorage.setItem(REFRESH_TOKEN_KEY, refreshToken)
  } else {
    localStorage.removeItem(REFRESH_TOKEN_KEY)
  }
}

function clearSession(): void {
  const authStore = getAuthStoreSafe()
  if (authStore) {
    authStore.clearSession()
    return
  }

  localStorage.removeItem(ACCESS_TOKEN_KEY)
  localStorage.removeItem(REFRESH_TOKEN_KEY)
  localStorage.removeItem(USER_ROLE_KEY)
}

async function refreshAccessToken(): Promise<string | null> {
  if (refreshPromise) {
    return refreshPromise
  }

  refreshPromise = (async () => {
    const currentRefreshToken = localStorage.getItem(REFRESH_TOKEN_KEY)
    if (!currentRefreshToken) {
      throw new Error('Missing refresh token')
    }

    const response = await axios.post<RefreshResponse>(
      `${baseURL}/auth/refresh`,
      {
        refresh_token: currentRefreshToken,
      },
      {
        headers: {
          'Content-Type': 'application/json',
        },
      },
    )

    const newAccessToken = response.data.access_token
    if (!newAccessToken) {
      throw new Error('Refresh response did not include an access token')
    }

    const newRefreshToken = response.data.refresh_token ?? currentRefreshToken
    const roleCandidate = response.data.user?.role ?? localStorage.getItem(USER_ROLE_KEY)

    if (!isUserRole(roleCandidate)) {
      throw new Error('Refresh response did not include a valid user role')
    }

    persistSession(newAccessToken, newRefreshToken, roleCandidate)
    return newAccessToken
  })()
    .catch(() => {
      clearSession()
      return null
    })
    .finally(() => {
      refreshPromise = null
    })

  return refreshPromise
}

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(ACCESS_TOKEN_KEY)
  if (!token) {
    return config
  }

  const headers = config.headers ?? {}
  if (!(headers as Record<string, string>).Authorization) {
    ;(headers as Record<string, string>).Authorization = `Bearer ${token}`
  }
  config.headers = headers

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as (InternalAxiosRequestConfig & { _retry?: boolean }) | undefined

    if (
      error.response?.status !== 401 ||
      !originalRequest ||
      originalRequest._retry ||
      isAuthEndpoint(String(originalRequest.url ?? ''))
    ) {
      return Promise.reject(error)
    }

    originalRequest._retry = true

    const newAccessToken = await refreshAccessToken()
    if (!newAccessToken) {
      return Promise.reject(error)
    }

    originalRequest.headers = originalRequest.headers ?? {}
    ;(originalRequest.headers as Record<string, string>).Authorization = `Bearer ${newAccessToken}`

    return api.request(originalRequest)
  },
)