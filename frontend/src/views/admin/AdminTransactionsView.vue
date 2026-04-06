<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { onMounted, reactive, ref } from 'vue'

import AdminFiltersPanel from '@/components/admin/AdminFiltersPanel.vue'
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import type { AdminTransactionsQuery } from '@/stores/admin'
import { useAdminStore } from '@/stores/admin'
import { formatDateTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface Filters {
  status: '' | 'pending' | 'paid' | 'failed' | 'cancelled'
  provider: '' | 'stripe'
  currency: string
  studentId: string
  tutorId: string
  dateFrom: string
  dateTo: string
  perPage: string
}

const DEFAULT_FILTERS: Filters = {
  status: '',
  provider: '',
  currency: '',
  studentId: '',
  tutorId: '',
  dateFrom: '',
  dateTo: '',
  perPage: '10',
}

const adminStore = useAdminStore()
const { transactions, transactionsLoading, transactionsPagination } = storeToRefs(adminStore)

const filters = reactive<Filters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')

function parsePositiveNumber(value: string): number | undefined {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined
}

function buildQuery(page: number): AdminTransactionsQuery {
  return {
    page,
    per_page: Number(filters.perPage) || 10,
    status: filters.status || undefined,
    provider: filters.provider || undefined,
    currency: filters.currency.trim().toLowerCase() || undefined,
    student_id: parsePositiveNumber(filters.studentId),
    tutor_id: parsePositiveNumber(filters.tutorId),
    date_from: filters.dateFrom || undefined,
    date_to: filters.dateTo || undefined,
  }
}

async function loadTransactions(page = transactionsPagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await adminStore.fetchTransactions(buildQuery(page))
  } catch (error: unknown) {
    errorMessage.value =
      error instanceof Error ? error.message : 'Unable to load transactions overview.'
  }
}

async function applyFilters(): Promise<void> {
  await loadTransactions(1)
}

async function resetFilters(): Promise<void> {
  filters.status = DEFAULT_FILTERS.status
  filters.provider = DEFAULT_FILTERS.provider
  filters.currency = DEFAULT_FILTERS.currency
  filters.studentId = DEFAULT_FILTERS.studentId
  filters.tutorId = DEFAULT_FILTERS.tutorId
  filters.dateFrom = DEFAULT_FILTERS.dateFrom
  filters.dateTo = DEFAULT_FILTERS.dateTo
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadTransactions(1)
}

async function goToPage(page: number): Promise<void> {
  if (
    transactionsLoading.value ||
    page < 1 ||
    page > transactionsPagination.value.total_pages ||
    page === transactionsPagination.value.page
  ) {
    return
  }

  await loadTransactions(page)
}

onMounted(() => {
  void loadTransactions(1)
})
</script>

<template>
  <main class="page-shell">
    <PageHeader title="Admin Transactions" back-to="/admin/dashboard" />

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <AdminFiltersPanel
      :disabled="transactionsLoading"
      @apply="applyFilters"
      @reset="resetFilters"
    >
      <label class="field">
        <span>Status</span>
        <select v-model="filters.status">
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="paid">Paid</option>
          <option value="failed">Failed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </label>

      <label class="field">
        <span>Provider</span>
        <select v-model="filters.provider">
          <option value="">All</option>
          <option value="stripe">Stripe</option>
        </select>
      </label>

      <label class="field">
        <span>Currency</span>
        <input v-model="filters.currency" type="text" maxlength="10" placeholder="eur" />
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
      <p v-if="transactionsLoading" class="muted">Loading transactions...</p>

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Tutor</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
                <th>Booking</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in transactions" :key="transaction.id">
                <td>#{{ transaction.id }}</td>
                <td>{{ transaction.student_name }} (#{{ transaction.student_id }})</td>
                <td>{{ transaction.tutor_name }} (#{{ transaction.tutor_id }})</td>
                <td>
                  {{ transaction.currency.toUpperCase() }} {{ formatPrice(transaction.amount) }}
                </td>
                <td>
                  <span class="badge" :class="transaction.status">{{ transaction.status }}</span>
                </td>
                <td>{{ formatDateTime(transaction.created_at) }}</td>
                <td>{{ transaction.booking_id ? `#${transaction.booking_id}` : '-' }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="transactions.length === 0" class="muted empty">No transactions found.</p>
        </div>

        <PaginationControls
          :page="transactionsPagination.page"
          :total-pages="transactionsPagination.total_pages"
          :has-prev="transactionsPagination.has_prev"
          :has-next="transactionsPagination.has_next"
          :disabled="transactionsLoading"
          @go="goToPage"
        />
      </template>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 1120px;
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
  min-width: 840px;
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
}

.badge {
  border-radius: 999px;
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.22rem 0.5rem;
  text-transform: uppercase;
}

.badge.pending {
  background: #fff7e9;
  color: #8a5b13;
}

.badge.paid {
  background: #ecfdf3;
  color: #067647;
}

.badge.failed,
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
