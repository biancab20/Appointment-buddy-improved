<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import ScopeTabs from '@/components/common/ScopeTabs.vue'
import type {
  BookingScope,
  RescheduleOption,
  StudentBooking,
  StudentBookingsQuery,
} from '@/stores/bookings'
import { useBookingsStore } from '@/stores/bookings'
import { dateKey, formatDate, formatTime, hoursUntil, isIsoDate, isPastDateTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface BookingFilters {
  dateFrom: string
  dateTo: string
  perPage: string
}

interface RescheduleState {
  open: boolean
  bookingId: number | null
  serviceTitle: string
  loading: boolean
  submitting: boolean
  error: string
  timeslots: RescheduleOption[]
  selectedDate: string
  selectedTimeslotId: number | null
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
const successMessage = ref('')
const actionLoadingByBooking = reactive<Record<number, boolean>>({})

const reschedule = reactive<RescheduleState>({
  open: false,
  bookingId: null,
  serviceTitle: '',
  loading: false,
  submitting: false,
  error: '',
  timeslots: [],
  selectedDate: '',
  selectedTimeslotId: null,
})

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

function statusLabel(booking: StudentBooking): string {
  if (booking.status === 'cancelled') {
    return 'Cancelled'
  }

  if (scope.value === 'history' && isPastDateTime(booking.start_time)) {
    return 'Completed'
  }

  return 'Paid'
}

function statusClass(booking: StudentBooking): string {
  if (booking.status === 'cancelled') {
    return 'status-cancelled'
  }

  if (scope.value === 'history' && isPastDateTime(booking.start_time)) {
    return 'status-completed'
  }

  return 'status-paid'
}

function policyHint(booking: StudentBooking): string {
  const hours = hoursUntil(booking.start_time)
  if (hours < 48) {
    return 'Less than 48h before session: reschedule is unavailable and cancellation does not qualify for refund.'
  }

  return 'More than 48h before session: you can reschedule or cancel with refund.'
}

function canManageBooking(booking: StudentBooking): boolean {
  return scope.value === 'upcoming' && booking.status === 'paid' && !isPastDateTime(booking.start_time)
}

function isActionLoading(bookingId: number): boolean {
  return actionLoadingByBooking[bookingId] === true
}

function setActionLoading(bookingId: number, loading: boolean): void {
  actionLoadingByBooking[bookingId] = loading
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
  successMessage.value = ''
  await loadBookings(1)
}

function onScopeTabChange(value: string): void {
  void switchScope(value as BookingScope)
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

function uniqueDateKeys(timeslots: RescheduleOption[]): string[] {
  return [...new Set(timeslots.map((slot) => dateKey(slot.start_time)))]
}

function rescheduleSlotsForDate(): RescheduleOption[] {
  if (!reschedule.selectedDate) {
    return reschedule.timeslots
  }

  return reschedule.timeslots.filter((slot) => dateKey(slot.start_time) === reschedule.selectedDate)
}

function nextThreeRescheduleSlots(): RescheduleOption[] {
  return reschedule.timeslots.slice(0, 3)
}

function selectRescheduleTimeslot(timeslotId: number): void {
  reschedule.selectedTimeslotId = timeslotId
  reschedule.error = ''
}

function selectNextRescheduleSlot(slot: RescheduleOption): void {
  reschedule.selectedDate = dateKey(slot.start_time)
  selectRescheduleTimeslot(slot.id)
}

function closeRescheduleModal(): void {
  reschedule.open = false
  reschedule.bookingId = null
  reschedule.serviceTitle = ''
  reschedule.loading = false
  reschedule.submitting = false
  reschedule.error = ''
  reschedule.timeslots = []
  reschedule.selectedDate = ''
  reschedule.selectedTimeslotId = null
}

async function openRescheduleModal(booking: StudentBooking): Promise<void> {
  if (isActionLoading(booking.id)) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  setActionLoading(booking.id, true)

  reschedule.open = true
  reschedule.bookingId = booking.id
  reschedule.serviceTitle = booking.service_title
  reschedule.loading = true
  reschedule.submitting = false
  reschedule.error = ''
  reschedule.timeslots = []
  reschedule.selectedDate = ''
  reschedule.selectedTimeslotId = null

  try {
    const options = await bookingsStore.fetchRescheduleOptions(booking.id)
    reschedule.timeslots = options

    if (options.length === 0) {
      reschedule.error = 'No alternative timeslots are currently available.'
      return
    }

    const firstOption = options[0]
    if (!firstOption) {
      reschedule.error = 'No alternative timeslots are currently available.'
      return
    }

    reschedule.selectedDate = dateKey(firstOption.start_time)
  } catch (error: unknown) {
    reschedule.error = error instanceof Error ? error.message : 'Unable to load reschedule options.'
  } finally {
    reschedule.loading = false
    setActionLoading(booking.id, false)
  }
}

async function submitReschedule(): Promise<void> {
  if (!reschedule.bookingId || !reschedule.selectedTimeslotId || reschedule.submitting) {
    return
  }

  reschedule.submitting = true
  reschedule.error = ''
  errorMessage.value = ''

  try {
    const message = await bookingsStore.rescheduleStudentBooking(
      reschedule.bookingId,
      reschedule.selectedTimeslotId,
    )

    successMessage.value = message
    closeRescheduleModal()
    await loadBookings(pagination.value.page)
  } catch (error: unknown) {
    reschedule.error = error instanceof Error ? error.message : 'Unable to reschedule booking.'
  } finally {
    reschedule.submitting = false
  }
}

async function cancelBooking(booking: StudentBooking): Promise<void> {
  if (isActionLoading(booking.id)) {
    return
  }

  const confirmed = window.confirm(
    'Cancel this booking? Refund depends on the 48-hour cancellation policy.',
  )
  if (!confirmed) {
    return
  }

  setActionLoading(booking.id, true)
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await bookingsStore.cancelStudentBooking(booking.id)
    successMessage.value = response.message
    await loadBookings(pagination.value.page)
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to cancel booking.'
  } finally {
    setActionLoading(booking.id, false)
  }
}

onMounted(() => {
  void loadBookings()
})
</script>

<template>
  <main class="page-shell">
    <PageHeader
      title="My Bookings"
      subtitle="Review your upcoming sessions and booking history."
      back-to="/student/dashboard"
    />

    <FeedbackMessage v-if="paymentMessage" :message="paymentMessage" type="success" />
    <FeedbackMessage v-if="successMessage" :message="successMessage" type="success" />
    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <ScopeTabs :model-value="scope" :disabled="isLoading" @update:model-value="onScopeTabChange" />

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

      <PaginationControls
        :page="pagination.page"
        :total-pages="pagination.totalPages"
        :has-prev="pagination.hasPrev"
        :has-next="pagination.hasNext"
        :disabled="isLoading"
        @go="goToPage"
      />

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

          <template v-if="canManageBooking(booking)">
            <p class="policy-hint">{{ policyHint(booking) }}</p>
            <div class="card-actions">
              <button
                type="button"
                class="action-btn action-outline"
                :disabled="isActionLoading(booking.id)"
                @click="openRescheduleModal(booking)"
              >
                {{
                  isActionLoading(booking.id) && reschedule.bookingId === booking.id
                    ? 'Loading...'
                    : 'Reschedule'
                }}
              </button>
              <button
                type="button"
                class="action-btn action-dark"
                :disabled="isActionLoading(booking.id)"
                @click="cancelBooking(booking)"
              >
                {{ isActionLoading(booking.id) ? 'Working...' : 'Cancel' }}
              </button>
            </div>
          </template>
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

    <div v-if="reschedule.open" class="modal-backdrop" @click.self="closeRescheduleModal">
      <section class="reschedule-modal" role="dialog" aria-modal="true" aria-labelledby="rescheduleTitle">
        <div class="modal-header">
          <div>
            <h2 id="rescheduleTitle" class="modal-title">Reschedule Booking</h2>
            <p class="modal-subtitle">{{ reschedule.serviceTitle }}</p>
          </div>
          <button type="button" class="close-btn" @click="closeRescheduleModal">Close</button>
        </div>

        <p v-if="reschedule.loading" class="muted">Loading alternative timeslots...</p>
        <p v-else-if="reschedule.error" class="feedback error inline">{{ reschedule.error }}</p>

        <template v-else>
          <div class="modal-grid">
            <div>
              <label class="label" for="reschedule-date">Choose a date</label>
              <select
                id="reschedule-date"
                v-model="reschedule.selectedDate"
                class="date-select"
              >
                <option
                  v-for="dateOption in uniqueDateKeys(reschedule.timeslots)"
                  :key="dateOption"
                  :value="dateOption"
                >
                  {{ formatDate(`${dateOption}T00:00`) }}
                </option>
              </select>

              <div class="timeslot-list">
                <p class="label">Available times</p>
                <button
                  v-for="slot in rescheduleSlotsForDate()"
                  :key="slot.id"
                  type="button"
                  class="timeslot-row selectable"
                  :class="{ selected: reschedule.selectedTimeslotId === slot.id }"
                  @click="selectRescheduleTimeslot(slot.id)"
                >
                  {{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}
                </button>
              </div>
            </div>

            <div>
              <p class="label">Next 3 available</p>
              <button
                v-for="slot in nextThreeRescheduleSlots()"
                :key="slot.id"
                type="button"
                class="next-row selectable"
                :class="{ selected: reschedule.selectedTimeslotId === slot.id }"
                @click="selectNextRescheduleSlot(slot)"
              >
                <div>{{ formatDate(slot.start_time) }}</div>
                <div>{{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}</div>
              </button>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="ghost-btn" @click="closeRescheduleModal">Cancel</button>
            <button
              type="button"
              class="primary-btn"
              :disabled="!reschedule.selectedTimeslotId || reschedule.submitting"
              @click="submitReschedule"
            >
              {{ reschedule.submitting ? 'Updating...' : 'Update timeslot' }}
            </button>
          </div>
        </template>
      </section>
    </div>
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

.primary-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
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

.policy-hint {
  color: #4d5f69;
  font-size: 0.82rem;
  margin-top: 0.5rem;
}

.card-actions {
  display: flex;
  gap: 0.45rem;
  margin-top: 0.55rem;
}

.action-btn {
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.4rem 0.74rem;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.action-outline {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.action-outline:hover {
  background: #f6f8f9;
}

.action-dark {
  background: #0f3341;
  color: #fff;
}

.action-dark:hover {
  background: #18475c;
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

.reschedule-modal {
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

.modal-subtitle {
  color: #884e1c;
  font-size: 0.9rem;
  margin-top: 0.15rem;
}

.close-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 8px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.4rem 0.7rem;
}

.close-btn:hover {
  background: #f6f8f9;
}

.modal-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr;
}

.label {
  color: #0f3341;
  display: block;
  font-size: 0.84rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}

.date-select {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 9px;
  margin-bottom: 0.7rem;
  padding: 0.5rem 0.58rem;
  width: 100%;
}

.timeslot-list {
  display: grid;
  gap: 0.38rem;
}

.timeslot-row,
.next-row {
  background: #fff;
  border: 1px solid #ecd9c6;
  border-radius: 9px;
  color: #4d5f69;
  font-size: 0.9rem;
  padding: 0.48rem 0.58rem;
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

.modal-actions {
  display: flex;
  gap: 0.45rem;
  justify-content: flex-end;
  margin-top: 0.9rem;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.9rem;
  padding: 0.68rem 0.82rem;
}

.feedback.inline {
  margin-bottom: 0.3rem;
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

  .modal-grid {
    grid-template-columns: 1fr;
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

  .card-actions {
    flex-wrap: wrap;
  }

  .modal-actions {
    justify-content: flex-start;
  }
}
</style>
