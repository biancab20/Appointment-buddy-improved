<script setup lang="ts">
interface Props {
  disabled?: boolean
}

withDefaults(defineProps<Props>(), {
  disabled: false,
})

const emit = defineEmits<{
  (event: 'apply'): void
  (event: 'reset'): void
}>()

function onApply(): void {
  emit('apply')
}

function onReset(): void {
  emit('reset')
}
</script>

<template>
  <section class="panel filters-panel">
    <form class="filters-form" @submit.prevent="onApply">
      <slot />

      <div class="actions">
        <button type="button" class="ghost-btn" :disabled="disabled" @click="onReset">Reset</button>
        <button type="submit" class="primary-btn" :disabled="disabled">Apply</button>
      </div>
    </form>
  </section>
</template>

<style scoped>
.panel {
  background: #fff;
  border: 1px solid rgba(229, 176, 95, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15, 51, 65, 0.07);
  padding: 0.9rem;
}

.filters-panel {
  margin-bottom: 0.9rem;
}

.filters-form {
  align-items: end;
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.actions {
  display: flex;
  gap: 0.45rem;
  margin-left: auto;
}

.primary-btn,
.ghost-btn {
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.86rem;
  font-weight: 700;
  min-height: 2.15rem;
  padding: 0.45rem 0.78rem;
}

.primary-btn {
  background: #0f3341;
  color: #fff;
}

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.primary-btn:disabled,
.ghost-btn:disabled {
  cursor: default;
  opacity: 0.6;
}

@media (max-width: 760px) {
  .actions {
    margin-left: 0;
  }
}
</style>
