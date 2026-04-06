<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { onMounted, reactive, ref } from 'vue'

import AdminFiltersPanel from '@/components/admin/AdminFiltersPanel.vue'
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import type { AdminBookingsQuery } from '@/stores/admin'
import { useAdminStore } from '@/stores/admin'
import { formatDateTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface Filters {
  scope: '' | 'upcoming' | 'history'
  status: '' | 'paid' | 'cancelled'
  studentId: string
  tutorId: string
  serviceId: string
  dateFrom: string
  dateTo: string
  perPage: string
}

const DEFAULT_FILTERS: Filters = {
  scope: '',
  status: '',
  studentId: '',
  tutorId: '',
  serviceId: '',
  dateFrom: '',
  dateTo: '',
  perPage: '10',
}

const adminStore = useAdminStore()
const { bookings, bookingsLoading, bookingsPagination } = storeToRefs(adminStore)

const filters = reactive<Filters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')

function parsePositiveNumber(value: string): number | undefined {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined
}

function buildQuery(page: number): AdminBookingsQuery {
  return {
    page,
    per_page: Number(filters.perPage) || 10,
    scope: filters.scope || undefined,
    status: filters.status || undefined,
    student_id: parsePositiveNumber(filters.studentId),
    tutor_id: parsePositiveNumber(filters.tutorId),
    service_id: parsePositiveNumber(filters.serviceId),
    date_from: filters.dateFrom || undefined,
    date_to: filters.dateTo || undefined,
  }
}

async function loadBookings(page = bookingsPagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await adminStore.fetchBookings(buildQuery(page))
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load bookings overview.'
  }
}

async function applyFilters(): Promise<void> {
  await loadBookings(1)
}

async function resetFilters(): Promise<void> {
  filters.scope = DEFAULT_FILTERS.scope
  filters.status = DEFAULT_FILTERS.status
  filters.studentId = DEFAULT_FILTERS.studentId
  filters.tutorId = DEFAULT_FILTERS.tutorId
  filters.serviceId = DEFAULT_FILTERS.serviceId
  filters.dateFrom = DEFAULT_FILTERS.dateFrom
  filters.dateTo = DEFAULT_FILTERS.dateTo
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadBookings(1)
}

async function goToPage(page: number): Promise<void> {
  if (
    bookingsLoading.value ||
    page < 1 ||
    page > bookingsPagination.value.total_pages ||
    page === bookingsPagination.value.page
  ) {
    return
  }

  await loadBookings(page)
}

onMounted(() => {
  void loadBookings(1)
})
</script>

<template>
  <main class="page-shell">
    <PageHeader title="Admin Bookings" back-to="/admin/dashboard" />

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <AdminFiltersPanel :disabled="bookingsLoading" @apply="applyFilters" @reset="resetFilters">
      <label class="field">
        <span>Scope</span>
        <select v-model="filters.scope">
          <option value="">All</option>
          <option value="upcoming">Upcoming</option>
          <option value="history">History</option>
        </select>
      </label>

      <label class="field">
        <span>Status</span>
        <select v-model="filters.status">
          <option value="">All</option>
          <option value="paid">Paid</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </label>

      <label class="field">
        <span>Student ID</span>
        <input v-model="filters.studentId" type="number" min="1" />
      </label>

      <label class="field">
        <span>Tutor ID</span>
        <input v-model="filters.tutorId" type="number" min="1" />
      </label>

      <label class="field">
        <span>Service ID</span>
        <input v-model="filters.serviceId" type="number" min="1" />
      </label>

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
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="30">30</option>
        </select>
      </label>
    </AdminFiltersPanel>

    <section class="panel">
      <p v-if="bookingsLoading" class="muted">Loading bookings...</p>

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Tutor</th>
                <th>Service</th>
                <th>Session</th>
                <th>Price</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="booking in bookings" :key="booking.id">
                <td>#{{ booking.id }}</td>
                <td>{{ booking.student_name }} (#{{ booking.student_id }})</td>
                <td>{{ booking.tutor_name }} (#{{ booking.tutor_id }})</td>
                <td>{{ booking.service_title }} (#{{ booking.service_id }})</td>
                <td>
                  <div>{{ formatDateTime(booking.start_time) }}</div>
                  <small>to {{ formatDateTime(booking.end_time) }}</small>
                </td>
                <td>€{{ formatPrice(booking.price_at_booking) }}</td>
                <td>
                  <span class="badge" :class="booking.status">
                    {{ booking.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-if="bookings.length === 0" class="muted empty">No bookings found.</p>
        </div>

        <PaginationControls
          :page="bookingsPagination.page"
          :total-pages="bookingsPagination.total_pages"
          :has-prev="bookingsPagination.has_prev"
          :has-next="bookingsPagination.has_next"
          :disabled="bookingsLoading"
          @go="goToPage"
        />
      </template>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 1140px;
  min-height: 72vh;
}

.panel {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 51, 65, 0.07);
  padding: 0.9rem;
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

.table-wrap {
  margin-bottom: 0.75rem;
  overflow-x: auto;
}

.table {
  border-collapse: collapse;
  min-width: 900px;
  width: 100%;
}

.table th,
.table td {
  border-bottom: 1px solid #ecf1f4;
  padding: 0.55rem 0.45rem;
  text-align: left;
}

.table th {
  color: #4f6270;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.table td {
  color: #22353f;
  font-size: 0.9rem;
  vertical-align: top;
}

.table small {
  color: #62727d;
}

.badge {
  border-radius: 999px;
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.22rem 0.5rem;
  text-transform: uppercase;
}

.badge.paid {
  background: #ecfdf3;
  color: #067647;
}

.badge.cancelled {
  background: #fff1f1;
  color: #b42318;
}

.muted {
  color: #63727d;
  font-style: italic;
}

.empty {
  margin-top: 0.6rem;
}
</style>
