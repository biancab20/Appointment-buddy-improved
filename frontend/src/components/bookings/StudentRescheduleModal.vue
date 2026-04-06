<script setup lang="ts">
import { computed } from 'vue'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import type { RescheduleOption } from '@/stores/bookings'
import { dateKey, formatDate, formatTime } from '@/utils/dateTime'

interface Props {
  open: boolean
  serviceTitle: string
  loading: boolean
  errorMessage: string
  submitting: boolean
  timeslots: RescheduleOption[]
  selectedDate: string
  selectedTimeslotId: number | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (event: 'close'): void
  (event: 'update:selectedDate', value: string): void
  (event: 'select-timeslot', timeslotId: number): void
  (event: 'submit'): void
}>()

const selectedDateModel = computed({
  get(): string {
    return props.selectedDate
  },
  set(value: string): void {
    emit('update:selectedDate', value)
  },
})

const dateOptions = computed(() => {
  return [...new Set(props.timeslots.map((slot) => dateKey(slot.start_time)))]
})

const filteredSlots = computed(() => {
  if (!props.selectedDate) {
    return props.timeslots
  }
  return props.timeslots.filter((slot) => dateKey(slot.start_time) === props.selectedDate)
})

const nextThreeSlots = computed(() => props.timeslots.slice(0, 3))

function onSelectNextSlot(slot: RescheduleOption): void {
  emit('update:selectedDate', dateKey(slot.start_time))
  emit('select-timeslot', slot.id)
}
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
    <section class="reschedule-modal" role="dialog" aria-modal="true" aria-labelledby="rescheduleTitle">
      <div class="modal-header">
        <div>
          <h2 id="rescheduleTitle" class="modal-title">Reschedule Booking</h2>
          <p class="modal-subtitle">{{ serviceTitle }}</p>
        </div>
        <button type="button" class="close-btn" @click="emit('close')">Close</button>
      </div>

      <p v-if="loading" class="muted">Loading alternative timeslots...</p>
      <FeedbackMessage v-else-if="errorMessage" :message="errorMessage" type="error" inline />

      <template v-else>
        <div class="modal-grid">
          <div>
            <label class="label" for="reschedule-date">Choose a date</label>
            <select id="reschedule-date" v-model="selectedDateModel" class="date-select">
              <option v-for="dateOption in dateOptions" :key="dateOption" :value="dateOption">
                {{ formatDate(`${dateOption}T00:00`) }}
              </option>
            </select>

            <div class="timeslot-list">
              <p class="label">Available times</p>
              <button
                v-for="slot in filteredSlots"
                :key="slot.id"
                type="button"
                class="timeslot-row selectable"
                :class="{ selected: selectedTimeslotId === slot.id }"
                @click="emit('select-timeslot', slot.id)"
              >
                {{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}
              </button>
            </div>
          </div>

          <div>
            <p class="label">Next 3 available</p>
            <button
              v-for="slot in nextThreeSlots"
              :key="slot.id"
              type="button"
              class="next-row selectable"
              :class="{ selected: selectedTimeslotId === slot.id }"
              @click="onSelectNextSlot(slot)"
            >
              <div>{{ formatDate(slot.start_time) }}</div>
              <div>{{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}</div>
            </button>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="ghost-btn" @click="emit('close')">Cancel</button>
          <button
            type="button"
            class="primary-btn"
            :disabled="!selectedTimeslotId || submitting"
            @click="emit('submit')"
          >
            {{ submitting ? 'Updating...' : 'Update timeslot' }}
          </button>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
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

.muted {
  color: #63727d;
  font-style: italic;
}

@media (max-width: 840px) {
  .modal-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .modal-actions {
    justify-content: flex-start;
  }
}
</style>
