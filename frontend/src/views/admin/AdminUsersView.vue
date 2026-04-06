<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { onMounted, reactive, ref } from 'vue'

import AdminFiltersPanel from '@/components/admin/AdminFiltersPanel.vue'
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationControls from '@/components/common/PaginationControls.vue'
import type { AdminUsersQuery } from '@/stores/admin'
import { useAdminStore } from '@/stores/admin'
import { formatDateTime } from '@/utils/dateTime'

interface Filters {
  role: '' | 'admin' | 'tutor' | 'student'
  search: string
  perPage: string
}

const DEFAULT_FILTERS: Filters = {
  role: '',
  search: '',
  perPage: '10',
}

const adminStore = useAdminStore()
const { users, usersLoading, usersPagination } = storeToRefs(adminStore)

const filters = reactive<Filters>({ ...DEFAULT_FILTERS })
const errorMessage = ref('')

function buildQuery(page: number): AdminUsersQuery {
  const perPage = Number(filters.perPage) || 10

  return {
    page,
    per_page: perPage,
    role: filters.role || undefined,
    search: filters.search.trim() || undefined,
  }
}

async function loadUsers(page = usersPagination.value.page): Promise<void> {
  errorMessage.value = ''

  try {
    await adminStore.fetchUsers(buildQuery(page))
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load users overview.'
  }
}

async function applyFilters(): Promise<void> {
  await loadUsers(1)
}

async function resetFilters(): Promise<void> {
  filters.role = DEFAULT_FILTERS.role
  filters.search = DEFAULT_FILTERS.search
  filters.perPage = DEFAULT_FILTERS.perPage
  await loadUsers(1)
}

async function goToPage(page: number): Promise<void> {
  if (
    usersLoading.value ||
    page < 1 ||
    page > usersPagination.value.total_pages ||
    page === usersPagination.value.page
  ) {
    return
  }

  await loadUsers(page)
}

onMounted(() => {
  void loadUsers(1)
})
</script>

<template>
  <main class="page-shell">
    <PageHeader title="Admin Users" back-to="/admin/dashboard" />

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />

    <AdminFiltersPanel :disabled="usersLoading" @apply="applyFilters" @reset="resetFilters">
      <label class="field">
        <span>Role</span>
        <select v-model="filters.role">
          <option value="">All</option>
          <option value="admin">Admin</option>
          <option value="tutor">Tutor</option>
          <option value="student">Student</option>
        </select>
      </label>

      <label class="field wide">
        <span>Search</span>
        <input v-model="filters.search" type="text" placeholder="Name or email" />
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
      <p v-if="usersLoading" class="muted">Loading users...</p>

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>{{ user.id }}</td>
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td>
                  <span class="badge" :class="`role-${user.role}`">{{ user.role }}</span>
                </td>
                <td>{{ formatDateTime(user.created_at) }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="users.length === 0" class="muted empty">No users found.</p>
        </div>

        <PaginationControls
          :page="usersPagination.page"
          :total-pages="usersPagination.total_pages"
          :has-prev="usersPagination.has_prev"
          :has-next="usersPagination.has_next"
          :disabled="usersLoading"
          @go="goToPage"
        />
      </template>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 1080px;
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
  min-width: 240px;
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
  min-width: 680px;
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

.role-admin {
  background: #ffeccf;
  color: #8a5516;
}

.role-tutor {
  background: #e7f3ff;
  color: #1f4f9a;
}

.role-student {
  background: #ecfdf3;
  color: #067647;
}

.muted {
  color: #63727d;
  font-style: italic;
}

.empty {
  margin-top: 0.6rem;
}
</style>
