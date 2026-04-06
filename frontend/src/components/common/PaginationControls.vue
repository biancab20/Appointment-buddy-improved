<script setup lang="ts">
interface Props {
  page: number
  totalPages: number
  hasPrev: boolean
  hasNext: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
})

const emit = defineEmits<{
  (event: 'go', page: number): void
}>()

function navigate(page: number): void {
  if (props.disabled || page < 1 || page > props.totalPages || page === props.page) {
    return
  }
  emit('go', page)
}
</script>

<template>
  <nav v-if="totalPages > 1" class="pager">
    <button
      type="button"
      class="pager-btn"
      :disabled="disabled || !hasPrev"
      @click="navigate(1)"
    >
      First
    </button>
    <button
      type="button"
      class="pager-btn"
      :disabled="disabled || !hasPrev"
      @click="navigate(page - 1)"
    >
      Previous
    </button>

    <span class="pager-info">Page {{ page }} / {{ totalPages }}</span>

    <button
      type="button"
      class="pager-btn"
      :disabled="disabled || !hasNext"
      @click="navigate(page + 1)"
    >
      Next
    </button>
    <button
      type="button"
      class="pager-btn"
      :disabled="disabled || !hasNext"
      @click="navigate(totalPages)"
    >
      Last
    </button>
  </nav>
</template>

<style scoped>
.pager {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 1rem;
}

.pager-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 8px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
  min-height: 2.1rem;
  padding: 0.45rem 0.72rem;
}

.pager-btn:hover:not(:disabled) {
  border-color: #c57632;
}

.pager-btn:disabled {
  cursor: default;
  opacity: 0.55;
}

.pager-info {
  color: #4f6270;
  font-size: 0.86rem;
  font-weight: 700;
}
</style>
