<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import type { BookingScope, StudentBooking, StudentBookingsQuery } from '@/stores/bookings'
import { useBookingsStore } from '@/stores/bookings'

interface BookingFilters {
  dateFrom: string
  dateTo: string
  perPage: string
}

const DEFAULT_PER_PAGE = 6
const DEFAULT_FILTERS: BookingFilters = {
  dateFrom: '',
  dateTo: '',
  perPage: String(DEFAULT_PER_PAGE),
}

const route = useRoute()
const bookingsStore = useBookingsStore()
const { studentBookings, studentLoading, studentPagination } = storeToRefs(bookingsStore)

const scope = ref<BookingScope>('upcoming')
const filters = reactive<BookingFilters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')

const bookings = computed<StudentBooking[]>(() => studentBookings.value)
const isLoading = computed(() => studentLoading.value)
const pagination = computed(() => ({
  page: studentPagination.value.page,
  perPage: studentPagination.value.per_page,
  total: studentPagination.value.total,
  totalPages: studentPagination.value.total_pages,
  hasPrev: studentPagination.value.has_prev,
  hasNext: studentPagination.value.has_next,
}))

const paymentMessage = computed(() => {
  const payment = String(route.query.payment ?? '')
  if (payment === 'success') {
    return 'Payment successful. Your booking is now in Upcoming.'
  }

  if (payment === 'cancelled') {
    return 'Payment was cancelled. No booking was created.'
  }

  return ''
})

const resultsSummary = computed(() => {
  if (pagination.value.total <= 0 || bookings.value.length === 0) {
    return scope.value === 'upcoming' ? 'No upcoming bookings found.' : 'No booking history found.'
  }

  const start = (pagination.value.page - 1) * pagination.value.perPage + 1
  const end = start + bookings.value.length - 1
  return `Showing ${start}-${end} of ${pagination.value.total} bookings`
})

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

function formatPrice(value: number): string {
  return Number(value).toFixed(2)
}

function isPast(value: string): boolean {
  const date = parseDateTime(value)
  if (Number.isNaN(date.getTime())) {
    return false
  }

  return date.getTime() < Date.now()
}

function statusLabel(booking: StudentBooking): string {
  if (booking.status === 'cancelled') {
    return 'Cancelled'
  }

  if (scope.value === 'history' && isPast(booking.start_time)) {
    return 'Completed'
  }

  return 'Paid'
}

function statusClass(booking: StudentBooking): string {
  if (booking.status === 'cancelled') {
    return 'status-cancelled'
  }

  if (scope.value === 'history' && isPast(booking.start_time)) {
    return 'status-completed'
  }

  return 'status-paid'
}

function isIsoDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return false
  }

  const [year, month, day] = value.split('-').map((part) => Number(part))
  const check = new Date(year, month - 1, day)
  return (
    check.getFullYear() === year &&
    check.getMonth() === month - 1 &&
    check.getDate() === day
  )
}

function validateFilters(): string | null {
  const dateFrom = filters.dateFrom.trim()
  const dateTo = filters.dateTo.trim()
  const perPage = Number(filters.perPage)

  if (dateFrom !== '' && !isIsoDate(dateFrom)) {
    return 'Date from must be a valid date.'
  }

  if (dateTo !== '' && !isIsoDate(dateTo)) {
    return 'Date to must be a valid date.'
  }

  if (dateFrom !== '' && dateTo !== '' && dateFrom > dateTo) {
    return 'Date from cannot be greater than date to.'
  }

  if (!Number.isInteger(perPage) || perPage <= 0) {
    return 'Per page must be a positive whole number.'
  }

  return null
}

function buildQueryParams(page: number): StudentBookingsQuery {
  const params: StudentBookingsQuery = {
    scope: scope.value,
    page,
    per_page: Number(filters.perPage) || DEFAULT_PER_PAGE,
  }

  const dateFrom = filters.dateFrom.trim()
  if (dateFrom !== '') {
    params.date_from = dateFrom
  }

  const dateTo = filters.dateTo.trim()
  if (dateTo !== '') {
    params.date_to = dateTo
  }

  return params
}

