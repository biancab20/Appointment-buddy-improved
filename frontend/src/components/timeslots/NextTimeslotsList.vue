<script setup lang="ts">
import { formatDate, formatTime } from '@/utils/dateTime'

interface TimeslotItem {
  id: number
  start_time: string
  end_time: string
}

interface Props {
  title?: string
  timeslots: TimeslotItem[]
  selectedTimeslotId: number | null
}

withDefaults(defineProps<Props>(), {
  title: 'Next 3 available',
})

const emit = defineEmits<{
  (event: 'select', timeslotId: number): void
}>()
</script>

<template>
  <div>
    <p class="label">{{ title }}</p>
    <button
      v-for="slot in timeslots"
      :key="slot.id"
      type="button"
      class="next-row selectable"
      :class="{ selected: selectedTimeslotId === slot.id }"
      @click="emit('select', slot.id)"
    >
      <div>{{ formatDate(slot.start_time) }}</div>
      <div>{{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}</div>
    </button>
  </div>
</template>

<style scoped>
.label {
  color: #0f3341;
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  margin-bottom: 0.4rem;
  text-transform: uppercase;
}

.next-row {
  background: #fff;
  border: 1px solid #ecd9c6;
  border-radius: 10px;
  color: #4d5f69;
  display: block;
  font-size: 0.92rem;
  margin-bottom: 0.42rem;
  padding: 0.52rem 0.62rem;
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
</style>
