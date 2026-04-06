<script setup lang="ts">
import type { TutorTimeslot } from '@/stores/timeslots'
import { formatDateTime, isPastOrNowDateTime } from '@/utils/dateTime'

interface Props {
  timeslot: TutorTimeslot
  canEdit: boolean
  canDeactivate: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (event: 'edit', timeslot: TutorTimeslot): void
  (event: 'deactivate', timeslotId: number): void
}>()

function isActive(value: number | boolean): boolean {
  return value === 1 || value === true
}

function onEdit(): void {
  emit('edit', props.timeslot)
}

function onDeactivate(): void {
  emit('deactivate', props.timeslot.id)
}
</script>

<template>
  <div class="timeslot-header">
    <div>
      <div class="title-row">
        <h2>{{ formatDateTime(timeslot.start_time) }} -> {{ formatDateTime(timeslot.end_time) }}</h2>
        <span v-if="isActive(timeslot.is_active)" class="badge active">Active</span>
        <span v-else class="badge inactive">Inactive</span>
        <span v-if="isPastOrNowDateTime(timeslot.start_time)" class="badge neutral">Past</span>
      </div>
      <p class="meta">Timeslot ID: {{ timeslot.id }}</p>
    </div>

    <div class="actions">
      <button type="button" class="ghost-btn" :disabled="!canEdit" @click="onEdit">Edit</button>
      <button v-if="canDeactivate" type="button" class="danger-btn" @click="onDeactivate">Deactivate</button>
    </div>
  </div>
</template>

<style scoped>
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

.ghost-btn,
.danger-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
}

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover {
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

@media (max-width: 760px) {
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
