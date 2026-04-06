<script setup lang="ts">
interface Props {
  title: string
  description: string
  durationMinutes: number
  price: number
  submitLabel: string
  cancelLabel?: string
  showCancel?: boolean
}

withDefaults(defineProps<Props>(), {
  cancelLabel: 'Cancel',
  showCancel: true,
})

const emit = defineEmits<{
  (event: 'update:title', value: string): void
  (event: 'update:description', value: string): void
  (event: 'update:durationMinutes', value: number): void
  (event: 'update:price', value: number): void
  (event: 'submit'): void
  (event: 'cancel'): void
}>()

function onDurationInput(raw: string): void {
  const nextValue = Number(raw)
  if (!Number.isNaN(nextValue)) {
    emit('update:durationMinutes', nextValue)
  }
}

function onPriceInput(raw: string): void {
  const nextValue = Number(raw)
  if (!Number.isNaN(nextValue)) {
    emit('update:price', nextValue)
  }
}
</script>

<template>
  <form class="service-form" @submit.prevent="emit('submit')">
    <label class="field wide">
      <span>Title</span>
      <input :value="title" type="text" required @input="emit('update:title', ($event.target as HTMLInputElement).value)" />
    </label>

    <label class="field wide">
      <span>Description</span>
      <textarea
        :value="description"
        rows="3"
        @input="emit('update:description', ($event.target as HTMLTextAreaElement).value)"
      />
    </label>

    <label class="field">
      <span>Duration (minutes)</span>
      <input
        :value="durationMinutes"
        type="number"
        min="1"
        required
        @input="onDurationInput(($event.target as HTMLInputElement).value)"
      />
    </label>

    <label class="field">
      <span>Price</span>
      <input
        :value="price"
        type="number"
        min="0.01"
        step="0.01"
        required
        @input="onPriceInput(($event.target as HTMLInputElement).value)"
      />
    </label>

    <div class="form-actions">
      <button v-if="showCancel" type="button" class="ghost-btn" @click="emit('cancel')">
        {{ cancelLabel }}
      </button>
      <button type="submit" class="primary-btn">{{ submitLabel }}</button>
    </div>
  </form>
</template>

<style scoped>
.service-form {
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

input,
textarea {
  border: 1px solid #d5d8db;
  border-radius: 10px;
  padding: 0.55rem 0.65rem;
}

input:focus,
textarea:focus {
  border-color: #c57632;
  outline: none;
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
  .service-form {
    grid-template-columns: 1fr;
  }
}
</style>
