<script setup lang="ts">
import axios from 'axios'
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { api } from '@/lib/api'

interface ServiceItem {
  id: number
  title: string
  description: string | null
  duration_minutes: number
  price: number
  tutor_name: string | null
}

interface TimeslotItem {
  id: number
  service_id: number
  start_time: string
  end_time: string
}

interface ServiceTimeslotState {
  open: boolean
  loading: boolean
  loaded: boolean
  error: string
  timeslots: TimeslotItem[]
  selectedDate: string
}

const services = ref<ServiceItem[]>([])
const isLoading = ref(true)
const errorMessage = ref('')

const timeslotStates = reactive<Record<number, ServiceTimeslotState>>({})

function ensureState(serviceId: number): ServiceTimeslotState {
  if (!timeslotStates[serviceId]) {
    timeslotStates[serviceId] = {
      open: false,
      loading: false,
      loaded: false,
      error: '',
      timeslots: [],
      selectedDate: '',
    }
  }

  return timeslotStates[serviceId]
}

function parseDateTime(value: string): Date {
  const isoLike = value.includes('T') ? value : value.replace(' ', 'T')
  return new Date(isoLike)
}

function formatDate(value: string): string {
  const date = parseDateTime(value)
  if (Number.isNaN(date.getTime())) {
    return value.slice(0, 10)
  }

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

function formatTime(value: string): string {
  const date = parseDateTime(value)
  if (Number.isNaN(date.getTime())) {
    return value.slice(11, 16)
  }

  return new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

function dateKey(value: string): string {
  return value.slice(0, 10)
}

function uniqueDateKeys(timeslots: TimeslotItem[]): string[] {
  return [...new Set(timeslots.map((slot) => dateKey(slot.start_time)))]
}

function slotsForDate(serviceId: number): TimeslotItem[] {
  const state = ensureState(serviceId)
  if (!state.selectedDate) {
    return state.timeslots
  }

  return state.timeslots.filter((slot) => dateKey(slot.start_time) === state.selectedDate)
}

function nextThree(serviceId: number): TimeslotItem[] {
  const state = ensureState(serviceId)
  return state.timeslots.slice(0, 3)
}

async function loadServices(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get<{ services: ServiceItem[] }>('/api/student/services')
    services.value = response.data.services ?? []
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      errorMessage.value = error.response?.data?.error ?? 'Unable to load services.'
    } else {
      errorMessage.value = 'Unable to load services.'
    }
  } finally {
    isLoading.value = false
  }
}

async function loadServiceTimeslots(serviceId: number): Promise<void> {
  const state = ensureState(serviceId)
  state.loading = true
  state.error = ''

  try {
    const response = await api.get<{ timeslots: TimeslotItem[] }>(`/api/student/services/${serviceId}/timeslots`)
    const loadedTimeslots = response.data.timeslots ?? []
    loadedTimeslots.sort((a, b) => a.start_time.localeCompare(b.start_time))

    state.timeslots = loadedTimeslots
    state.loaded = true
    const firstTimeslot = loadedTimeslots[0]
    state.selectedDate = firstTimeslot ? dateKey(firstTimeslot.start_time) : ''
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      state.error = error.response?.data?.error ?? 'Unable to load timeslots.'
    } else {
      state.error = 'Unable to load timeslots.'
    }
  } finally {
    state.loading = false
  }
}

async function toggleTimeslots(serviceId: number): Promise<void> {
  const state = ensureState(serviceId)

  if (state.open) {
    state.open = false
    return
  }

  state.open = true

  if (!state.loaded && !state.loading) {
    await loadServiceTimeslots(serviceId)
  }
}

onMounted(() => {
  void loadServices()
})
</script>

