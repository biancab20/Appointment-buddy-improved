<script setup lang="ts">
interface Props {
  title: string
  value: string | number
  subtitle?: string
}

withDefaults(defineProps<Props>(), {
  subtitle: '',
})
</script>

<template>
  <article class="stat-card">
    <p class="title">{{ title }}</p>
    <p class="value">{{ value }}</p>
    <p v-if="subtitle" class="subtitle">{{ subtitle }}</p>
  </article>
</template>

<style scoped>
.stat-card {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 14px;
  box-shadow: 0 8px 20px rgba(15, 51, 65, 0.07);
  padding: 0.9rem;
}

.title {
  color: #4f6270;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.value {
  color: #0f3341;
  font-size: clamp(1.35rem, 3vw, 1.9rem);
  font-weight: 800;
  line-height: 1.15;
}

.subtitle {
  color: #6a7a85;
  font-size: 0.82rem;
  margin-top: 0.25rem;
}
</style>
