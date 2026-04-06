<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { RouterLink } from 'vue-router'

interface Props {
  title: string
  subtitle?: string
  backTo?: RouteLocationRaw
  backLabel?: string
}

withDefaults(defineProps<Props>(), {
  subtitle: '',
  backTo: undefined,
  backLabel: 'Back',
})
</script>

<template>
  <section class="app-page-header">
    <div class="title-wrap">
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="subtitle">{{ subtitle }}</p>
    </div>

    <RouterLink v-if="backTo" :to="backTo" class="back-btn">{{ backLabel }}</RouterLink>
  </section>
</template>

<style scoped>
.app-page-header {
  align-items: flex-start;
  display: flex;
  gap: 0.85rem;
  justify-content: space-between;
  margin-bottom: 1rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.55rem, 4vw, 2.25rem);
  line-height: 1.15;
  margin-bottom: 0.22rem;
}

.subtitle {
  color: #884e1c;
  font-size: 0.97rem;
  font-weight: 600;
}

.back-btn {
  background: #0f3341;
  border: 1px solid #0f3341;
  border-radius: 8px;
  color: #fff;
  font-size: 0.84rem;
  font-weight: 700;
  min-height: 2.15rem;
  padding: 0.5rem 0.82rem;
  text-decoration: none;
  transition: background-color 0.2s ease;
  white-space: nowrap;
}

.back-btn:hover {
  background: #18475c;
}

@media (max-width: 680px) {
  .app-page-header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
