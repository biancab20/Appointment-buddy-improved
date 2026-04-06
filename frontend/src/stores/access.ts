import { ref } from 'vue'

import { defineStore } from 'pinia'

export type AccessErrorCode = 401 | 403 | null

export const useAccessStore = defineStore('access', () => {
  const errorCode = ref<AccessErrorCode>(null)

  function setError(code: 401 | 403): void {
    errorCode.value = code
  }

  function clearError(): void {
    errorCode.value = null
  }

  return {
    clearError,
    errorCode,
    setError,
  }
})