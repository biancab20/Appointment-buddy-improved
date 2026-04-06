import axios from 'axios'
import { ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
  has_prev: boolean
  has_next: boolean
}

export interface AdminUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'tutor' | 'student'
  created_at: string
}

export interface AdminService {
  id: number
  tutor_id: number
  tutor_name: string | null
  title: string
  description: string | null
  duration_minutes: number
  price: number
  is_active: number | boolean
  created_at: string
}

export interface AdminBooking {
  id: number
  student_id: number
  student_name: string
  student_email: string
  tutor_id: number
  tutor_name: string
  tutor_email: string
  service_id: number
  service_title: string
  timeslot_id: number
  start_time: string
  end_time: string
  price_at_booking: number
  status: 'paid' | 'cancelled'
  created_at: string
}

export interface AdminTransaction {
  id: number
  student_id: number
  student_name: string
  student_email: string
  tutor_id: number
  tutor_name: string
  tutor_email: string
  service_id: number
  service_title: string
  timeslot_id: number
  booking_id: number | null
  provider: string
  provider_session_id: string
  provider_payment_intent_id: string | null
  amount: number | string
  currency: string
  status: 'pending' | 'paid' | 'failed' | 'cancelled'
  failure_reason: string | null
  paid_at: string | null
  created_at: string
  updated_at: string
  start_time: string
  end_time: string
}

export interface AdminUsersQuery {
  page: number
  per_page: number
  role?: 'admin' | 'tutor' | 'student'
  search?: string
}

export interface AdminServicesQuery {
  page: number
  per_page: number
  subject?: string
  tutor_id?: number
  is_active?: boolean
  min_duration?: number
  max_duration?: number
  min_price?: number
  max_price?: number
}

export interface AdminBookingsQuery {
  page: number
  per_page: number
  scope?: 'upcoming' | 'history'
  status?: 'paid' | 'cancelled'
  student_id?: number
  tutor_id?: number
  service_id?: number
  date_from?: string
  date_to?: string
}

export interface AdminTransactionsQuery {
  page: number
  per_page: number
  status?: 'pending' | 'paid' | 'failed' | 'cancelled'
  provider?: 'stripe'
  currency?: string
  student_id?: number
  tutor_id?: number
  service_id?: number
  timeslot_id?: number
  booking_id?: number
  date_from?: string
  date_to?: string
}

interface AdminUsersResponse {
  users: AdminUser[]
  pagination?: PaginationMeta
}

interface AdminServicesResponse {
  services: AdminService[]
  pagination?: PaginationMeta
}

interface AdminBookingsResponse {
  bookings: AdminBooking[]
  pagination?: PaginationMeta
}

interface AdminTransactionsResponse {
  transactions: AdminTransaction[]
  pagination?: PaginationMeta
}

function createDefaultPagination(page = 1, perPage = 10): PaginationMeta {
  return {
    page,
    per_page: perPage,
    total: 0,
    total_pages: 1,
    has_prev: false,
    has_next: false,
  }
}

function toErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? fallback
  }

  return fallback
}

export const useAdminStore = defineStore('admin', () => {
  const users = ref<AdminUser[]>([])
  const usersPagination = ref<PaginationMeta>(createDefaultPagination())
  const usersLoading = ref(false)

  const services = ref<AdminService[]>([])
  const servicesPagination = ref<PaginationMeta>(createDefaultPagination())
  const servicesLoading = ref(false)

  const bookings = ref<AdminBooking[]>([])
  const bookingsPagination = ref<PaginationMeta>(createDefaultPagination())
  const bookingsLoading = ref(false)

  const transactions = ref<AdminTransaction[]>([])
  const transactionsPagination = ref<PaginationMeta>(createDefaultPagination())
  const transactionsLoading = ref(false)

  async function fetchUsers(query: AdminUsersQuery): Promise<void> {
    usersLoading.value = true

    try {
      const response = await api.get<AdminUsersResponse>('/api/admin/users', {
        params: query,
      })

      users.value = response.data.users ?? []
      usersPagination.value = response.data.pagination ?? createDefaultPagination(query.page, query.per_page)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load users overview.'))
    } finally {
      usersLoading.value = false
    }
  }

  async function fetchServices(query: AdminServicesQuery): Promise<void> {
    servicesLoading.value = true

    try {
      const response = await api.get<AdminServicesResponse>('/api/admin/services', {
        params: query,
      })

      services.value = response.data.services ?? []
      servicesPagination.value =
        response.data.pagination ?? createDefaultPagination(query.page, query.per_page)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load services overview.'))
    } finally {
      servicesLoading.value = false
    }
  }

  async function fetchBookings(query: AdminBookingsQuery): Promise<void> {
    bookingsLoading.value = true

    try {
      const response = await api.get<AdminBookingsResponse>('/api/admin/bookings', {
        params: query,
      })

      bookings.value = response.data.bookings ?? []
      bookingsPagination.value =
        response.data.pagination ?? createDefaultPagination(query.page, query.per_page)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load bookings overview.'))
    } finally {
      bookingsLoading.value = false
    }
  }

  async function fetchTransactions(query: AdminTransactionsQuery): Promise<void> {
    transactionsLoading.value = true

    try {
      const response = await api.get<AdminTransactionsResponse>('/api/admin/transactions', {
        params: query,
      })

      transactions.value = response.data.transactions ?? []
      transactionsPagination.value =
        response.data.pagination ?? createDefaultPagination(query.page, query.per_page)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load transactions overview.'))
    } finally {
      transactionsLoading.value = false
    }
  }

  return {
    bookings,
    bookingsLoading,
    bookingsPagination,
    fetchBookings,
    fetchServices,
    fetchTransactions,
    fetchUsers,
    services,
    servicesLoading,
    servicesPagination,
    transactions,
    transactionsLoading,
    transactionsPagination,
    users,
    usersLoading,
    usersPagination,
  }
})
