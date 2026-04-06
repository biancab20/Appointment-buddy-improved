<script setup lang="ts">
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import NextTimeslotsList from '@/components/timeslots/NextTimeslotsList.vue'
import PayPanel from '@/components/timeslots/PayPanel.vue'
import TimeslotDatePicker from '@/components/timeslots/TimeslotDatePicker.vue'
import TimeslotList from '@/components/timeslots/TimeslotList.vue'
import type { ServiceItem } from '@/stores/services'
import type { StudentTimeslot } from '@/stores/timeslots'
import { formatPrice } from '@/utils/number'

interface Props {
  service: ServiceItem
  open: boolean
  loading: boolean
  errorMessage: string
  timeslots: StudentTimeslot[]
  selectedDate: string
  selectedTimeslotId: number | null
  selectedTimeslot: StudentTimeslot | null
  paymentLoading: boolean
  paymentError: string
  dateOptions: string[]
  filteredTimeslots: StudentTimeslot[]
  nextTimeslots: StudentTimeslot[]
}

defineProps<Props>()

const emit = defineEmits<{
  (event: 'toggle'): void
  (event: 'update:selectedDate', value: string): void
  (event: 'select-timeslot', timeslotId: number): void
  (event: 'pay'): void
}>()

function onDateChange(value: string): void {
  emit('update:selectedDate', value)
}
</script>

<template>
  <article class="service-card">
    <h2>{{ service.title }}</h2>

    <p v-if="service.description" class="description">{{ service.description }}</p>

    <p class="meta">
      Tutor: <strong>{{ service.tutor_name ?? 'Unknown tutor' }}</strong>
    </p>
    <p class="meta">
      Duration: <strong>{{ service.duration_minutes }} min</strong> |
      Price: <strong>EUR {{ formatPrice(service.price) }}</strong>
    </p>

    <button type="button" class="toggle-btn" @click="emit('toggle')">
      {{ open ? 'Hide timeslots' : 'View timeslots' }}
    </button>

    <div v-if="open" class="timeslot-panel">
      <p v-if="loading" class="muted">Loading timeslots...</p>
      <FeedbackMessage v-else-if="errorMessage" :message="errorMessage" type="error" inline />

      <template v-else>
        <p v-if="timeslots.length === 0" class="muted">No available timeslots at the moment.</p>

        <template v-else>
          <div class="timeslot-grid">
            <div>
              <TimeslotDatePicker
                :id="`date-${service.id}`"
                :options="dateOptions"
                :model-value="selectedDate"
                @update:model-value="onDateChange"
              />
              <TimeslotList
                :timeslots="filteredTimeslots"
                :selected-timeslot-id="selectedTimeslotId"
                @select="emit('select-timeslot', $event)"
              />
            </div>

            <NextTimeslotsList
              :timeslots="nextTimeslots"
              :selected-timeslot-id="selectedTimeslotId"
              @select="emit('select-timeslot', $event)"
            />
          </div>

          <PayPanel
            :selected-timeslot="selectedTimeslot"
            :payment-error="paymentError"
            :payment-loading="paymentLoading"
            @pay="emit('pay')"
          />
        </template>
      </template>
    </div>
  </article>
</template>

<style scoped>
.service-card {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.42);
  border-radius: 18px;
  box-shadow: 0 14px 28px rgba(15, 51, 65, 0.08);
  padding: 1.15rem;
}

h2 {
  color: #0f3341;
  font-size: 1.32rem;
  line-height: 1.25;
  margin-bottom: 0.35rem;
}

.description {
  color: #884e1c;
  margin-bottom: 0.45rem;
  max-width: 70ch;
}

.meta {
  color: #4e616c;
  font-size: 0.93rem;
  margin-bottom: 0.3rem;
}

.toggle-btn {
  background: #c57632;
  border: none;
  border-radius: 10px;
  box-shadow: 0 8px 18px rgba(197, 118, 50, 0.24);
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  margin-top: 0.4rem;
  padding: 0.58rem 0.95rem;
}

.toggle-btn:hover {
  background: #d68744;
}

.timeslot-panel {
  background: linear-gradient(180deg, #fffaf4, #fffefb);
  border: 1px solid #f0decb;
  border-radius: 14px;
  margin-top: 0.95rem;
  padding: 0.85rem;
}

.timeslot-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: 1fr 1fr;
}

.muted {
  color: #63727d;
  font-style: italic;
}

@media (max-width: 760px) {
  .timeslot-grid {
    grid-template-columns: 1fr;
  }
}
</style>
