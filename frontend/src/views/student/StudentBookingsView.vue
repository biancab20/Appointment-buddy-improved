<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import StudentBookingCard from '@/components/bookings/StudentBookingCard.vue'
import StudentRescheduleModal from '@/components/bookings/StudentRescheduleModal.vue'
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
import { dateKey, hoursUntil, isIsoDate, isPastDateTime } from '@/utils/dateTime'

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
  const tone = statusTone(booking)
  if (tone === 'cancelled') return 'Cancelled'
  if (tone === 'completed') return 'Completed'
  return 'Paid'
}

function statusTone(booking: StudentBooking): 'paid' | 'completed' | 'cancelled' {
  if (booking.status === 'cancelled') {
    return 'cancelled'
  }

  if (scope.value === 'history' && isPastDateTime(booking.start_time)) {
    return 'completed'
  }

  return 'paid'
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

function selectRescheduleTimeslot(timeslotId: number): void {
  reschedule.selectedTimeslotId = timeslotId
  reschedule.error = ''
}

function updateRescheduleDate(value: string): void {
  reschedule.selectedDate = value
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
        <StudentBookingCard
          v-for="booking in bookings"
          :key="booking.id"
          :booking="booking"
          :status-label="statusLabel(booking)"
          :status-tone="statusTone(booking)"
          :can-manage="canManageBooking(booking)"
          :policy-hint="policyHint(booking)"
          :action-loading="isActionLoading(booking.id)"
          :reschedule-loading="isActionLoading(booking.id) && reschedule.bookingId === booking.id"
          @reschedule="openRescheduleModal"
          @cancel="cancelBooking"
        />

        <p v-if="bookings.length === 0" class="muted empty-message">
          {{
            scope === 'upcoming'
              ? 'No upcoming bookings in the selected range.'
              : 'No booking history in the selected range.'
          }}
        </p>
      </section>
    </template>

    <StudentRescheduleModal
      :open="reschedule.open"
      :service-title="reschedule.serviceTitle"
      :loading="reschedule.loading"
      :error-message="reschedule.error"
      :submitting="reschedule.submitting"
      :timeslots="reschedule.timeslots"
      :selected-date="reschedule.selectedDate"
      :selected-timeslot-id="reschedule.selectedTimeslotId"
      @close="closeRescheduleModal"
      @update:selected-date="updateRescheduleDate"
      @select-timeslot="selectRescheduleTimeslot"
      @submit="submitReschedule"
    />
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 980px;
  min-height: 72vh;
  padding: 0.6rem 0 1.5rem;
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

.booking-list {
  display: grid;
  gap: 0.9rem;
}

.empty-message {
  margin-top: 0.4rem;
}

.muted {
  color: #63727d;
  font-style: italic;
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
  .filters-form {
    grid-template-columns: 1fr;
  }
}
</style>
