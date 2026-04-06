<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AdminStatCard from '@/components/admin/AdminStatCard.vue'
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import { useAdminStore } from '@/stores/admin'

const adminStore = useAdminStore()
const {
  bookingsPagination,
  servicesPagination,
  transactionsPagination,
  usersPagination,
} = storeToRefs(adminStore)

const isLoading = ref(true)
const errorMessage = ref('')

const cards = computed(() => [
  {
    title: 'Users',
    value: usersPagination.value.total,
    subtitle: 'Students, tutors, and admins',
  },
  {
    title: 'Services',
    value: servicesPagination.value.total,
    subtitle: 'All tutor services',
  },
  {
    title: 'Bookings',
    value: bookingsPagination.value.total,
    subtitle: 'Paid and cancelled sessions',
  },
  {
    title: 'Transactions',
    value: transactionsPagination.value.total,
    subtitle: 'Payment flow records',
  },
])

async function loadSummary(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    await Promise.all([
      adminStore.fetchUsers({ page: 1, per_page: 1 }),
      adminStore.fetchServices({ page: 1, per_page: 1 }),
      adminStore.fetchBookings({ page: 1, per_page: 1 }),
      adminStore.fetchTransactions({ page: 1, per_page: 1 }),
    ])
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load dashboard summary.'
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
      <h1>Admin Dashboard</h1>
      <p class="summary">Overview of users, services, bookings, and transactions.</p>

      <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />
      <p v-else-if="isLoading" class="muted">Loading summary...</p>

      <section v-else class="stats-grid">
        <AdminStatCard
          v-for="card in cards"
          :key="card.title"
          :title="card.title"
          :value="card.value"
          :subtitle="card.subtitle"
        />
      </section>

      <section class="quick-links">
        <RouterLink to="/admin/users" class="link-card">Manage Users</RouterLink>
        <RouterLink to="/admin/services" class="link-card">Browse Services</RouterLink>
        <RouterLink to="/admin/bookings" class="link-card">Review Bookings</RouterLink>
        <RouterLink to="/admin/transactions" class="link-card">Inspect Transactions</RouterLink>
      </section>
    </section>
  </main>
</template>

<style scoped>
.dashboard-page {
  margin: 0 auto;
  max-width: 1040px;
  min-height: 72vh;
}

.dashboard-shell {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(15, 51, 65, 0.08);
  padding: 1rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 4vw, 2.1rem);
  margin-bottom: 0.5rem;
}

.summary {
  color: #53646e;
  margin-bottom: 0.9rem;
}

.muted {
  color: #63727d;
  font-style: italic;
}

.stats-grid {
  display: grid;
  gap: 0.8rem;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 1rem;
}

.quick-links {
  display: grid;
  gap: 0.7rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.link-card {
  background: #f8fafb;
  border: 1px solid #dce6eb;
  border-radius: 10px;
  color: #0f3341;
  display: block;
  font-weight: 700;
  padding: 0.85rem 0.95rem;
  text-decoration: none;
}

.link-card:hover {
  border-color: #c57632;
}

@media (max-width: 900px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 680px) {
  .quick-links,
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
