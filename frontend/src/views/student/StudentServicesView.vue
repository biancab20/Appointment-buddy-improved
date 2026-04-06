<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import type { ServiceItem, StudentServicesQuery } from '@/stores/services'
import { useServicesStore } from '@/stores/services'
import type { StudentTimeslot } from '@/stores/timeslots'
import { useTimeslotsStore } from '@/stores/timeslots'
import { dateKey, formatDate, formatTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface ServiceTimeslotState {
  open: boolean
  loading: boolean
  loaded: boolean
  error: string
  timeslots: StudentTimeslot[]
  selectedDate: string
  selectedTimeslotId: number | null
  paymentLoading: boolean
  paymentError: string
}

interface ServiceFilters {
  subject: string
  minDuration: string
  maxDuration: string
  minPrice: string
  maxPrice: string
  perPage: string
}

const DEFAULT_PER_PAGE = 6
const DEFAULT_FILTERS: ServiceFilters = {
  subject: '',
  minDuration: '',
  maxDuration: '',
  minPrice: '',
  maxPrice: '',
  perPage: String(DEFAULT_PER_PAGE),
}

const servicesStore = useServicesStore()
const timeslotsStore = useTimeslotsStore()
const { studentLoading, studentPagination, studentServices } = storeToRefs(servicesStore)

const services = computed<ServiceItem[]>(() => studentServices.value)
const isLoading = computed(() => studentLoading.value)
const pagination = computed(() => ({
  page: studentPagination.value.page,
  perPage: studentPagination.value.per_page,
  total: studentPagination.value.total,
  totalPages: studentPagination.value.total_pages,
  hasPrev: studentPagination.value.has_prev,
  hasNext: studentPagination.value.has_next,
}))

const errorMessage = ref('')
const filters = reactive<ServiceFilters>({ ...DEFAULT_FILTERS })
const modalFilters = reactive<ServiceFilters>({ ...DEFAULT_FILTERS })
const isFiltersModalOpen = ref(false)
const timeslotStates = reactive<Record<number, ServiceTimeslotState>>({})

const resultsSummary = computed(() => {
  if (pagination.value.total <= 0 || services.value.length === 0) {
    return 'No services found for the current filters.'
  }

  const start = (pagination.value.page - 1) * pagination.value.perPage + 1
  const end = start + services.value.length - 1
  return `Showing ${start}-${end} of ${pagination.value.total} services`
})

const hasActiveFilters = computed(() => {
  return (
    filters.subject.trim() !== '' ||
    filters.minDuration.trim() !== '' ||
    filters.maxDuration.trim() !== '' ||
    filters.minPrice.trim() !== '' ||
    filters.maxPrice.trim() !== '' ||
    Number(filters.perPage) !== DEFAULT_PER_PAGE
  )
})

function ensureState(serviceId: number): ServiceTimeslotState {
  if (!timeslotStates[serviceId]) {
    timeslotStates[serviceId] = {
      open: false,
      loading: false,
      loaded: false,
      error: '',
      timeslots: [],
      selectedDate: '',
      selectedTimeslotId: null,
      paymentLoading: false,
      paymentError: '',
    }
  }

  return timeslotStates[serviceId]
}

function uniqueDateKeys(timeslots: StudentTimeslot[]): string[] {
  return [...new Set(timeslots.map((slot) => dateKey(slot.start_time)))]
}

function slotsForDate(serviceId: number): StudentTimeslot[] {
  const state = ensureState(serviceId)
  if (!state.selectedDate) {
    return state.timeslots
  }

  return state.timeslots.filter((slot) => dateKey(slot.start_time) === state.selectedDate)
}

function nextThree(serviceId: number): StudentTimeslot[] {
  const state = ensureState(serviceId)
  return state.timeslots.slice(0, 3)
}

function findTimeslotById(serviceId: number, timeslotId: number | null): StudentTimeslot | null {
  if (!timeslotId) {
    return null
  }

  const state = ensureState(serviceId)
  return state.timeslots.find((slot) => slot.id === timeslotId) ?? null
}

function selectedTimeslot(serviceId: number): StudentTimeslot | null {
  const state = ensureState(serviceId)
  return findTimeslotById(serviceId, state.selectedTimeslotId)
}

function toOptionalNumber(value: string): number | null {
  const normalized = value.trim()
  if (normalized === '') {
    return null
  }

  const parsed = Number(normalized)
  if (!Number.isFinite(parsed)) {
    return null
  }

  return parsed
}

function validateFilters(form: ServiceFilters): string | null {
  const minDuration = toOptionalNumber(form.minDuration)
  const maxDuration = toOptionalNumber(form.maxDuration)
  const minPrice = toOptionalNumber(form.minPrice)
  const maxPrice = toOptionalNumber(form.maxPrice)

  if (minDuration !== null && (!Number.isInteger(minDuration) || minDuration <= 0)) {
    return 'Min duration must be a positive whole number.'
  }

  if (maxDuration !== null && (!Number.isInteger(maxDuration) || maxDuration <= 0)) {
    return 'Max duration must be a positive whole number.'
  }

  if (minPrice !== null && minPrice < 0) {
    return 'Min price cannot be negative.'
  }

  if (maxPrice !== null && maxPrice < 0) {
    return 'Max price cannot be negative.'
  }

  if (minDuration !== null && maxDuration !== null && minDuration > maxDuration) {
    return 'Min duration cannot be greater than max duration.'
  }

  if (minPrice !== null && maxPrice !== null && minPrice > maxPrice) {
    return 'Min price cannot be greater than max price.'
  }

  const perPage = Number(form.perPage)
  if (!Number.isInteger(perPage) || perPage <= 0) {
    return 'Per page must be a positive whole number.'
  }

  return null
}

function buildQueryParams(page: number): StudentServicesQuery {
  const params: StudentServicesQuery = {
    page,
    per_page: Number(filters.perPage) || DEFAULT_PER_PAGE,
  }

  const subject = filters.subject.trim()
  if (subject !== '') {
    params.subject = subject
  }

  const minDuration = toOptionalNumber(filters.minDuration)
  if (minDuration !== null) {
    params.min_duration = minDuration
  }

  const maxDuration = toOptionalNumber(filters.maxDuration)
  if (maxDuration !== null) {
    params.max_duration = maxDuration
  }

  const minPrice = toOptionalNumber(filters.minPrice)
  if (minPrice !== null) {
    params.min_price = minPrice
  }

  const maxPrice = toOptionalNumber(filters.maxPrice)
  if (maxPrice !== null) {
    params.max_price = maxPrice
  }

  return params
}

function pruneTimeslotStates(): void {
  const visibleIds = new Set(services.value.map((service) => service.id))
  for (const idKey of Object.keys(timeslotStates)) {
    const id = Number(idKey)
    if (!visibleIds.has(id)) {
      delete timeslotStates[id]
    }
  }
}

async function loadServices(page = pagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await servicesStore.fetchStudentServices(buildQueryParams(page))
    pruneTimeslotStates()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load services.'
  }
}

