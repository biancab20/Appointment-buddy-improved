<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import ScopeTabs from '@/components/common/ScopeTabs.vue'
import type {
  TutorBooking,
  TutorBookingScope,
  TutorBookingsQuery,
} from '@/stores/tutorBookings'
import { useTutorBookingsStore } from '@/stores/tutorBookings'
import {
  formatDate,
  formatIsoDate,
  formatMonthYear,
  formatTime,
  isIsoDate,
  isPastDateTime,
} from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface TutorFilters {
  dateFrom: string
  dateTo: string
  perPage: string
}

interface CalendarCell {
  key: string
  date: string | null
  day: number | null
  count: number
}

const DEFAULT_PER_PAGE = 6
const DEFAULT_FILTERS: TutorFilters = {
  dateFrom: '',
  dateTo: '',
  perPage: String(DEFAULT_PER_PAGE),
}

const tutorBookingsStore = useTutorBookingsStore()
const { bookings, dateCounts, loading, pagination } = storeToRefs(tutorBookingsStore)

const scope = ref<TutorBookingScope>('upcoming')
const filters = reactive<TutorFilters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')
const successMessage = ref('')
const actionLoadingByBooking = reactive<Record<number, boolean>>({})
const calendarMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

const monthLabel = computed(() => formatMonthYear(calendarMonth.value))

const dateCountMap = computed<Record<string, number>>(() => {
  const map: Record<string, number> = {}
  for (const entry of dateCounts.value) {
    map[entry.date] = Number(entry.count ?? 0)
  }
  return map
})

const resultsSummary = computed(() => {
  if (pagination.value.total <= 0 || bookings.value.length === 0) {
    return scope.value === 'upcoming' ? 'No upcoming bookings found.' : 'No booking history found.'
  }

  const start = (pagination.value.page - 1) * pagination.value.per_page + 1
  const end = start + bookings.value.length - 1
  return `Showing ${start}-${end} of ${pagination.value.total} bookings`
})

const calendarCells = computed<CalendarCell[]>(() => {
  const year = calendarMonth.value.getFullYear()
  const monthIndex = calendarMonth.value.getMonth()
  const firstDay = new Date(year, monthIndex, 1)
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()
  const mondayOffset = (firstDay.getDay() + 6) % 7
  const totalCells = Math.ceil((mondayOffset + daysInMonth) / 7) * 7

  const cells: CalendarCell[] = []
  for (let index = 0; index < totalCells; index += 1) {
    const dayNumber = index - mondayOffset + 1
    if (dayNumber < 1 || dayNumber > daysInMonth) {
      cells.push({
        key: `empty-${index}`,
        date: null,
        day: null,
        count: 0,
      })
      continue
    }

    const date = new Date(year, monthIndex, dayNumber)
    const key = formatIsoDate(date)
    cells.push({
      key,
      date: key,
      day: dayNumber,
      count: dateCountMap.value[key] ?? 0,
    })
  }

  return cells
})

function statusLabel(booking: TutorBooking): string {
  if (booking.status === 'cancelled') {
    return 'Cancelled'
  }

  if (scope.value === 'history' && isPastDateTime(booking.start_time)) {
    return 'Completed'
  }

  return 'Paid'
}

function statusClass(booking: TutorBooking): string {
  if (booking.status === 'cancelled') {
    return 'status-cancelled'
  }

  if (scope.value === 'history' && isPastDateTime(booking.start_time)) {
    return 'status-completed'
  }

  return 'status-paid'
}

function canTutorCancel(booking: TutorBooking): boolean {
  return scope.value === 'upcoming' && booking.status === 'paid' && !isPastDateTime(booking.start_time)
}

function isActionLoading(bookingId: number): boolean {
  return actionLoadingByBooking[bookingId] === true
}

