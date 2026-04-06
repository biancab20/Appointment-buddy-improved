<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { onMounted, reactive, ref } from 'vue'

import AdminFiltersPanel from '@/components/admin/AdminFiltersPanel.vue'
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import type { AdminServicesQuery } from '@/stores/admin'
import { useAdminStore } from '@/stores/admin'
import { formatDateTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

interface Filters {
  subject: string
  tutorId: string
  isActive: '' | 'true' | 'false'
  minPrice: string
  maxPrice: string
  perPage: string
}

const DEFAULT_FILTERS: Filters = {
  subject: '',
  tutorId: '',
  isActive: '',
  minPrice: '',
  maxPrice: '',
  perPage: '10',
}

const adminStore = useAdminStore()
const { services, servicesLoading, servicesPagination } = storeToRefs(adminStore)

const filters = reactive<Filters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')

function parsePositiveNumber(value: string): number | undefined {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined
}

function buildQuery(page: number): AdminServicesQuery {
  return {
    page,
    per_page: Number(filters.perPage) || 10,
    subject: filters.subject.trim() || undefined,
    tutor_id: parsePositiveNumber(filters.tutorId),
    is_active: filters.isActive === '' ? undefined : filters.isActive === 'true',
    min_price: parsePositiveNumber(filters.minPrice),
    max_price: parsePositiveNumber(filters.maxPrice),
  }
}

async function loadServices(page = servicesPagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await adminStore.fetchServices(buildQuery(page))
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load services overview.'
  }
}

async function applyFilters(): Promise<void> {
  await loadServices(1)
}

async function resetFilters(): Promise<void> {
  filters.subject = DEFAULT_FILTERS.subject
  filters.tutorId = DEFAULT_FILTERS.tutorId
  filters.isActive = DEFAULT_FILTERS.isActive
  filters.minPrice = DEFAULT_FILTERS.minPrice
  filters.maxPrice = DEFAULT_FILTERS.maxPrice
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadServices(1)
}

async function goToPage(page: number): Promise<void> {
  if (
    servicesLoading.value ||
    page < 1 ||
    page > servicesPagination.value.total_pages ||
    page === servicesPagination.value.page
  ) {
    return
  }

  await loadServices(page)
}

onMounted(() => {
  void loadServices(1)
})
</script>

<template>
  <main class="page-shell">
    <PageHeader title="Admin Services" back-to="/admin/dashboard" />

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <AdminFiltersPanel :disabled="servicesLoading" @apply="applyFilters" @reset="resetFilters">
      <label class="field wide">
        <span>Subject</span>
        <input v-model="filters.subject" type="text" placeholder="Service title" />
      </label>

      <label class="field">
        <span>Tutor ID</span>
        <input v-model="filters.tutorId" type="number" min="1" placeholder="Any" />
      </label>

      <label class="field">
        <span>Status</span>
        <select v-model="filters.isActive">
          <option value="">All</option>
          <option value="true">Active</option>
          <option value="false">Inactive</option>
        </select>
      </label>

      <label class="field">
        <span>Min Price</span>
        <input v-model="filters.minPrice" type="number" min="0" step="0.01" />
      </label>

      <label class="field">
        <span>Max Price</span>
        <input v-model="filters.maxPrice" type="number" min="0" step="0.01" />
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
      <p v-if="servicesLoading" class="muted">Loading services...</p>

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Tutor</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="service in services" :key="service.id">
                <td>{{ service.id }}</td>
                <td>{{ service.title }}</td>
                <td>{{ service.tutor_name || `Tutor #${service.tutor_id}` }}</td>
                <td>{{ service.duration_minutes }} min</td>
                <td>€{{ formatPrice(service.price) }}</td>
                <td>
                  <span class="badge" :class="service.is_active ? 'active' : 'inactive'">
                    {{ service.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>{{ formatDateTime(service.created_at) }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="services.length === 0" class="muted empty">No services found.</p>
        </div>

        <PaginationControls
          :page="servicesPagination.page"
          :total-pages="servicesPagination.total_pages"
          :has-prev="servicesPagination.has_prev"
          :has-next="servicesPagination.has_next"
          :disabled="servicesLoading"
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

.field.wide {
  min-width: 220px;
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
  min-width: 780px;
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

.badge.active {
  background: #ecfdf3;
  color: #067647;
}

.badge.inactive {
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
