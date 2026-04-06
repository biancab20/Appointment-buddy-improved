<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView } from 'vue-router'

import brandLogo from '@/assets/images/logo.png'
import Error401View from '@/views/errors/Error401View.vue'
import Error403View from '@/views/errors/Error403View.vue'

import { useAccessStore } from './stores/access'

const accessStore = useAccessStore()
const { errorCode } = storeToRefs(accessStore)
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <RouterLink to="/" class="brand" aria-label="Appointment Buddy home">
        <img :src="brandLogo" alt="Appointment Buddy logo" class="brand-logo" />
        <span>Appointment Buddy</span>
      </RouterLink>

      <nav class="app-nav">
        <RouterLink to="/">Home</RouterLink>
        <RouterLink to="/about">About</RouterLink>
      </nav>
    </header>

    <Error401View v-if="errorCode === 401" />
    <Error403View v-else-if="errorCode === 403" />
    <RouterView v-else />

    <footer class="app-footer">
      <span>&copy; {{ new Date().getFullYear() }} Appointment Buddy</span>
      <span>Built for Web Development 2</span>
    </footer>
  </div>
</template>

<style scoped>
.app-shell {
  margin: 0 auto;
  max-width: 1160px;
  min-height: 100vh;
  padding: 1.2rem 1rem 2rem;
}

.app-header {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 1.6rem;
}

.brand {
  align-items: center;
  color: #0f3341;
  display: inline-flex;
  font-family: var(--font-display);
  font-size: 1.22rem;
  font-weight: 700;
  gap: 0.6rem;
  letter-spacing: 0.01em;
  text-decoration: none;
}

.brand-logo {
  border-radius: 12px;
  height: 44px;
  width: 44px;
}

.app-nav {
  display: flex;
  gap: 0.45rem;
}

.app-nav a {
  border-radius: 999px;
  color: #0f3341;
  font-weight: 700;
  padding: 0.4rem 0.85rem;
  text-decoration: none;
  transition:
    color 0.2s ease,
    background-color 0.2s ease;
}

.app-nav a:hover {
  background: rgba(15, 51, 65, 0.09);
}

.app-nav a.router-link-exact-active {
  background: #0f3341;
  color: #fff;
}

.app-footer {
  border-top: 1px solid rgba(15, 51, 65, 0.15);
  color: #62727b;
  display: flex;
  flex-wrap: wrap;
  font-size: 0.86rem;
  gap: 0.6rem;
  justify-content: space-between;
  margin-top: 3rem;
  padding-top: 1rem;
}
</style>