async function loadServiceTimeslots(serviceId: number): Promise<void> {
  const state = ensureState(serviceId)
  state.loading = true
  state.error = ''
  state.paymentError = ''

  try {
    const loadedTimeslots = await timeslotsStore.fetchStudentServiceTimeslots(serviceId)
    state.timeslots = loadedTimeslots
    state.loaded = true
    state.selectedTimeslotId = null

    const firstTimeslot = loadedTimeslots[0]
    state.selectedDate = firstTimeslot ? dateKey(firstTimeslot.start_time) : ''
  } catch (error: unknown) {
    state.error = error instanceof Error ? error.message : 'Unable to load timeslots.'
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

function selectTimeslot(serviceId: number, timeslotId: number): void {
  const state = ensureState(serviceId)
  state.selectedTimeslotId = timeslotId
  state.paymentError = ''
}

async function paySelectedTimeslot(serviceId: number): Promise<void> {
  const state = ensureState(serviceId)
  if (state.paymentLoading || !state.selectedTimeslotId) {
    return
  }

  const selected = selectedTimeslot(serviceId)
  if (!selected) {
    state.paymentError = 'Please select a valid timeslot.'
    return
  }

  state.paymentLoading = true
  state.paymentError = ''

  const origin = window.location.origin
  const successUrl = `${origin}/student/bookings?payment=success&session_id={CHECKOUT_SESSION_ID}`
  const cancelUrl = `${origin}/student/services?payment=cancelled`

  try {
    const checkoutUrl = await timeslotsStore.createStudentCheckoutSession(
      selected.id,
      successUrl,
      cancelUrl,
    )
    window.location.assign(checkoutUrl)
  } catch (error: unknown) {
    state.paymentError = error instanceof Error ? error.message : 'Unable to start payment checkout.'
    state.paymentLoading = false
  }
}

function copyFilters(source: ServiceFilters, target: ServiceFilters): void {
  target.subject = source.subject
  target.minDuration = source.minDuration
  target.maxDuration = source.maxDuration
  target.minPrice = source.minPrice
  target.maxPrice = source.maxPrice
  target.perPage = source.perPage
}

function openFiltersModal(): void {
  copyFilters(filters, modalFilters)
  isFiltersModalOpen.value = true
}

function closeFiltersModal(): void {
  isFiltersModalOpen.value = false
}

function resetModalFilters(): void {
  copyFilters(DEFAULT_FILTERS, modalFilters)
}

async function applyModalFilters(): Promise<void> {
  const validationError = validateFilters(modalFilters)
  if (validationError) {
    errorMessage.value = validationError
    return
  }

  copyFilters(modalFilters, filters)
  closeFiltersModal()
  await loadServices(1)
}

async function clearActiveFilters(): Promise<void> {
  copyFilters(DEFAULT_FILTERS, filters)
  await loadServices(1)
}

async function goToPage(page: number): Promise<void> {
  if (isLoading.value || page < 1 || page > pagination.value.totalPages || page === pagination.value.page) {
    return
  }

  await loadServices(page)
}

onMounted(() => {
  void loadServices()
})
</script>

<template>
  <main class="page-shell">
    <PageHeader
      title="Available Services"
      subtitle="Choose a service and check its available timeslots."
      back-to="/student/dashboard"
    />

    <section class="controls-row">
      <button type="button" class="primary-btn" @click="openFiltersModal">Filters</button>
      <button
        v-if="hasActiveFilters"
        type="button"
        class="ghost-btn"
        @click="clearActiveFilters"
      >
        Clear filters
      </button>
    </section>

    <div v-if="isFiltersModalOpen" class="modal-backdrop" @click.self="closeFiltersModal">
      <section class="filters-modal" role="dialog" aria-modal="true" aria-labelledby="filtersTitle">
        <div class="modal-header">
          <h2 id="filtersTitle" class="modal-title">Filter Services</h2>
          <button type="button" class="close-btn" @click="closeFiltersModal">Close</button>
        </div>

        <form class="filters-grid" @submit.prevent="applyModalFilters">
          <label class="filter-field filter-subject">
            <span>Subject</span>
            <input
              v-model="modalFilters.subject"
              type="text"
              placeholder="e.g. Math, English, Science"
              maxlength="100"
            />
          </label>

          <label class="filter-field">
            <span>Min duration (min)</span>
            <input v-model="modalFilters.minDuration" type="number" min="1" step="1" />
          </label>

          <label class="filter-field">
            <span>Max duration (min)</span>
            <input v-model="modalFilters.maxDuration" type="number" min="1" step="1" />
          </label>

          <label class="filter-field">
            <span>Min price (EUR)</span>
            <input v-model="modalFilters.minPrice" type="number" min="0" step="0.01" />
          </label>

          <label class="filter-field">
            <span>Max price (EUR)</span>
            <input v-model="modalFilters.maxPrice" type="number" min="0" step="0.01" />
          </label>

          <label class="filter-field">
            <span>Per page</span>
            <select v-model="modalFilters.perPage">
              <option value="3">3</option>
              <option value="6">6</option>
              <option value="9">9</option>
              <option value="12">12</option>
            </select>
          </label>

          <div class="filter-actions">
            <button type="button" class="ghost-btn" @click="resetModalFilters">Reset</button>
            <button type="button" class="ghost-btn" @click="closeFiltersModal">Cancel</button>
            <button type="submit" class="primary-btn">Apply filters</button>
          </div>
        </form>
      </section>
    </div>

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />
    <p v-if="isLoading" class="muted">Loading services...</p>

    <template v-else>
      <p class="results-summary">{{ resultsSummary }}</p>

      <PaginationControls
        :page="pagination.page"
        :total-pages="pagination.totalPages"
        :has-prev="pagination.hasPrev"
        :has-next="pagination.hasNext"
        :disabled="isLoading"
        @go="goToPage"
      />

      <section class="service-list">
        <article v-for="service in services" :key="service.id" class="service-card">
          <h2>{{ service.title }}</h2>

          <p v-if="service.description" class="description">{{ service.description }}</p>

          <p class="meta">
            Tutor: <strong>{{ service.tutor_name ?? 'Unknown tutor' }}</strong>
          </p>
          <p class="meta">
            Duration: <strong>{{ service.duration_minutes }} min</strong> |
            Price: <strong>EUR {{ formatPrice(service.price) }}</strong>
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
                      <button
                        v-for="slot in slotsForDate(service.id)"
                        :key="slot.id"
                        type="button"
                        class="timeslot-row selectable"
                        :class="{
                          selected: ensureState(service.id).selectedTimeslotId === slot.id,
                        }"
                        @click="selectTimeslot(service.id, slot.id)"
                      >
                        {{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}
                      </button>
                    </div>
                  </div>

                  <div>
                    <p class="label">Next 3 available</p>
                    <button
                      v-for="slot in nextThree(service.id)"
                      :key="slot.id"
                      type="button"
                      class="next-row selectable"
                      :class="{
                        selected: ensureState(service.id).selectedTimeslotId === slot.id,
                      }"
                      @click="selectTimeslot(service.id, slot.id)"
                    >
                      <div>{{ formatDate(slot.start_time) }}</div>
                      <div>{{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}</div>
                    </button>
                  </div>
                </div>

                <section class="pay-panel">
                  <p v-if="selectedTimeslot(service.id)" class="selected-slot">
                    Selected:
                    <strong>
                      {{ formatDate(selectedTimeslot(service.id)?.start_time ?? '') }}
                      {{ formatTime(selectedTimeslot(service.id)?.start_time ?? '') }} ->
                      {{ formatTime(selectedTimeslot(service.id)?.end_time ?? '') }}
                    </strong>
                  </p>
                  <p v-else class="muted">Select a timeslot to continue to payment.</p>

                  <p v-if="ensureState(service.id).paymentError" class="feedback error inline">
                    {{ ensureState(service.id).paymentError }}
                  </p>

                  <button
                    type="button"
                    class="pay-btn"
                    :disabled="
                      !ensureState(service.id).selectedTimeslotId || ensureState(service.id).paymentLoading
                    "
                    @click="paySelectedTimeslot(service.id)"
                  >
                    {{ ensureState(service.id).paymentLoading ? 'Redirecting...' : 'Pay' }}
                  </button>
                </section>
              </template>
            </template>
          </div>
        </article>

        <p v-if="services.length === 0" class="muted">No services available yet.</p>
      </section>
    </template>
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

.controls-row {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.modal-backdrop {
  align-items: center;
  background: rgba(15, 51, 65, 0.4);
  display: flex;
  inset: 0;
  justify-content: center;
  padding: 1rem;
  position: fixed;
  z-index: 80;
}

.filters-modal {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.42);
  border-radius: 14px;
  box-shadow: 0 14px 30px rgba(15, 51, 65, 0.2);
  max-height: 90vh;
  max-width: 760px;
  overflow-y: auto;
  padding: 1rem;
  width: min(100%, 760px);
}

.modal-header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.8rem;
}

.modal-title {
  color: #0f3341;
  font-size: 1.16rem;
  font-weight: 800;
}

.close-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 9px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.4rem 0.7rem;
}

