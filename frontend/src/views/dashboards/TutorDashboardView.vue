<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { api } from '@/lib/api'

interface TutorService {
  id: number
  title: string
  is_active: number | boolean
}

interface TutorTimeslot {
  id: number
  is_active: number | boolean
}

const isLoading = ref(true)
const errorMessage = ref('')
const totalServices = ref(0)
const activeServices = ref(0)
const activeTimeslots = ref(0)

const summaryText = computed(() => {
  return `You currently have ${activeServices.value} active service(s) and ${activeTimeslots.value} active timeslot(s).`
})

function isActive(value: number | boolean): boolean {
  return value === true || value === 1
}

async function loadSummary(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const servicesResponse = await api.get<{ services: TutorService[] }>('/api/tutor/services')
    const services = servicesResponse.data.services ?? []

    totalServices.value = services.length
    activeServices.value = services.filter((service) => isActive(service.is_active)).length

    if (services.length === 0) {
      activeTimeslots.value = 0
      return
    }

    const responses = await Promise.all(
      services.map((service) =>
        api.get<{ timeslots: TutorTimeslot[] }>(`/api/tutor/services/${service.id}/timeslots`),
      ),
    )

    activeTimeslots.value = responses.reduce((count, response) => {
      const serviceTimeslots = response.data.timeslots ?? []
      return count + serviceTimeslots.filter((timeslot) => isActive(timeslot.is_active)).length
    }, 0)
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      errorMessage.value = error.response?.data?.error ?? 'Unable to load tutor dashboard data.'
    } else {
      errorMessage.value = 'Unable to load tutor dashboard data.'
    }
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadSummary()
})
</script>

<template>
  <main class="dashboard-page">
    <section class="dashboard-shell">
      <h1>Tutor Dashboard</h1>

      <p v-if="isLoading" class="summary muted">Loading your dashboard summary...</p>
      <p v-else-if="errorMessage" class="summary error">{{ errorMessage }}</p>
      <p v-else class="summary">
        {{ summaryText }}
      </p>

      <p class="subtle-info">Total services created: {{ totalServices }}</p>

      <div class="action-list">
        <RouterLink to="/tutor/services" class="action-card action-primary action-link">
          <h2>Manage Services</h2>
          <p>Create, update, and deactivate your tutoring services.</p>
        </RouterLink>

        <RouterLink to="/tutor/services" class="action-card action-dark action-link">
          <h2>Manage Timeslots</h2>
          <p>Add, edit, and cancel timeslots for the services you own.</p>
        </RouterLink>

        <article class="action-card action-light">
          <h2>Bookings Overview</h2>
          <p>Review booked sessions and cancellations from your timetable.</p>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.dashboard-page {
  margin: 0 auto;
  max-width: 940px;
  min-height: 72vh;
  padding: 0.4rem 0 1rem;
}

.dashboard-shell {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 20px;
  box-shadow: 0 14px 34px rgba(15, 51, 65, 0.08);
  padding: clamp(1.1rem, 2vw, 1.7rem);
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 4vw, 2.1rem);
  line-height: 1.2;
  margin-bottom: 0.7rem;
}

.summary {
  color: #884e1c;
  font-size: 1.05rem;
  margin-bottom: 0.35rem;
}

.summary.muted {
  color: #5d707c;
}

.summary.error {
  color: #b42318;
  font-weight: 700;
}

.subtle-info {
  color: #5b6c77;
  margin-bottom: 1rem;
}

.action-list {
  display: grid;
  gap: 0.9rem;
}

.action-card {
  border-radius: 14px;
  padding: 1rem 1.1rem;
}

.action-link {
  color: inherit;
  text-decoration: none;
}

.action-link:hover {
  transform: translateY(-1px);
  transition: transform 0.2s ease;
}

.action-card h2 {
  font-size: 1.05rem;
  margin-bottom: 0.25rem;
}

.action-card p {
  margin-bottom: 0.45rem;
  opacity: 0.96;
}

.action-note {
  display: inline-block;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  opacity: 0.92;
  text-transform: uppercase;
}

.action-primary {
  background: #c57632;
  color: #fff;
}

.action-dark {
  background: #0f3341;
  color: #fff;
}

.action-light {
  background: #f1f6f8;
  border: 1px solid #dbe6eb;
  color: #0f3341;
}
</style>
