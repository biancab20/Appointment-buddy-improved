<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { api } from '@/lib/api'

type SignupRole = 'student' | 'tutor'

const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const role = ref<SignupRole>('student')

const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function onSubmit(): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  if (name.value.trim() === '' || email.value.trim() === '' || password.value.trim() === '') {
    errorMessage.value = 'Name, email, and password are required.'
    return
  }

  if (password.value.length < 6) {
    errorMessage.value = 'Password must be at least 6 characters.'
    return
  }

  if (password.value !== confirmPassword.value) {
    errorMessage.value = 'Passwords do not match.'
    return
  }

  isLoading.value = true

  try {
    await api.post('/auth/register', {
      name: name.value,
      email: email.value,
      password: password.value,
      role: role.value,
    })

    successMessage.value = 'Your account has been created. You can now log in.'
    await router.push({ name: 'login' })
  } catch (error: unknown) {
    if (axios.isAxiosError<{ error?: string }>(error)) {
      if (error.response?.status === 404) {
        errorMessage.value =
          'Registration endpoint is not available yet. Add backend route POST /auth/register next.'
      } else {
        errorMessage.value = error.response?.data?.error ?? 'Sign up failed. Please try again.'
      }
    } else {
      errorMessage.value = 'Sign up failed. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <p class="auth-kicker">Get started</p>
      <h1>Create your account</h1>
      <p class="auth-subtitle">Choose your role and start booking or offering tutoring sessions.</p>

      <form class="auth-form" @submit.prevent="onSubmit">
        <label class="field">
          <span>Name</span>
          <input v-model="name" type="text" name="name" autocomplete="name" required />
        </label>

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
            autocomplete="new-password"
            required
          />
        </label>

        <label class="field">
          <span>Confirm password</span>
          <input
            v-model="confirmPassword"
            type="password"
            name="confirmPassword"
            autocomplete="new-password"
            required
          />
        </label>

        <fieldset class="role-fieldset">
          <legend>Role</legend>
          <label>
            <input v-model="role" type="radio" value="student" name="role" />
            Student
          </label>
          <label>
            <input v-model="role" type="radio" value="tutor" name="role" />
            Tutor
          </label>
        </fieldset>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <p v-if="successMessage" class="success">{{ successMessage }}</p>

        <button type="submit" class="submit" :disabled="isLoading">
          {{ isLoading ? 'Creating account...' : 'Sign up' }}
        </button>
      </form>

      <p class="auth-footer">
        Already have an account?
        <RouterLink to="/login">Log in</RouterLink>
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
  width: min(100%, 560px);
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

input[type='text'],
input[type='email'],
input[type='password'] {
  border: 1px solid #d7c4b0;
  border-radius: 10px;
  padding: 0.62rem 0.7rem;
}

input:focus {
  border-color: #c57632;
  outline: none;
}

.role-fieldset {
  border: 1px solid #e4d2bf;
  border-radius: 10px;
  display: flex;
  gap: 1.2rem;
  margin: 0;
  padding: 0.75rem 0.85rem;
}

.role-fieldset legend {
  color: #2d4551;
  font-size: 0.9rem;
  font-weight: 700;
  padding: 0 0.25rem;
}

.role-fieldset label {
  align-items: center;
  color: #384f5a;
  display: inline-flex;
  gap: 0.4rem;
}

.error {
  color: #b42318;
  font-size: 0.92rem;
  font-weight: 700;
}

.success {
  color: #166534;
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