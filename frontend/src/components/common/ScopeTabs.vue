<script setup lang="ts">
import { computed } from 'vue'

interface ScopeTabOption {
  value: string
  label: string
}

interface Props {
  modelValue: string
  options?: ScopeTabOption[]
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  options: () => [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'history', label: 'History' },
  ],
  disabled: false,
})

const emit = defineEmits<{
  (event: 'update:modelValue', value: string): void
}>()

const resolvedOptions = computed(() => props.options)

function select(value: string): void {
  if (props.disabled || value === props.modelValue) {
    return
  }
  emit('update:modelValue', value)
}
</script>

<template>
  <section class="scope-tabs">
    <button
      v-for="option in resolvedOptions"
      :key="option.value"
      type="button"
      class="tab-btn"
      :class="{ active: option.value === modelValue }"
      :disabled="disabled"
      @click="select(option.value)"
    >
      {{ option.label }}
    </button>
  </section>
</template>

<style scoped>
.scope-tabs {
  border-bottom: 1px solid rgba(229, 176, 95, 0.4);
  display: flex;
  gap: 0.35rem;
  margin-bottom: 0.95rem;
}

.tab-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 9px 9px 0 0;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  min-height: 2rem;
  opacity: 0.72;
  padding: 0.5rem 0.84rem;
  transition: opacity 0.18s ease;
}

.tab-btn:hover:not(:disabled) {
  opacity: 1;
}

.tab-btn.active {
  background: #fff;
  border-color: rgba(229, 176, 95, 0.45);
  border-bottom-color: #fff;
  opacity: 1;
}

.tab-btn:disabled {
  cursor: default;
  opacity: 0.5;
}
</style>
