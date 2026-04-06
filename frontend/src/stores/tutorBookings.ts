import axios from 'axios'
import { ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export type TutorBookingScope = 'upcoming' | 'history'

export interface TutorBooking {
  id: number
  status: 'paid' | 'cancelled'
  created_at: string
  price_at_booking: number
  timeslot_id: number
  start_time: string
  end_time: string
  service_id: number
  service_title: string
  student_id: number
  student_name: string
  student_email: string
}

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
  has_prev: boolean
  has_next: boolean
}

export interface TutorBookingsQuery {
  scope: TutorBookingScope
  page: number
  per_page: number
  date_from?: string
  date_to?: string
}

export interface TutorCalendarQuery {
  scope: TutorBookingScope
  year: number
  month: number
}

export interface TutorDateCount {
  date: string
  count: number
}

interface TutorBookingsResponse {
  bookings: TutorBooking[]
  pagination?: PaginationMeta
}

interface TutorCalendarResponse {
  date_counts: TutorDateCount[]
}

interface CancelTutorBookingResponse {
  booking_id: number
  refund_eligible: boolean
  message: string
}

function toErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? fallback
  }

  return fallback
}

export const useTutorBookingsStore = defineStore('tutor-bookings', () => {
  const bookings = ref<TutorBooking[]>([])
  const pagination = ref<PaginationMeta>({
    page: 1,
    per_page: 6,
    total: 0,
    total_pages: 1,
    has_prev: false,
    has_next: false,
  })
  const dateCounts = ref<TutorDateCount[]>([])
  const loading = ref(false)

  async function fetchTutorBookings(query: TutorBookingsQuery): Promise<void> {
    loading.value = true

    try {
      const response = await api.get<TutorBookingsResponse>('/api/tutor/bookings', {
        params: query,
      })

      bookings.value = response.data.bookings ?? []

      const responsePagination = response.data.pagination
      if (responsePagination) {
        pagination.value = responsePagination
      } else {
        pagination.value = {
          page: query.page,
          per_page: query.per_page,
          total: bookings.value.length,
          total_pages: 1,
          has_prev: false,
          has_next: false,
        }
      }
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load bookings overview.'))
    } finally {
      loading.value = false
    }
  }

  async function fetchTutorCalendarCounts(query: TutorCalendarQuery): Promise<void> {
    try {
      const response = await api.get<TutorCalendarResponse>('/api/tutor/bookings/calendar', {
        params: query,
      })
      dateCounts.value = response.data.date_counts ?? []
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load calendar bookings.'))
    }
  }

  async function cancelTutorBooking(bookingId: number): Promise<CancelTutorBookingResponse> {
    try {
      const response = await api.delete<CancelTutorBookingResponse>(`/api/tutor/bookings/${bookingId}`)
      return response.data
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to cancel booking.'))
    }
  }

  return {
    bookings,
    cancelTutorBooking,
    dateCounts,
    fetchTutorBookings,
    fetchTutorCalendarCounts,
    loading,
    pagination,
  }
})