<template>
  <main class="page-shell">
    <section class="heading-row">
      <div>
        <h1>Available Services</h1>
        <p class="subtitle">Choose a service and check its available timeslots.</p>
      </div>
      <RouterLink to="/student/dashboard" class="back-btn">Back</RouterLink>
    </section>

    <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>
    <p v-if="isLoading" class="muted">Loading services...</p>

    <section v-else class="service-list">
      <article v-for="service in services" :key="service.id" class="service-card">
        <h2>{{ service.title }}</h2>

        <p v-if="service.description" class="description">{{ service.description }}</p>

        <p class="meta">
          Tutor: <strong>{{ service.tutor_name ?? 'Unknown tutor' }}</strong>
        </p>
        <p class="meta">
          Duration: <strong>{{ service.duration_minutes }} min</strong> |
          Price: <strong>EUR {{ Number(service.price).toFixed(2) }}</strong>
        </p>

        <button type="button" class="toggle-btn" @click="toggleTimeslots(service.id)">
          {{ ensureState(service.id).open ? 'Hide timeslots' : 'View timeslots' }}
        </button>

        <div v-if="ensureState(service.id).open" class="timeslot-panel">
          <p v-if="ensureState(service.id).loading" class="muted">Loading timeslots...</p>
          <p v-else-if="ensureState(service.id).error" class="feedback error inline">
            {{ ensureState(service.id).error }}
          </p>

          <template v-else>
            <p v-if="ensureState(service.id).timeslots.length === 0" class="muted">
              No available timeslots at the moment.
            </p>

            <template v-else>
              <div class="timeslot-grid">
                <div>
                  <label class="label" :for="`date-${service.id}`">Choose a date</label>
                  <select
                    :id="`date-${service.id}`"
                    v-model="ensureState(service.id).selectedDate"
                    class="date-select"
                  >
                    <option
                      v-for="dateOption in uniqueDateKeys(ensureState(service.id).timeslots)"
                      :key="dateOption"
                      :value="dateOption"
                    >
                      {{ formatDate(`${dateOption}T00:00`) }}
                    </option>
                  </select>

                  <div class="timeslot-list">
                    <p class="label">Available times</p>
                    <div
                      v-for="slot in slotsForDate(service.id)"
                      :key="slot.id"
                      class="timeslot-row"
                    >
                      {{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}
                    </div>
                  </div>
                </div>

                <div>
                  <p class="label">Next 3 available</p>
                  <div v-for="slot in nextThree(service.id)" :key="slot.id" class="next-row">
                    <div>{{ formatDate(slot.start_time) }}</div>
                    <div>{{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}</div>
                  </div>
                </div>
              </div>
            </template>
          </template>
        </div>
      </article>

      <p v-if="services.length === 0" class="muted">No services available yet.</p>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 920px;
  min-height: 72vh;
  padding: 0.45rem 0 1.4rem;
}

.heading-row {
  align-items: center;
  display: flex;
  gap: 0.9rem;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.7rem, 4vw, 2.35rem);
  line-height: 1.15;
  margin-bottom: 0.25rem;
}

.subtitle {
  color: #884e1c;
  font-size: 1rem;
  font-weight: 600;
}

.service-list {
  display: grid;
  gap: 1rem;
}

.service-card {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.42);
  border-radius: 18px;
  box-shadow: 0 14px 28px rgba(15, 51, 65, 0.08);
  padding: 1.15rem;
}

h2 {
  color: #0f3341;
  font-size: 1.32rem;
  line-height: 1.25;
  margin-bottom: 0.35rem;
}

.description {
  color: #884e1c;
  margin-bottom: 0.45rem;
  max-width: 70ch;
}

.meta {
  color: #4e616c;
  font-size: 0.93rem;
  margin-bottom: 0.3rem;
}

.toggle-btn {
  background: #c57632;
  border: none;
  border-radius: 10px;
  box-shadow: 0 8px 18px rgba(197, 118, 50, 0.24);
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  margin-top: 0.4rem;
  padding: 0.58rem 0.95rem;
}

.toggle-btn:hover {
  background: #d68744;
}

.timeslot-panel {
  background: linear-gradient(180deg, #fffaf4, #fffefb);
  border: 1px solid #f0decb;
  border-radius: 14px;
  margin-top: 0.95rem;
  padding: 0.85rem;
}

.timeslot-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr;
}

.label {
  color: #0f3341;
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  margin-bottom: 0.4rem;
  text-transform: uppercase;
}

.date-select {
  background: #fff;
  border: 1px solid #d6c4af;
  border-radius: 10px;
  margin-bottom: 0.75rem;
  padding: 0.55rem 0.62rem;
  width: 100%;
}

.timeslot-list {
  display: grid;
  gap: 0.42rem;
}

.timeslot-row,
.next-row {
  background: #fff;
  border: 1px solid #ecd9c6;
  border-radius: 10px;
  color: #4d5f69;
  font-size: 0.92rem;
  padding: 0.52rem 0.62rem;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.9rem;
  padding: 0.68rem 0.82rem;
}

.feedback.inline {
  margin-bottom: 0.2rem;
}

.feedback.error {
  background: #fff1f1;
  border: 1px solid #f2c6c6;
  color: #b42318;
}

.muted {
  color: #63727d;
  font-style: italic;
}

.back-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 9px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
  text-decoration: none;
}

.back-btn:hover {
  background: #f6f8f9;
}

@media (max-width: 760px) {
  .page-shell {
    padding-top: 0.1rem;
  }

  .heading-row {
    align-items: flex-start;
    flex-direction: column;
    margin-bottom: 1rem;
  }

  .timeslot-grid {
    grid-template-columns: 1fr;
  }
}
</style>
