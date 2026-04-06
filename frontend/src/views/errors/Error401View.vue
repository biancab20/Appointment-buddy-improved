<script setup lang="ts">
import { RouterLink } from 'vue-router'

import unauthorizedImage from '@/assets/images/unauthorized.png'
</script>

<template>
  <main class="error-page">
    <section class="error-layout">
      <div class="error-copy">
        <p class="code">401</p>
        <h1>You did not pass the identity check.</h1>
        <p class="message">This page requires a valid session. Log in and try again.</p>

        <RouterLink to="/" class="action">Go back home</RouterLink>
      </div>

      <div class="error-image-wrap">
        <img :src="unauthorizedImage" alt="Unauthorized illustration" class="error-image" />
      </div>
    </section>
  </main>
</template>

<style scoped>
.error-page {
  min-height: 70vh;
  place-items: center;
  display: grid;
}

.error-layout {
  align-items: center;
  display: grid;
  gap: 1.5rem;
  grid-template-columns: 1fr;
  width: min(1100px, 100%);
}

.error-copy {
  display: grid;
  gap: 0.85rem;
  text-align: left;
}

.code {
  color: #c57632;
  font-size: clamp(2.6rem, 8vw, 5rem);
  font-weight: 800;
  line-height: 1;
}

h1 {
  color: #122933;
  font-size: clamp(1.55rem, 4.2vw, 3rem);
  font-weight: 800;
  line-height: 1.15;
}

.message {
  color: #51616b;
  font-size: clamp(1rem, 2.4vw, 1.2rem);
}

.action {
  background: #c57632;
  border-radius: 10px;
  color: #fff;
  display: inline-block;
  font-weight: 700;
  margin-top: 0.55rem;
  padding: 0.7rem 1.1rem;
  text-decoration: none;
  width: fit-content;
}

.action:hover {
  background: #d68744;
}

.error-image-wrap {
  display: flex;
  justify-content: center;
}

.error-image {
  max-height: 420px;
  width: min(100%, 520px);
}

@media (min-width: 960px) {
  .error-layout {
    grid-template-columns: 1fr 1fr;
    gap: 2.2rem;
  }
}
</style>