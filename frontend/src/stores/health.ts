import { defineStore } from 'pinia'
import { ref } from 'vue'

import { api } from '@/lib/api'

type HealthResponse = {
  status: string
  service?: string
}

export const useHealthStore = defineStore('health', () => {
  const status = ref<'idle' | 'loading' | 'ok' | 'error'>('idle')
  const service = ref('')
  const errorMessage = ref('')

  async function fetchHealth(): Promise<void> {
    status.value = 'loading'
    errorMessage.value = ''

    try {
      const { data } = await api.get<HealthResponse>('/health')
      status.value = data.status === 'ok' ? 'ok' : 'error'
      service.value = data.service ?? ''

      if (status.value === 'error') {
        errorMessage.value = 'Health endpoint returned a non-ok response.'
      }
    } catch {
      status.value = 'error'
      errorMessage.value = 'Could not reach the backend health endpoint.'
    }
  }

  return {
    status,
    service,
    errorMessage,
    fetchHealth,
  }
})
