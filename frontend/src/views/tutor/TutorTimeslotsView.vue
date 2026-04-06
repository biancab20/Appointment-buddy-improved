<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import type { TutorTimeslot } from '@/stores/timeslots'
import { useTimeslotsStore } from '@/stores/timeslots'

const route = useRoute()
const serviceId = computed(() => Number(route.params.id ?? 0))

const timeslotsStore = useTimeslotsStore()
const { tutorLoading, tutorService, tutorTimeslots } = storeToRefs(timeslotsStore)

const service = computed(() => tutorService.value)
const timeslots = computed(() => tutorTimeslots.value)
const isLoading = computed(() => tutorLoading.value)
const errorMessage = ref('')
const successMessage = ref('')

const showCreateForm = ref(false)
const createForm = reactive({
  start_time: '',
})

const editingTimeslotId = ref<number | null>(null)
const editForm = reactive({
  start_time: '',
})

function isActive(value: number | boolean): boolean {
  return value === 1 || value === true
}

function isPast(value: string): boolean {
  const start = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(start.getTime())) {
    return false
  }

  return start.getTime() <= Date.now()
}

function toInputDateTime(value: string): string {
  const parsed = new Date(value.replace(' ', 'T'))
  if (!Number.isNaN(parsed.getTime())) {
    const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, 16)
  }

  const fallback = value.trim().replace(' ', 'T')
  return fallback.length >= 16 ? fallback.slice(0, 16) : ''
}

function formatHumanDateTime(value: string): string {
  const parsed = new Date(value.replace(' ', 'T'))
  if (!Number.isNaN(parsed.getTime())) {
    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  }

  return value
}

function resetCreateForm(): void {
  createForm.start_time = ''
}

function startEdit(timeslot: TutorTimeslot): void {
  editingTimeslotId.value = timeslot.id
  editForm.start_time = toInputDateTime(timeslot.start_time)
}

function cancelEdit(): void {
  editingTimeslotId.value = null
}

async function loadTimeslots(): Promise<void> {
  errorMessage.value = ''

  if (!Number.isInteger(serviceId.value) || serviceId.value <= 0) {
    errorMessage.value = 'Invalid service id.'
    return
  }

  try {
    await timeslotsStore.fetchTutorServiceTimeslots(serviceId.value)
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load timeslots.'
  }
}

async function createTimeslot(): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await timeslotsStore.createTutorTimeslot(serviceId.value, createForm.start_time)

    successMessage.value = 'Timeslot created.'
    showCreateForm.value = false
    resetCreateForm()
    await loadTimeslots()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to create timeslot.'
  }
}

async function updateTimeslot(timeslotId: number): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await timeslotsStore.updateTutorTimeslot(timeslotId, editForm.start_time)

    successMessage.value = 'Timeslot updated.'
    editingTimeslotId.value = null
    await loadTimeslots()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update timeslot.'
  }
}

async function deactivateTimeslot(timeslotId: number): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  const shouldContinue = window.confirm(
    'Deactivate this timeslot? Paid bookings for this slot will be cancelled.',
  )
  if (!shouldContinue) {
    return
  }

  try {
    const cancelled = await timeslotsStore.deactivateTutorTimeslot(timeslotId)

    successMessage.value =
      cancelled > 0
        ? `Timeslot deactivated. ${cancelled} booking(s) were automatically cancelled.`
        : 'Timeslot deactivated.'

    await loadTimeslots()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to deactivate timeslot.'
  }
}

onMounted(() => {
  void loadTimeslots()
})

function formatPreviewDateTime(value: Date): string {
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(value)
}

function calculatedEndPreview(startValue: string): string {
  if (!service.value || !startValue) {
    return 'Select a start time'
  }

  const startDate = new Date(startValue)
  if (Number.isNaN(startDate.getTime())) {
    return 'Invalid start time'
  }

  const durationMinutes = Number(service.value.duration_minutes ?? 0)
  if (durationMinutes <= 0) {
    return 'Invalid service duration'
  }

  const endDate = new Date(startDate.getTime() + durationMinutes * 60_000)
  return formatPreviewDateTime(endDate)
}
</script>

