import axios from 'axios'
import { ref } from 'vue'

import { defineStore } from 'pinia'

import { api } from '@/lib/api'

export interface ServiceItem {
  id: number
  title: string
  description: string | null
  duration_minutes: number
  price: number
  tutor_name: string | null
}

export interface TutorService {
  id: number
  title: string
  description: string | null
  duration_minutes: number
  price: number
  is_active: number | boolean
}

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
  has_prev: boolean
  has_next: boolean
}

export interface StudentServicesQuery {
  page: number
  per_page: number
  subject?: string
  min_duration?: number
  max_duration?: number
  min_price?: number
  max_price?: number
}

export interface TutorServicePayload {
  title: string
  description: string | null
  duration_minutes: number
  price: number
}

interface StudentServicesResponse {
  services: ServiceItem[]
  pagination?: PaginationMeta
}

interface TutorServicesResponse {
  services: TutorService[]
}

function toErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<{ error?: string }>(error)) {
    return error.response?.data?.error ?? fallback
  }

  return fallback
}

export const useServicesStore = defineStore('services', () => {
  const studentServices = ref<ServiceItem[]>([])
  const studentPagination = ref<PaginationMeta>({
    page: 1,
    per_page: 6,
    total: 0,
    total_pages: 1,
    has_prev: false,
    has_next: false,
  })
  const studentLoading = ref(false)

  const tutorServices = ref<TutorService[]>([])
  const tutorLoading = ref(false)

  async function fetchStudentServices(query: StudentServicesQuery): Promise<void> {
    studentLoading.value = true

    try {
      const response = await api.get<StudentServicesResponse>('/api/student/services', {
        params: query,
      })

      studentServices.value = response.data.services ?? []

      const responsePagination = response.data.pagination
      if (responsePagination) {
        studentPagination.value = responsePagination
      } else {
        studentPagination.value = {
          page: query.page,
          per_page: query.per_page,
          total: studentServices.value.length,
          total_pages: 1,
          has_prev: false,
          has_next: false,
        }
      }
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load services.'))
    } finally {
      studentLoading.value = false
    }
  }

  async function fetchTutorServices(): Promise<void> {
    tutorLoading.value = true

    try {
      const response = await api.get<TutorServicesResponse>('/api/tutor/services')
      tutorServices.value = response.data.services ?? []
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to load your services.'))
    } finally {
      tutorLoading.value = false
    }
  }

  async function createTutorService(payload: TutorServicePayload): Promise<void> {
    try {
      await api.post('/api/tutor/services', payload)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to create service.'))
    }
  }

  async function updateTutorService(serviceId: number, payload: TutorServicePayload): Promise<void> {
    try {
      await api.put(`/api/tutor/services/${serviceId}`, payload)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to update service.'))
    }
  }

  async function deactivateTutorService(serviceId: number): Promise<void> {
    try {
      await api.delete(`/api/tutor/services/${serviceId}`)
    } catch (error: unknown) {
      throw new Error(toErrorMessage(error, 'Unable to disable service.'))
    }
  }

  return {
    createTutorService,
    deactivateTutorService,
    fetchStudentServices,
    fetchTutorServices,
    studentLoading,
    studentPagination,
    studentServices,
    tutorLoading,
    tutorServices,
    updateTutorService,
  }
})
