<script setup lang="ts">
interface Props {
  startTime: string
  endPreview: string
  submitLabel: string
  showCancel?: boolean
  cancelLabel?: string
}

withDefaults(defineProps<Props>(), {
  showCancel: true,
  cancelLabel: 'Cancel',
})

const emit = defineEmits<{
  (event: 'update:startTime', value: string): void
  (event: 'submit'): void
  (event: 'cancel'): void
}>()
</script>

<template>
  <form class="timeslot-form" @submit.prevent="emit('submit')">
    <label class="field wide">
      <span>Start</span>
      <input
        :value="startTime"
        type="datetime-local"
        required
        @input="emit('update:startTime', ($event.target as HTMLInputElement).value)"
      />
    </label>

    <p class="auto-end">End time (auto): {{ endPreview }}</p>

    <div class="form-actions">
      <button v-if="showCancel" type="button" class="ghost-btn" @click="emit('cancel')">
        {{ cancelLabel }}
      </button>
      <button type="submit" class="primary-btn">{{ submitLabel }}</button>
    </div>
  </form>
</template>

<style scoped>
.timeslot-form {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field {
  display: grid;
  gap: 0.28rem;
}

.field.wide {
  grid-column: 1 / -1;
}

.field span {
  color: #223844;
  font-size: 0.9rem;
  font-weight: 700;
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

.auto-end {
  color: #4f606c;
  font-size: 0.9rem;
  grid-column: 1 / -1;
}

.form-actions {
  display: flex;
  gap: 0.5rem;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.primary-btn,
.ghost-btn {
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

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover {
  background: #f6f8f9;
}

@media (max-width: 760px) {
  .timeslot-form {
    grid-template-columns: 1fr;
  }
}
</style>
