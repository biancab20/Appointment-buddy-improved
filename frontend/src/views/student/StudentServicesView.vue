<template>
  <main class="page-shell">
    <section class="heading-row">
      <div>
        <h1>Available Services</h1>
        <p class="subtitle">Browse services and choose a timeslot to book.</p>
      </div>
      <RouterLink to="/student/dashboard" class="back-btn">Back</RouterLink>
    </section>

    <section class="panel">
      <p>This page is now routed and ready. Next step: list `/api/student/services` and book flow.</p>
    </section>
  </main>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<style scoped>
.page-shell {
  margin: 0 auto;
  max-width: 980px;
  min-height: 72vh;
}

.heading-row {
  align-items: flex-start;
  display: flex;
  gap: 0.9rem;
  justify-content: space-between;
  margin-bottom: 0.9rem;
}

h1 {
  color: #0f3341;
  font-family: var(--font-display);
  font-size: clamp(1.55rem, 4vw, 2.2rem);
  margin-bottom: 0.2rem;
}

.subtitle {
  color: #884e1c;
}

.panel {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 14px;
  box-shadow: 0 12px 26px rgba(15, 51, 65, 0.07);
  color: #586974;
  padding: 0.95rem;
}

.back-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  border-radius: 9px;
  color: #0f3341;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
  text-decoration: none;
}

.back-btn:hover {
  background: #f6f8f9;
}
</style>
