<script setup lang="ts">
import { RouterLink } from 'vue-router'

import type { TutorService } from '@/stores/services'
import { formatPrice } from '@/utils/number'

interface Props {
  service: TutorService
  active: boolean
  timeslotsTo: string
  canDeactivate: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  (event: 'edit'): void
  (event: 'deactivate'): void
}>()
</script>

<template>
  <div class="service-header">
    <div>
      <div class="title-row">
        <h2>{{ service.title }}</h2>
        <span v-if="!active" class="badge inactive">Inactive</span>
      </div>
      <p v-if="service.description" class="desc">{{ service.description }}</p>
      <p class="meta">
        Duration: <strong>{{ service.duration_minutes }}</strong> min |
        Price: <strong>EUR {{ formatPrice(service.price) }}</strong>
      </p>
    </div>

    <div class="actions">
      <RouterLink :to="timeslotsTo" class="dark-btn">Timeslots</RouterLink>
      <button type="button" class="ghost-btn" @click="emit('edit')">Edit</button>
      <button v-if="canDeactivate" type="button" class="danger-btn" @click="emit('deactivate')">Disable</button>
    </div>
  </div>
</template>

<style scoped>
.service-header {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

.title-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
}

h2 {
  color: #0f3341;
  font-size: 1.22rem;
}

.badge {
  border: 1px solid #d5dbe0;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.18rem 0.45rem;
}

.badge.inactive {
  background: #f6f7f8;
  color: #56636d;
}

.desc {
  color: #5d6c76;
  margin-bottom: 0.25rem;
  max-width: 64ch;
}

.meta {
  color: #445661;
  font-size: 0.92rem;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 124px;
}

.ghost-btn,
.dark-btn,
.danger-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
  text-align: center;
  text-decoration: none;
}

.dark-btn {
  background: #0f3341;
  color: #fff;
}

.dark-btn:hover {
  background: #174559;
}

.ghost-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover {
  background: #f6f8f9;
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
  .service-header {
    flex-direction: column;
  }

  .actions {
    flex-direction: row;
    flex-wrap: wrap;
    min-width: 0;
  }
}
</style>
