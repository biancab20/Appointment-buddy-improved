<script setup lang="ts">
import { computed } from 'vue'

import { formatDate } from '@/utils/dateTime'

interface Props {
  id: string
  label?: string
  options: string[]
  modelValue: string
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Choose a date',
})

const emit = defineEmits<{
  (event: 'update:modelValue', value: string): void
}>()

const model = computed({
  get(): string {
    return props.modelValue
  },
  set(value: string): void {
    emit('update:modelValue', value)
  },
})
</script>

<template>
  <label class="label" :for="id">{{ label }}</label>
  <select :id="id" v-model="model" class="date-select">
    <option v-for="dateOption in options" :key="dateOption" :value="dateOption">
      {{ formatDate(`${dateOption}T00:00`) }}
    </option>
  </select>
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

.date-select {
  background: #fff;
  border: 1px solid #d6c4af;
  border-radius: 10px;
  margin-bottom: 0.75rem;
  padding: 0.55rem 0.62rem;
  width: 100%;
}
</style>
