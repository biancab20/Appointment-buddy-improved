<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <h1>Appointment Buddy Improved</h1>

      <nav>
        <RouterLink to="/">Health</RouterLink>
        <RouterLink to="/about">About</RouterLink>
      </nav>
    </header>

    <RouterView />
  </div>
</template>

<style scoped>
.app-shell {
  margin: 0 auto;
  max-width: 960px;
  min-height: 100vh;
  padding: 2rem 1rem;
}

.app-header {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 2rem;
}

h1 {
  color: #0f3341;
  font-size: 1.5rem;
  margin: 0;
}

nav {
  display: flex;
  gap: 1rem;
}

nav a {
  color: #0f3341;
  font-weight: 600;
  text-decoration: none;
}

nav a.router-link-exact-active {
  color: #c57632;
}
</style>
