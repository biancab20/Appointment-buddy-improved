<script setup lang="ts">
interface Props {
  label: string
  tone: 'paid' | 'completed' | 'cancelled'
}

defineProps<Props>()
</script>

<template>
  <span class="status-badge" :class="`status-${tone}`">
    {{ label }}
  </span>
</template>

<style scoped>
.status-badge {
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.28rem 0.56rem;
  white-space: nowrap;
}

.status-paid {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}

.status-completed {
  background: #f9fafb;
  border-color: #e5e7eb;
  color: #374151;
}

.status-cancelled {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}
</style>
