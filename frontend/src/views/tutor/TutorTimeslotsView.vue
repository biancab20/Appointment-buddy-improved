<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import TutorTimeslotCard from '@/components/timeslots/TutorTimeslotCard.vue'
import TutorTimeslotForm from '@/components/timeslots/TutorTimeslotForm.vue'
import type { TutorTimeslot } from '@/stores/timeslots'
import { useTimeslotsStore } from '@/stores/timeslots'
import { formatDateTimeFromDate, isPastOrNowDateTime, toInputDateTime } from '@/utils/dateTime'

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

function updateCreateStartTime(value: string): void {
  createForm.start_time = value
}

function updateEditStartTime(value: string): void {
  editForm.start_time = value
}

function canEditTimeslot(timeslot: TutorTimeslot): boolean {
  return Boolean(service.value && isActive(service.value.is_active) && !isPastOrNowDateTime(timeslot.start_time))
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
  return formatDateTimeFromDate(endDate)
}
</script>

<template>
  <main class="page-shell">
    <PageHeader
      title="Timeslots"
      :subtitle="`Service: ${service?.title ?? '...'}`"
      back-to="/tutor/services"
      back-label="Back to services"
    />

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />
    <FeedbackMessage v-if="successMessage" :message="successMessage" type="success" />

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

      <section v-if="showCreateForm" class="panel create-panel">
        <TutorTimeslotForm
          :start-time="createForm.start_time"
          :end-preview="calculatedEndPreview(createForm.start_time)"
          submit-label="Create"
          @update:start-time="updateCreateStartTime"
          @cancel="showCreateForm = false"
          @submit="createTimeslot"
        />
      </section>
    </section>

    <p v-if="isLoading" class="muted">Loading timeslots...</p>

    <section v-else class="timeslot-list">
      <article v-for="timeslot in timeslots" :key="timeslot.id" class="panel timeslot-card">
        <TutorTimeslotCard
          v-if="editingTimeslotId !== timeslot.id"
          :timeslot="timeslot"
          :can-edit="canEditTimeslot(timeslot)"
          :can-deactivate="isActive(timeslot.is_active)"
          @edit="startEdit"
          @deactivate="deactivateTimeslot"
        />

        <TutorTimeslotForm
          v-else
          :start-time="editForm.start_time"
          :end-preview="calculatedEndPreview(editForm.start_time)"
          submit-label="Save changes"
          @update:start-time="updateEditStartTime"
          @cancel="cancelEdit"
          @submit="updateTimeslot(timeslot.id)"
        />
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

.feedback {
  border-radius: 10px;
  margin-bottom: 0.7rem;
  padding: 0.65rem 0.8rem;
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

.create-panel {
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

.primary-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
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

.muted {
  color: #63727d;
}

@media (max-width: 760px) {
  .create-panel {
    padding: 0.75rem;
  }
}
</style>