<template>
  <main class="page-shell">
    <section class="heading-row">
      <div>
        <h1>Timeslots</h1>
        <p class="subtitle">
          Service:
          <strong>{{ service?.title ?? '...' }}</strong>
        </p>
      </div>
      <RouterLink to="/tutor/services" class="back-btn">Back to services</RouterLink>
    </section>

    <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="feedback success">{{ successMessage }}</p>

    <p v-if="service && !isActive(service.is_active)" class="feedback warning">
      This service is inactive. You cannot add or edit timeslots.
    </p>

    <section class="create-wrap">
      <button
        type="button"
        class="primary-btn"
        :disabled="service ? !isActive(service.is_active) : true"
        @click="showCreateForm = !showCreateForm"
      >
        {{ showCreateForm ? 'Close form' : 'Add timeslot' }}
      </button>

      <form v-if="showCreateForm" class="panel create-panel" @submit.prevent="createTimeslot">
        <label class="field wide">
          <span>Start</span>
          <input v-model="createForm.start_time" type="datetime-local" required />
        </label>

        <p class="auto-end">End time (auto): {{ calculatedEndPreview(createForm.start_time) }}</p>

        <div class="form-actions">
          <button type="button" class="ghost-btn" @click="showCreateForm = false">Cancel</button>
          <button type="submit" class="primary-btn">Create</button>
        </div>
      </form>
    </section>

    <p v-if="isLoading" class="muted">Loading timeslots...</p>

    <section v-else class="timeslot-list">
      <article v-for="timeslot in timeslots" :key="timeslot.id" class="panel timeslot-card">
        <template v-if="editingTimeslotId !== timeslot.id">
          <div class="timeslot-header">
            <div>
              <div class="title-row">
                <h2>
                  {{ formatHumanDateTime(timeslot.start_time) }} -> {{ formatHumanDateTime(timeslot.end_time) }}
                </h2>
                <span v-if="isActive(timeslot.is_active)" class="badge active">Active</span>
                <span v-else class="badge inactive">Inactive</span>
                <span v-if="isPast(timeslot.start_time)" class="badge neutral">Past</span>
              </div>
              <p class="meta">Timeslot ID: {{ timeslot.id }}</p>
            </div>

            <div class="actions">
              <button
                type="button"
                class="ghost-btn"
                :disabled="!service || !isActive(service.is_active) || isPast(timeslot.start_time)"
                @click="startEdit(timeslot)"
              >
                Edit
              </button>
              <button
                v-if="isActive(timeslot.is_active)"
                type="button"
                class="danger-btn"
                @click="deactivateTimeslot(timeslot.id)"
              >
                Deactivate
              </button>
            </div>
          </div>
        </template>

        <form v-else class="edit-grid" @submit.prevent="updateTimeslot(timeslot.id)">
          <label class="field wide">
            <span>Start</span>
            <input v-model="editForm.start_time" type="datetime-local" required />
          </label>

          <p class="auto-end">End time (auto): {{ calculatedEndPreview(editForm.start_time) }}</p>

          <div class="form-actions">
            <button type="button" class="ghost-btn" @click="cancelEdit">Cancel</button>
            <button type="submit" class="primary-btn">Save changes</button>
          </div>
        </form>
      </article>

      <p v-if="timeslots.length === 0" class="muted">No timeslots yet. Add your first timeslot.</p>
    </section>
  </main>
</template>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 980px;
  min-height: 72vh;
}

.heading-row {
  align-items: flex-start;
  display: flex;
  gap: 0.9rem;
  justify-content: space-between;
  margin-bottom: 0.9rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.55rem, 4vw, 2.2rem);
  line-height: 1.2;
  margin-bottom: 0.2rem;
}

.subtitle {
  color: #884e1c;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.7rem;
  padding: 0.65rem 0.8rem;
}

.feedback.error {
  background: #fff1f1;
  border: 1px solid #f2c6c6;
  color: #b42318;
}

.feedback.success {
  background: #eefbf1;
  border: 1px solid #bfe6c8;
  color: #217348;
}

.feedback.warning {
  background: #fff7e9;
  border: 1px solid #f2d7a8;
  color: #8a5b13;
}

.create-wrap {
  margin-bottom: 0.9rem;
}

.panel {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 14px;
  box-shadow: 0 12px 26px rgba(15, 51, 65, 0.07);
}

.create-panel,
.edit-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: 0.75rem;
  padding: 0.9rem;
}

.timeslot-list {
  display: grid;
  gap: 0.8rem;
}

.timeslot-card {
  padding: 0.95rem;
}

.timeslot-header {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

.title-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 0.25rem;
}

h2 {
  color: #0f3341;
  font-size: 1.07rem;
}

.badge {
  border-radius: 999px;
  font-size: 0.73rem;
  font-weight: 700;
  padding: 0.17rem 0.45rem;
}

.badge.active {
  background: #ecf9ef;
  border: 1px solid #c8e7d1;
  color: #1f7a46;
}

.badge.inactive {
  background: #f6f7f8;
  border: 1px solid #d5dbe0;
  color: #56636d;
}

.badge.neutral {
  background: #f2f3f4;
  border: 1px solid #d9dde2;
  color: #4e5963;
}

.meta {
  color: #586975;
  font-size: 0.9rem;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 118px;
}

.field {
  display: grid;
  gap: 0.28rem;
}

.field span {
  color: #223844;
  font-size: 0.9rem;
  font-weight: 700;
}

.field.wide {
  grid-column: 1 / -1;
}

.auto-end {
  color: #4f606c;
  font-size: 0.9rem;
  grid-column: 1 / -1;
}

input {
  border: 1px solid #d5d8db;
  border-radius: 10px;
  padding: 0.55rem 0.65rem;
}

input:focus {
  border-color: #c57632;
  outline: none;
}

.form-actions {
  display: flex;
  gap: 0.5rem;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.primary-btn,
.ghost-btn,
.danger-btn,
.back-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
  text-align: center;
  text-decoration: none;
}

.primary-btn {
  background: #c57632;
  color: #fff;
}

.primary-btn:hover {
  background: #d68744;
}

.primary-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ghost-btn,
.back-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover,
.back-btn:hover {
  background: #f6f8f9;
}

.ghost-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.danger-btn {
  background: #fff3f3;
  border: 1px solid #f0caca;
  color: #aa2a2a;
}

.danger-btn:hover {
  background: #ffeaea;
}

.muted {
  color: #63727d;
}

@media (max-width: 760px) {
  .create-panel,
  .edit-grid {
    grid-template-columns: 1fr;
  }

  .timeslot-header {
    flex-direction: column;
  }

  .actions {
    flex-direction: row;
    flex-wrap: wrap;
    min-width: 0;
  }
}
</style>
