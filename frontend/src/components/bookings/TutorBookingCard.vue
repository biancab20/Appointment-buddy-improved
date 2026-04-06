<script setup lang="ts">
import type { TutorBooking } from '@/stores/tutorBookings'
import { formatDate, formatTime } from '@/utils/dateTime'
import { formatPrice } from '@/utils/number'

import StatusBadge from './StatusBadge.vue'

interface Props {
  booking: TutorBooking
  statusLabel: string
  statusTone: 'paid' | 'completed' | 'cancelled'
  canCancel: boolean
  actionLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  actionLoading: false,
})

const emit = defineEmits<{
  (event: 'cancel', booking: TutorBooking): void
}>()

function onCancel(): void {
  emit('cancel', props.booking)
}
</script>

<template>
  <article class="booking-card">
    <div class="card-head">
      <div>
        <h3>{{ booking.service_title }}</h3>
        <p class="meta">
          {{ formatDate(booking.start_time) }} | {{ formatTime(booking.start_time) }} ->
          {{ formatTime(booking.end_time) }}
        </p>
        <p class="meta">Student: {{ booking.student_name }} ({{ booking.student_email }})</p>
        <p class="meta">Paid: EUR {{ formatPrice(booking.price_at_booking) }}</p>
      </div>

      <StatusBadge :label="statusLabel" :tone="statusTone" />
    </div>

    <div v-if="canCancel" class="card-actions">
      <button type="button" class="action-btn" :disabled="actionLoading" @click="onCancel">
        {{ actionLoading ? 'Working...' : 'Cancel Booking' }}
      </button>
    </div>
  </article>
</template>

<style scoped>
.booking-card {
  border: 1px solid rgba(229, 176, 95, 0.38);
  border-radius: 10px;
  padding: 0.85rem 0.9rem;
}

.card-head {
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

h3 {
  color: #0f3341;
  font-size: 1rem;
  margin-bottom: 0.2rem;
}

.meta {
  color: #884e1c;
  font-size: 0.84rem;
  margin-bottom: 0.24rem;
}

.card-actions {
  margin-top: 0.5rem;
}

.action-btn {
  background: #0f3341;
  border: none;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.4rem 0.7rem;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 760px) {
  .card-head {
    flex-direction: column;
  }
}
</style>
