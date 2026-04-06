import axios from 'axios'
import { ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export interface StudentTimeslot {
  id: number
  service_id: number
  start_time: string
  end_time: string
}

export interface TutorServiceSummary {
  id: number
  title: string
  duration_minutes: number
  is_active: number | boolean
}

export interface TutorTimeslot {
  id: number
  service_id: number
  start_time: string
  end_time: string
  is_active: number | boolean
}

interface StudentTimeslotsResponse {
  timeslots: StudentTimeslot[]
}

interface TutorTimeslotsResponse {
  service: TutorServiceSummary
  timeslots: TutorTimeslot[]
}

interface DeactivateTimeslotResponse {
  cancelled_bookings_count?: number
}

interface CreateCheckoutSessionResponse {
  checkout_url: string
  session_id: string
  transaction_id: number
}

function toErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? fallback
  }

  return fallback
}

export const useTimeslotsStore = defineStore('timeslots', () => {
  const tutorService = ref<TutorServiceSummary | null>(null)
  const tutorTimeslots = ref<TutorTimeslot[]>([])
  const tutorLoading = ref(false)

  async function fetchStudentServiceTimeslots(serviceId: number): Promise<StudentTimeslot[]> {
    try {
      const response = await api.get<StudentTimeslotsResponse>(`/api/student/services/${serviceId}/timeslots`)
      const loaded = response.data.timeslots ?? []
      loaded.sort((a, b) => a.start_time.localeCompare(b.start_time))
      return loaded
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load timeslots.'))
    }
  }

  async function fetchTutorServiceTimeslots(serviceId: number): Promise<void> {
    tutorLoading.value = true

    try {
      const response = await api.get<TutorTimeslotsResponse>(`/api/tutor/services/${serviceId}/timeslots`)
      tutorService.value = response.data.service
      tutorTimeslots.value = response.data.timeslots ?? []
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load timeslots.'))
    } finally {
      tutorLoading.value = false
    }
  }

  async function createTutorTimeslot(serviceId: number, startTime: string): Promise<void> {
    try {
      await api.post(`/api/tutor/services/${serviceId}/timeslots`, {
        start_time: startTime,
      })
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to create timeslot.'))
    }
  }

  async function updateTutorTimeslot(timeslotId: number, startTime: string): Promise<void> {
    try {
      await api.put(`/api/tutor/timeslots/${timeslotId}`, {
        start_time: startTime,
      })
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to update timeslot.'))
    }
  }

  async function deactivateTutorTimeslot(timeslotId: number): Promise<number> {
    try {
      const response = await api.delete<DeactivateTimeslotResponse>(`/api/tutor/timeslots/${timeslotId}`)
      return Number(response.data.cancelled_bookings_count ?? 0)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to deactivate timeslot.'))
    }
  }

  async function createStudentCheckoutSession(
    timeslotId: number,
    successUrl: string,
    cancelUrl: string,
  ): Promise<string> {
    try {
      const response = await api.post<CreateCheckoutSessionResponse>(
        '/api/student/bookings/checkout-session',
        {
          timeslot_id: timeslotId,
          success_url: successUrl,
          cancel_url: cancelUrl,
        },
      )

      const checkoutUrl = response.data.checkout_url
      if (!checkoutUrl) {
        throw new Error('Checkout url was not returned by the server.')
      }

      return checkoutUrl
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to start payment checkout.'))
    }
  }

  return {
    createStudentCheckoutSession,
    createTutorTimeslot,
    deactivateTutorTimeslot,
    fetchStudentServiceTimeslots,
    fetchTutorServiceTimeslots,
    tutorLoading,
    tutorService,
    tutorTimeslots,
    updateTutorTimeslot,
  }
})
