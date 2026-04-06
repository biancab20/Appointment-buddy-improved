<script setup lang="ts">
import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import { formatDate, formatTime } from '@/utils/dateTime'

interface TimeslotItem {
  id: number
  start_time: string
  end_time: string
}

interface Props {
  selectedTimeslot: TimeslotItem | null
  paymentError?: string
  paymentLoading?: boolean
}

withDefaults(defineProps<Props>(), {
  paymentError: '',
  paymentLoading: false,
})

const emit = defineEmits<{
  (event: 'pay'): void
}>()
</script>

<template>
  <section class="pay-panel">
    <p v-if="selectedTimeslot" class="selected-slot">
      Selected:
      <strong>
        {{ formatDate(selectedTimeslot.start_time) }}
        {{ formatTime(selectedTimeslot.start_time) }} ->
        {{ formatTime(selectedTimeslot.end_time) }}
      </strong>
    </p>
    <p v-else class="muted">Select a timeslot to continue to payment.</p>

    <FeedbackMessage v-if="paymentError" :message="paymentError" type="error" inline />

    <button type="button" class="pay-btn" :disabled="!selectedTimeslot || paymentLoading" @click="emit('pay')">
      {{ paymentLoading ? 'Redirecting...' : 'Pay' }}
    </button>
  </section>
</template>

<style scoped>
.pay-panel {
  align-items: flex-start;
  border-top: 1px solid #f0decb;
  display: grid;
  gap: 0.45rem;
  margin-top: 0.9rem;
  padding-top: 0.85rem;
}

.selected-slot {
  color: #4d5f69;
  font-size: 0.9rem;
}

.selected-slot strong {
  color: #0f3341;
}

.pay-btn {
  background: #0f3341;
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 700;
  padding: 0.56rem 0.95rem;
}

.pay-btn:hover {
  background: #174559;
}

.pay-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.muted {
  color: #63727d;
  font-style: italic;
}
</style>
