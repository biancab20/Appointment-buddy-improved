<script setup lang="ts">
import type { StudentBooking } from '@/stores/bookings'
import { formatDate, formatTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

import StatusBadge from './StatusBadge.vue'

interface Props {
  booking: StudentBooking
  statusLabel: string
  statusTone: 'paid' | 'completed' | 'cancelled'
  canManage: boolean
  policyHint: string
  actionLoading?: boolean
  rescheduleLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  actionLoading: false,
  rescheduleLoading: false,
})

const emit = defineEmits<{
  (event: 'reschedule', booking: StudentBooking): void
  (event: 'cancel', booking: StudentBooking): void
}>()

function onReschedule(): void {
  emit('reschedule', props.booking)
}

function onCancel(): void {
  emit('cancel', props.booking)
}
</script>

<template>
  <article class="booking-card">
    <div class="card-head">
      <div>
        <h2>{{ booking.service_title }}</h2>
        <p class="meta">
          {{ formatDate(booking.start_time) }} | {{ formatTime(booking.start_time) }} ->
          {{ formatTime(booking.end_time) }}
        </p>
        <p class="meta">Tutor: {{ booking.tutor_name }}</p>
        <p class="meta">Paid: EUR {{ formatPrice(booking.price_at_booking) }}</p>
      </div>

      <StatusBadge :label="statusLabel" :tone="statusTone" />
    </div>

    <template v-if="canManage">
      <p class="policy-hint">{{ policyHint }}</p>
      <div class="card-actions">
        <button type="button" class="action-btn action-outline" :disabled="actionLoading" @click="onReschedule">
          {{ rescheduleLoading ? 'Loading...' : 'Reschedule' }}
        </button>
        <button type="button" class="action-btn action-dark" :disabled="actionLoading" @click="onCancel">
          {{ actionLoading ? 'Working...' : 'Cancel' }}
        </button>
      </div>
    </template>
  </article>
</template>

<style scoped>
.booking-card {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 18px rgba(15, 51, 65, 0.08);
  padding: 1.05rem 1.1rem;
}

.card-head {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

h2 {
  color: #0f3341;
  font-size: 1.08rem;
  line-height: 1.25;
  margin-bottom: 0.32rem;
}

.meta {
  color: #884e1c;
  font-size: 0.88rem;
  margin-bottom: 0.28rem;
}

.policy-hint {
  color: #4d5f69;
  font-size: 0.82rem;
  margin-top: 0.5rem;
}

.card-actions {
  display: flex;
  gap: 0.45rem;
  margin-top: 0.55rem;
}

.action-btn {
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  padding: 0.4rem 0.74rem;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.action-outline {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.action-outline:hover {
  background: #f6f8f9;
}

.action-dark {
  background: #0f3341;
  color: #fff;
}

.action-dark:hover {
  background: #18475c;
}

@media (max-width: 760px) {
  .card-head {
    flex-direction: column;
  }

  .card-actions {
    flex-wrap: wrap;
  }
}
</style>