async function loadBookings(page = pagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await bookingsStore.fetchStudentBookings(buildQueryParams(page))
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load bookings.'
  }
}

async function switchScope(nextScope: BookingScope): Promise<void> {
  if (scope.value === nextScope || isLoading.value) {
    return
  }

  scope.value = nextScope
  await loadBookings(1)
}

async function applyFilters(): Promise<void> {
  const validationError = validateFilters()
  if (validationError) {
    errorMessage.value = validationError
    return
  }

  await loadBookings(1)
}

async function clearFilters(): Promise<void> {
  filters.dateFrom = DEFAULT_FILTERS.dateFrom
  filters.dateTo = DEFAULT_FILTERS.dateTo
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadBookings(1)
}

async function goToPage(page: number): Promise<void> {
  if (isLoading.value || page < 1 || page > pagination.value.totalPages || page === pagination.value.page) {
    return
  }

  await loadBookings(page)
}

onMounted(() => {
  void loadBookings()
})
</script>

<template>
  <main class="page-shell">
    <section class="heading-row">
      <div>
        <h1>My Bookings</h1>
        <p class="subtitle">Review your upcoming sessions and booking history.</p>
      </div>
      <RouterLink to="/student/dashboard" class="back-btn">Back</RouterLink>
    </section>

    <p v-if="paymentMessage" class="feedback success">{{ paymentMessage }}</p>
    <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>

    <section class="tabs-row">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: scope === 'upcoming' }"
        @click="switchScope('upcoming')"
      >
        Upcoming
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: scope === 'history' }"
        @click="switchScope('history')"
      >
        History
      </button>
    </section>

    <section class="panel filters-panel">
      <form class="filters-form" @submit.prevent="applyFilters">
        <label class="field">
          <span>From</span>
          <input v-model="filters.dateFrom" type="date" />
        </label>

        <label class="field">
          <span>To</span>
          <input v-model="filters.dateTo" type="date" />
        </label>

        <label class="field">
          <span>Per page</span>
          <select v-model="filters.perPage">
            <option value="3">3</option>
            <option value="6">6</option>
            <option value="9">9</option>
            <option value="12">12</option>
          </select>
        </label>

        <div class="actions">
          <button type="button" class="ghost-btn" @click="clearFilters">Reset</button>
          <button type="submit" class="primary-btn">Apply</button>
        </div>
      </form>
    </section>

    <p v-if="isLoading" class="muted">Loading bookings...</p>

    <template v-else>
      <p class="results-summary">{{ resultsSummary }}</p>

      <nav v-if="pagination.totalPages > 1" class="pager">
        <button
          type="button"
          class="pager-btn"
          :disabled="!pagination.hasPrev"
          @click="goToPage(1)"
        >
          First
        </button>
        <button
          type="button"
          class="pager-btn"
          :disabled="!pagination.hasPrev"
          @click="goToPage(pagination.page - 1)"
        >
          Previous
        </button>

        <span class="pager-info">Page {{ pagination.page }} / {{ pagination.totalPages }}</span>

        <button
          type="button"
          class="pager-btn"
          :disabled="!pagination.hasNext"
          @click="goToPage(pagination.page + 1)"
        >
          Next
        </button>
        <button
          type="button"
          class="pager-btn"
          :disabled="!pagination.hasNext"
          @click="goToPage(pagination.totalPages)"
        >
          Last
        </button>
      </nav>

      <section class="booking-list">
        <article v-for="booking in bookings" :key="booking.id" class="booking-card">
          <div class="card-head">
            <div>
              <h2>{{ booking.service_title }}</h2>
              <p class="meta">
                {{ formatDate(booking.start_time) }} | {{ formatTime(booking.start_time) }} ->
                {{ formatTime(booking.end_time) }}
              </p>
              <p class="meta">Tutor: {{ booking.tutor_name }}</p>
              <p class="meta">Paid: EUR {{ formatPrice(booking.price_at_booking) }}</p>
            </div>

            <span class="status-badge" :class="statusClass(booking)">
              {{ statusLabel(booking) }}
            </span>
          </div>
        </article>

        <p v-if="bookings.length === 0" class="muted empty-message">
          {{
            scope === 'upcoming'
              ? 'No upcoming bookings in the selected range.'
              : 'No booking history in the selected range.'
          }}
        </p>
      </section>
    </template>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 980px;
  min-height: 72vh;
  padding: 0.6rem 0 1.5rem;
}

