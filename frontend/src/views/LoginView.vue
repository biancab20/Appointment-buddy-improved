<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface LoginResponse {
  access_token: string
  refresh_token: string
  user: {
    role: 'admin' | 'tutor' | 'student'
  }
}

const authStore = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

async function onSubmit(): Promise<void> {
  errorMessage.value = ''

  if (email.value.trim() === '' || password.value.trim() === '') {
    errorMessage.value = 'Email and password are required.'
    return
  }

  isLoading.value = true

  try {
    const response = await api.post<LoginResponse>('/auth/login', {
      email: email.value,
      password: password.value,
    })

    const data = response.data
    authStore.setSession(data.access_token, data.user.role, data.refresh_token ?? null)

    await router.push('/dashboard')
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      errorMessage.value = error.response?.data?.error ?? 'Login failed. Please try again.'
    } else {
      errorMessage.value = 'Login failed. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <p class="auth-kicker">Welcome back</p>
      <h1>Log in to Appointment Buddy</h1>
      <p class="auth-subtitle">Access your tutoring sessions and bookings.</p>

      <form class="auth-form" @submit.prevent="onSubmit">
        <label class="field">
          <span>Email</span>
          <input v-model="email" type="email" name="email" autocomplete="email" required />
        </label>

        <label class="field">
          <span>Password</span>
          <input
            v-model="password"
            type="password"
            name="password"
            autocomplete="current-password"
            required
          />
        </label>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

        <button type="submit" class="submit" :disabled="isLoading">
          {{ isLoading ? 'Logging in...' : 'Log in' }}
        </button>
      </form>

      <p class="auth-footer">
        No account yet?
        <RouterLink to="/signup">Create one</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.auth-page {
  display: grid;
  min-height: 72vh;
  place-items: center;
}

.auth-card {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(15, 51, 65, 0.08);
  padding: 1.35rem;
  width: min(100%, 520px);
}

.auth-kicker {
  color: #c57632;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  margin-bottom: 0.4rem;
  text-transform: uppercase;
}

h1 {
  color: #122933;
  font-size: clamp(1.45rem, 4vw, 2rem);
  font-weight: 800;
  line-height: 1.15;
  margin-bottom: 0.4rem;
}

.auth-subtitle {
  color: #50616b;
  margin-bottom: 1rem;
}

.auth-form {
  display: grid;
  gap: 0.75rem;
}

.field {
  display: grid;
  gap: 0.3rem;
}

.field span {
  color: #233842;
  font-size: 0.9rem;
  font-weight: 700;
}

input {
  border: 1px solid #d7c4b0;
  border-radius: 10px;
  padding: 0.62rem 0.7rem;
}

input:focus {
  border-color: #c57632;
  outline: none;
}

.error {
  color: #b42318;
  font-size: 0.92rem;
  font-weight: 700;
}

.submit {
  background: #0f3341;
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  margin-top: 0.3rem;
  padding: 0.7rem 0.9rem;
}

.submit:hover {
  background: #194a5d;
}

.submit:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.auth-footer {
  color: #55656e;
  margin-top: 1rem;
  text-align: center;
}

.auth-footer a {
  color: #0f3341;
  font-weight: 700;
  text-decoration: none;
}
</style>