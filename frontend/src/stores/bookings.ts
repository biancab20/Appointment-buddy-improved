import axios from 'axios'
import { ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export type BookingScope = 'upcoming' | 'history'

export interface StudentBooking {
  id: number
  status: 'paid' | 'cancelled'
  created_at: string
  price_at_booking: number
  timeslot_id: number
  start_time: string
  end_time: string
  service_id: number
  service_title: string
  tutor_id: number
  tutor_name: string
}

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
  has_prev: boolean
  has_next: boolean
}

export interface StudentBookingsQuery {
  scope: BookingScope
  page: number
  per_page: number
  date_from?: string
  date_to?: string
}

export interface RescheduleOption {
  id: number
  service_id: number
  start_time: string
  end_time: string
}

interface RescheduleOptionsResponse {
  booking: {
    id: number
    service_id: number
    timeslot_id: number
    start_time: string
  }
  timeslots: RescheduleOption[]
}

interface CancelBookingResponse {
  booking_id: number
  refund_eligible: boolean
  message: string
}

interface RescheduleBookingResponse {
  booking_id: number
  new_timeslot_id: number
  message: string
}

interface StudentBookingsResponse {
  bookings: StudentBooking[]
  pagination?: PaginationMeta
}

function toErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? fallback
  }

  return fallback
}

export const useBookingsStore = defineStore('bookings', () => {
  const studentBookings = ref<StudentBooking[]>([])
  const studentPagination = ref<PaginationMeta>({
    page: 1,
    per_page: 6,
    total: 0,
    total_pages: 1,
    has_prev: false,
    has_next: false,
  })
  const studentLoading = ref(false)

  async function fetchStudentBookings(query: StudentBookingsQuery): Promise<void> {
    studentLoading.value = true

    try {
      const response = await api.get<StudentBookingsResponse>('/api/student/bookings', {
        params: query,
      })

      studentBookings.value = response.data.bookings ?? []

      const responsePagination = response.data.pagination
      if (responsePagination) {
        studentPagination.value = responsePagination
      } else {
        studentPagination.value = {
          page: query.page,
          per_page: query.per_page,
          total: studentBookings.value.length,
          total_pages: 1,
          has_prev: false,
          has_next: false,
        }
      }
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load bookings.'))
    } finally {
      studentLoading.value = false
    }
  }

  async function cancelStudentBooking(bookingId: number): Promise<CancelBookingResponse> {
    try {
      const response = await api.delete<CancelBookingResponse>(`/api/student/bookings/${bookingId}`)
      return response.data
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to cancel booking.'))
    }
  }

  async function fetchRescheduleOptions(bookingId: number): Promise<RescheduleOption[]> {
    try {
      const response = await api.get<RescheduleOptionsResponse>(
        `/api/student/bookings/${bookingId}/reschedule-options`,
      )
      return response.data.timeslots ?? []
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load reschedule options.'))
    }
  }

  async function rescheduleStudentBooking(bookingId: number, newTimeslotId: number): Promise<string> {
    try {
      const response = await api.put<RescheduleBookingResponse>(
        `/api/student/bookings/${bookingId}/reschedule`,
        {
          new_timeslot_id: newTimeslotId,
        },
      )

      return response.data.message || 'Booking rescheduled successfully.'
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to reschedule booking.'))
    }
  }

  return {
    cancelStudentBooking,
    fetchStudentBookings,
    fetchRescheduleOptions,
    rescheduleStudentBooking,
    studentBookings,
    studentLoading,
    studentPagination,
  }
})