.heading-row {
  align-items: flex-start;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 1.1rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.55rem, 4vw, 2.25rem);
  margin-bottom: 0.18rem;
}

.subtitle {
  color: #884e1c;
  font-size: 0.97rem;
  font-weight: 600;
}

.tabs-row {
  align-items: center;
  border-bottom: 1px solid rgba(229, 176, 95, 0.4);
  display: flex;
  gap: 0.35rem;
  margin-bottom: 0.95rem;
}

.tab-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 9px 9px 0 0;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  opacity: 0.72;
  padding: 0.5rem 0.84rem;
  transition: opacity 0.18s ease;
}

.tab-btn:hover {
  opacity: 1;
}

.tab-btn.active {
  background: #fff;
  border-color: rgba(229, 176, 95, 0.45);
  border-bottom-color: #fff;
  opacity: 1;
}

.panel {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 51, 65, 0.07);
  margin-bottom: 0.9rem;
  padding: 0.9rem;
}

.filters-form {
  align-items: end;
  display: grid;
  gap: 0.6rem;
  grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
}

.field {
  display: grid;
  gap: 0.28rem;
}

.field span {
  color: #4f6270;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.field input,
.field select {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 8px;
  color: #0f3341;
  min-height: 2.15rem;
  padding: 0.44rem 0.55rem;
}

.field input:focus,
.field select:focus {
  border-color: #c57632;
  outline: none;
}

.actions {
  display: flex;
  gap: 0.45rem;
  justify-content: flex-end;
}

.primary-btn,
.ghost-btn {
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.86rem;
  font-weight: 700;
  min-height: 2.15rem;
  padding: 0.45rem 0.78rem;
}

.primary-btn {
  background: #0f3341;
  color: #fff;
}

.primary-btn:hover {
  background: #18475c;
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
  font-size: 0.9rem;
  margin-bottom: 0.72rem;
}

.pager {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 0.9rem;
}

.pager-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 7px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.38rem 0.62rem;
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

.booking-list {
  display: grid;
  gap: 0.9rem;
}

.booking-card {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 18px rgba(15, 51, 65, 0.08);
  padding: 1.05rem 1.1rem;
}

.card-head {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

.booking-card h2 {
  color: #0f3341;
  font-size: 1.08rem;
  line-height: 1.25;
  margin-bottom: 0.32rem;
}

.meta {
  color: #884e1c;
  font-size: 0.88rem;
  margin-bottom: 0.28rem;
}

.status-badge {
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.28rem 0.56rem;
  white-space: nowrap;
}

.status-paid {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}

.status-completed {
  background: #f9fafb;
  border-color: #e5e7eb;
  color: #374151;
}

.status-cancelled {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.9rem;
  padding: 0.68rem 0.82rem;
}

.feedback.error {
  background: #fff1f1;
  border: 1px solid #f2c6c6;
  color: #b42318;
}

.feedback.success {
  background: #ecfdf3;
  border: 1px solid #b7e7c8;
  color: #067647;
}

.empty-message {
  margin-top: 0.4rem;
}

.muted {
  color: #63727d;
  font-style: italic;
}

.back-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 8px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.46rem 0.76rem;
  text-decoration: none;
}

.back-btn:hover {
  background: #f6f8f9;
}

@media (max-width: 840px) {
  .filters-form {
    grid-template-columns: 1fr 1fr;
  }

  .actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }
}

@media (max-width: 760px) {
  .heading-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .filters-form {
    grid-template-columns: 1fr;
  }

  .card-head {
    flex-direction: column;
  }
}
</style>