function setActionLoading(bookingId: number, loadingState: boolean): void {
  actionLoadingByBooking[bookingId] = loadingState
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

function buildQueryParams(page: number): TutorBookingsQuery {
  const params: TutorBookingsQuery = {
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

async function loadCalendar(): Promise<void> {
  try {
    await tutorBookingsStore.fetchTutorCalendarCounts({
      scope: scope.value,
      year: calendarMonth.value.getFullYear(),
      month: calendarMonth.value.getMonth() + 1,
    })
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load calendar bookings.'
  }
}

async function loadBookings(page = pagination.value.page): Promise<void> {
  try {
    await tutorBookingsStore.fetchTutorBookings(buildQueryParams(page))
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load bookings overview.'
  }
}

async function loadAll(page = pagination.value.page): Promise<void> {
  errorMessage.value = ''
  await Promise.all([loadBookings(page), loadCalendar()])
}

async function switchScope(nextScope: TutorBookingScope): Promise<void> {
  if (scope.value === nextScope || loading.value) {
    return
  }

  scope.value = nextScope
  successMessage.value = ''
  await loadAll(1)
}

function onScopeTabChange(value: string): void {
  void switchScope(value as TutorBookingScope)
}

async function applyFilters(): Promise<void> {
  const validationError = validateFilters()
  if (validationError) {
    errorMessage.value = validationError
    return
  }

  await loadAll(1)
}

async function clearFilters(): Promise<void> {
  filters.dateFrom = DEFAULT_FILTERS.dateFrom
  filters.dateTo = DEFAULT_FILTERS.dateTo
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadAll(1)
}

async function goToPage(page: number): Promise<void> {
  if (loading.value || page < 1 || page > pagination.value.total_pages || page === pagination.value.page) {
    return
  }

  await loadBookings(page)
}

async function moveCalendarMonth(offset: number): Promise<void> {
  calendarMonth.value = new Date(
    calendarMonth.value.getFullYear(),
    calendarMonth.value.getMonth() + offset,
    1,
  )
  await loadCalendar()
}

async function selectCalendarDate(date: string): Promise<void> {
  filters.dateFrom = date
  filters.dateTo = date
  await loadAll(1)
}

async function cancelTutorBooking(booking: TutorBooking): Promise<void> {
  if (!canTutorCancel(booking) || isActionLoading(booking.id)) {
    return
  }

  const confirmed = window.confirm(
    'Cancel this booking? The student will be eligible for a full refund.',
  )
  if (!confirmed) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  setActionLoading(booking.id, true)

  try {
    const result = await tutorBookingsStore.cancelTutorBooking(booking.id)
    successMessage.value = result.message
    await loadAll(pagination.value.page)
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to cancel booking.'
  } finally {
    setActionLoading(booking.id, false)
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <main class="page-shell">
    <PageHeader title="Tutor Bookings" back-to="/tutor/dashboard" />

    <FeedbackMessage v-if="successMessage" :message="successMessage" type="success" />
    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <ScopeTabs :model-value="scope" :disabled="loading" @update:model-value="onScopeTabChange" />

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
            <option value="6">6</option>
            <option value="9">9</option>
            <option value="12">12</option>
            <option value="18">18</option>
          </select>
        </label>

        <div class="actions">
          <button type="button" class="ghost-btn" @click="clearFilters">Reset</button>
          <button type="submit" class="primary-btn">Apply</button>
        </div>
      </form>
    </section>

    <section class="split-grid">
      <article class="panel calendar-panel">
        <header class="calendar-head">
          <button type="button" class="nav-btn" @click="moveCalendarMonth(-1)">Previous</button>
          <h2>{{ monthLabel }}</h2>
          <button type="button" class="nav-btn" @click="moveCalendarMonth(1)">Next</button>
        </header>

        <div class="weekday-row">
          <span>Mon</span>
          <span>Tue</span>
          <span>Wed</span>
          <span>Thu</span>
          <span>Fri</span>
          <span>Sat</span>
          <span>Sun</span>
        </div>

        <div class="calendar-grid">
          <button
            v-for="cell in calendarCells"
            :key="cell.key"
            type="button"
            class="calendar-cell"
            :class="{ empty: !cell.date, hasBooking: cell.count > 0 }"
            :disabled="!cell.date"
            @click="cell.date ? selectCalendarDate(cell.date) : undefined"
          >
            <span v-if="cell.day !== null" class="day">{{ cell.day }}</span>
            <span v-if="cell.count > 0" class="dot"></span>
          </button>
        </div>

        <p class="calendar-help">Click a day with a dot to filter list bookings for that date.</p>
      </article>

      <article class="panel list-panel">
        <h2>Bookings List</h2>
        <p class="results-summary">{{ resultsSummary }}</p>

        <p class="policy-note">
          Tutors can cancel bookings but cannot reschedule them. If a tutor cancels, the student gets a full refund.
        </p>

        <p v-if="loading" class="muted">Loading bookings...</p>

        <template v-else>
          <section class="booking-list">
            <article v-for="booking in bookings" :key="booking.id" class="booking-card">
              <div class="card-head">
                <div>
                  <h3>{{ booking.service_title }}</h3>
                  <p class="meta">
                    {{ formatDate(booking.start_time) }} | {{ formatTime(booking.start_time) }} ->
                    {{ formatTime(booking.end_time) }}
                  </p>
                  <p class="meta">Student: {{ booking.student_name }} ({{ booking.student_email }})</p>
                  <p class="meta">Paid: EUR {{ formatPrice(booking.price_at_booking) }}</p>
                </div>

                <span class="status-badge" :class="statusClass(booking)">
                  {{ statusLabel(booking) }}
                </span>
              </div>

              <div v-if="canTutorCancel(booking)" class="card-actions">
                <button
                  type="button"
                  class="action-btn"
                  :disabled="isActionLoading(booking.id)"
                  @click="cancelTutorBooking(booking)"
                >
                  {{ isActionLoading(booking.id) ? 'Working...' : 'Cancel Booking' }}
                </button>
              </div>
            </article>

            <p v-if="bookings.length === 0" class="muted empty-message">
              {{
                scope === 'upcoming'
                  ? 'No upcoming tutor bookings in the selected range.'
                  : 'No tutor booking history in the selected range.'
              }}
            </p>
          </section>

          <PaginationControls
            :page="pagination.page"
            :total-pages="pagination.total_pages"
            :has-prev="pagination.has_prev"
            :has-next="pagination.has_next"
            :disabled="loading"
            @go="goToPage"
          />
        </template>
      </article>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 1040px;
  min-height: 72vh;
}

.heading-row {
  align-items: flex-start;
  display: flex;
  justify-content: space-between;
  margin-bottom: 1rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.6rem, 4vw, 2.2rem);
  margin-bottom: 0.2rem;
}

.subtitle {
  color: #884e1c;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.75rem;
  padding: 0.66rem 0.8rem;
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

.tabs-row {
  border-bottom: 1px solid rgba(229, 176, 95, 0.4);
  display: flex;
  gap: 0.35rem;
  margin-bottom: 0.9rem;
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
  padding: 0.48rem 0.82rem;
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
  padding: 0.9rem;
}

.filters-panel {
  margin-bottom: 0.9rem;
}

.filters-form {
  align-items: end;
  display: grid;
  gap: 0.6rem;
  grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
}

.field {
  display: grid;
  gap: 0.26rem;
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

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.split-grid {
  display: grid;
  gap: 0.9rem;
  grid-template-columns: 0.95fr 1.35fr;
}

.calendar-head {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.7rem;
}

.calendar-head h2 {
  color: #0f3341;
  font-size: 1.02rem;
}

.nav-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 7px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.34rem 0.56rem;
}

.weekday-row {
  color: #5c6f7a;
  display: grid;
  font-size: 0.74rem;
  font-weight: 700;
  grid-template-columns: repeat(7, 1fr);
  margin-bottom: 0.35rem;
  text-align: center;
  text-transform: uppercase;
}

.calendar-grid {
  display: grid;
  gap: 0.28rem;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-cell {
  align-items: center;
  background: #fff;
  border: 1px solid #e7edf1;
  border-radius: 8px;
  cursor: pointer;
  display: grid;
  min-height: 2.6rem;
  place-items: center;
  position: relative;
}

.calendar-cell.empty {
  background: #fafcfd;
  cursor: default;
}

.calendar-cell.hasBooking {
  border-color: #c57632;
}

.day {
  color: #0f3341;
  font-size: 0.82rem;
  font-weight: 700;
}

.dot {
  background: #c57632;
  border-radius: 999px;
  bottom: 0.34rem;
  height: 0.35rem;
  position: absolute;
  width: 0.35rem;
}

.calendar-help {
  color: #5d707c;
  font-size: 0.82rem;
  margin-top: 0.6rem;
}

.list-panel h2 {
  color: #0f3341;
  font-size: 1.04rem;
  margin-bottom: 0.3rem;
}

.results-summary {
  color: #4d5f69;
  font-size: 0.9rem;
  margin-bottom: 0.4rem;
}

.policy-note {
  background: #f8fafb;
  border: 1px solid #dce6eb;
  border-radius: 8px;
  color: #43545f;
  font-size: 0.82rem;
  margin-bottom: 0.6rem;
  padding: 0.48rem 0.58rem;
}

.booking-list {
  display: grid;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.booking-card {
  border: 1px solid rgba(229, 176, 95, 0.38);
  border-radius: 10px;
  padding: 0.85rem 0.9rem;
}

.card-head {
  display: flex;
  justify-content: space-between;
  gap: 0.8rem;
}

.booking-card h3 {
  color: #0f3341;
  font-size: 1rem;
  margin-bottom: 0.2rem;
}

.meta {
  color: #884e1c;
  font-size: 0.84rem;
  margin-bottom: 0.24rem;
}

.status-badge {
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  padding: 0.24rem 0.52rem;
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

.card-actions {
  margin-top: 0.5rem;
}

.action-btn {
  background: #0f3341;
  border: none;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.4rem 0.7rem;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.pager {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.pager-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 7px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.36rem 0.58rem;
}

.pager-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.pager-info {
  color: #4d5f69;
  font-size: 0.84rem;
  font-weight: 700;
}

.empty-message,
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

@media (max-width: 940px) {
  .split-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .heading-row {
    flex-direction: column;
  }

  .filters-form {
    grid-template-columns: 1fr;
  }

  .actions {
    justify-content: flex-start;
  }

  .card-head {
    flex-direction: column;
  }
}
</style>
