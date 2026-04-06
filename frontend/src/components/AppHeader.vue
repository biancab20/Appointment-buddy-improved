<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { RouterLink, useRouter } from 'vue-router'

import brandLogo from '@/assets/images/logo.png'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const { isAuthenticated } = storeToRefs(authStore)

async function logout(): Promise<void> {
  authStore.clearSession()
  await router.push({ name: 'home' })
}
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="brand" aria-label="Appointment Buddy home">
      <img :src="brandLogo" alt="Appointment Buddy logo" class="brand-logo" />
      <span>Appointment Buddy</span>
    </RouterLink>

    <nav class="app-nav" aria-label="Main navigation">
      <template v-if="!isAuthenticated">
        <RouterLink to="/">Home</RouterLink>
        <RouterLink to="/login">Log in</RouterLink>
        <RouterLink to="/signup">Sign up</RouterLink>
      </template>

      <template v-else>
        <RouterLink to="/dashboard">Dashboard</RouterLink>
        <button type="button" class="logout-btn" @click="logout">Log out</button>
      </template>
    </nav>
  </header>
</template>

<style scoped>
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
  align-items: center;
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

.logout-btn {
  background: rgba(15, 51, 65, 0.1);
  border: none;
  border-radius: 999px;
  color: #0f3341;
  cursor: pointer;
  font-weight: 700;
  padding: 0.4rem 0.85rem;
}

.logout-btn:hover {
  background: rgba(15, 51, 65, 0.16);
}
</style>