.close-btn:hover {
  background: #f6f8f9;
}

.filters-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.filter-field {
  display: grid;
  gap: 0.28rem;
}

.filter-subject {
  grid-column: 1 / -1;
}

.filter-field span {
  color: #0f3341;
  font-size: 0.86rem;
  font-weight: 700;
}

.filter-field input,
.filter-field select {
  background: #fff;
  border: 1px solid #d6c4af;
  border-radius: 10px;
  padding: 0.52rem 0.62rem;
  width: 100%;
}

.filter-field input:focus,
.filter-field select:focus {
  border-color: #c57632;
  outline: none;
}

.filter-actions {
  display: flex;
  gap: 0.45rem;
  justify-content: flex-end;
  margin-top: 0.15rem;
}

.primary-btn,
.ghost-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
}

.primary-btn {
  background: #c57632;
  color: #fff;
}

.primary-btn:hover {
  background: #d68744;
}

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover {
  background: #f6f8f9;
}

.results-summary {
  color: #4d5f69;
  font-size: 0.93rem;
  margin-bottom: 0.75rem;
}

.pager {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.95rem;
}

.pager-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 8px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.42rem 0.65rem;
}

.pager-btn:hover {
  background: #f6f8f9;
}

.pager-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.pager-info {
  color: #4d5f69;
  font-size: 0.88rem;
  font-weight: 700;
  margin: 0 0.15rem;
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

.service-card h2 {
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

.selectable {
  cursor: pointer;
  text-align: left;
  width: 100%;
}

.selectable:hover {
  background: #fff8f0;
}

.selectable.selected {
  border-color: #c57632;
  box-shadow: 0 0 0 2px rgba(197, 118, 50, 0.18);
}

.pay-panel {
  align-items: flex-start;
  border-top: 1px solid #f0decb;
  display: grid;
  gap: 0.45rem;
  margin-top: 0.9rem;
  padding-top: 0.85rem;
}

.selected-slot {
  color: #4d5f69;
  font-size: 0.9rem;
}

.selected-slot strong {
  color: #0f3341;
}

.pay-btn {
  background: #0f3341;
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 700;
  padding: 0.56rem 0.95rem;
}

.pay-btn:hover {
  background: #174559;
}

.pay-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
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

  .filters-grid {
    grid-template-columns: 1fr;
  }

  .filter-subject {
    grid-column: auto;
  }

  .filter-actions {
    justify-content: flex-start;
  }

  .timeslot-grid {
    grid-template-columns: 1fr;
  }
}
</style>
