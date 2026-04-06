<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { RouterView } from 'vue-router'

import AppHeader from '@/components/AppHeader.vue'
import Error401View from '@/views/errors/Error401View.vue'
import Error403View from '@/views/errors/Error403View.vue'

import { useAccessStore } from './stores/access'

const accessStore = useAccessStore()
const { errorCode } = storeToRefs(accessStore)
</script>

<template>
  <div class="app-shell">
    <AppHeader />

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