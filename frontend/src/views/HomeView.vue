<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'

import { useHealthStore } from '@/stores/health'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost/api'
const healthUrl = `${apiBaseUrl}/health`

const healthStore = useHealthStore()
const { status, service, errorMessage } = storeToRefs(healthStore)

onMounted(() => {
  void healthStore.fetchHealth()
})
</script>

<template>
  <main class="home-view">
    <section class="card">
      <h2>Backend Health Check</h2>
      <p class="description">Checks <code>{{ healthUrl }}</code></p>

      <p v-if="status === 'loading'" class="status info">Checking backend...</p>
      <p v-else-if="status === 'ok'" class="status ok">Backend is up ({{ service || 'unknown service' }})</p>
      <p v-else-if="status === 'error'" class="status error">{{ errorMessage }}</p>
      <p v-else class="status info">Idle</p>

      <button type="button" @click="healthStore.fetchHealth">Run health check</button>
    </section>
  </main>
</template>

<style scoped>
.home-view {
  width: min(900px, 100%);
}

.card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
}

h2 {
  margin: 0 0 0.75rem;
}

.description {
  margin: 0 0 1rem;
  color: #475569;
}

.status {
  margin: 0 0 1rem;
  font-weight: 600;
}

.status.ok {
  color: #166534;
}

.status.error {
  color: #b91c1c;
}

.status.info {
  color: #334155;
}

button {
  border: none;
  border-radius: 8px;
  background: #0f3341;
  color: white;
  cursor: pointer;
  padding: 0.625rem 1rem;
}

button:hover {
  background: #195063;
}
</style>
