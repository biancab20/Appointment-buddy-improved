<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { api } from '@/lib/api'

const isLoading = ref(true)
const errorMessage = ref('')
const upcomingCount = ref(0)

async function loadUpcomingCount(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get<{ upcoming_count: number }>('/api/student/bookings/upcoming-count')
    upcomingCount.value = Number(response.data.upcoming_count ?? 0)
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      errorMessage.value = error.response?.data?.error ?? 'Unable to load dashboard summary.'
    } else {
      errorMessage.value = 'Unable to load dashboard summary.'
    }
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadUpcomingCount()
})
</script>

<template>
  <main class="dashboard-shell">
    <h1>Welcome back!</h1>

    <p v-if="isLoading" class="summary muted">Loading your upcoming appointments...</p>
    <p v-else-if="errorMessage" class="summary error">{{ errorMessage }}</p>
    <p v-else class="summary">
      You have <strong>{{ upcomingCount }}</strong> upcoming appointment(s).
    </p>

    <div class="action-list">
      <RouterLink to="/student/services" class="action-link action-primary">
        Browse Available Services
      </RouterLink>

      <RouterLink to="/student/bookings" class="action-link action-dark">
        View My Bookings
      </RouterLink>
    </div>
  </main>
</template>

<style scoped>
.dashboard-shell {
  margin: 0 auto;
  max-width: 900px;
  min-height: 72vh;
  padding: 0.35rem 0;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.6rem, 4vw, 2.2rem);
  margin-bottom: 0.75rem;
}

.summary {
  color: #884e1c;
  font-size: clamp(1rem, 2vw, 1.16rem);
  margin-bottom: 1.25rem;
}

.summary strong {
  color: #c57632;
}

.summary.muted {
  color: #5f6f79;
}

.summary.error {
  color: #b42318;
  font-weight: 700;
}

.action-list {
  display: grid;
  gap: 0.75rem;
}

.action-link {
  border-radius: 12px;
  box-shadow: 0 10px 22px rgba(15, 51, 65, 0.08);
  color: #fff;
  display: block;
  font-size: 1rem;
  font-weight: 700;
  padding: 0.95rem 1rem;
  text-decoration: none;
  transition:
    transform 0.2s ease,
    filter 0.2s ease;
}

.action-link:hover {
  filter: brightness(1.04);
  transform: translateY(-1px);
}

.action-primary {
  background: #c57632;
}

.action-dark {
  background: #0f3341;
}
</style>
