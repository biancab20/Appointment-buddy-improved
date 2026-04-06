<script setup lang="ts">
import { formatTime } from '@/utils/dateTime'

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
  title: 'Available times',
})

const emit = defineEmits<{
  (event: 'select', timeslotId: number): void
}>()
</script>

<template>
  <div class="timeslot-list">
    <p class="label">{{ title }}</p>
    <button
      v-for="slot in timeslots"
      :key="slot.id"
      type="button"
      class="timeslot-row selectable"
      :class="{ selected: selectedTimeslotId === slot.id }"
      @click="emit('select', slot.id)"
    >
      {{ formatTime(slot.start_time) }} -> {{ formatTime(slot.end_time) }}
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

.timeslot-list {
  display: grid;
  gap: 0.42rem;
}

.timeslot-row {
  background: #fff;
  border: 1px solid #ecd9c6;
  border-radius: 10px;
  color: #4d5f69;
  font-size: 0.92rem;
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
