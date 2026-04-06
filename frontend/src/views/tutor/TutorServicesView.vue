<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import type { TutorService, TutorServicePayload } from '@/stores/services'
import { useServicesStore } from '@/stores/services'
import { formatPrice } from '@/utils/number'

const servicesStore = useServicesStore()
const { tutorLoading, tutorServices } = storeToRefs(servicesStore)

const services = computed(() => tutorServices.value)
const isLoading = computed(() => tutorLoading.value)
const errorMessage = ref('')
const successMessage = ref('')

const showCreateForm = ref(false)
const createForm = reactive({
  title: '',
  description: '',
  duration_minutes: 60,
  price: 25,
})

const editingServiceId = ref<number | null>(null)
const editForm = reactive({
  title: '',
  description: '',
  duration_minutes: 60,
  price: 25,
})

const activeServicesCount = computed(
  () => services.value.filter((service) => service.is_active === 1 || service.is_active === true).length,
)

function isActive(value: number | boolean): boolean {
  return value === 1 || value === true
}

function resetCreateForm(): void {
  createForm.title = ''
  createForm.description = ''
  createForm.duration_minutes = 60
  createForm.price = 25
}

function startEdit(service: TutorService): void {
  editingServiceId.value = service.id
  editForm.title = service.title
  editForm.description = service.description ?? ''
  editForm.duration_minutes = service.duration_minutes
  editForm.price = Number(service.price)
}

function cancelEdit(): void {
  editingServiceId.value = null
}

async function loadServices(): Promise<void> {
  errorMessage.value = ''

  try {
    await servicesStore.fetchTutorServices()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load your services.'
  }
}

async function createService(): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload: TutorServicePayload = {
      title: createForm.title,
      description: createForm.description.trim() === '' ? null : createForm.description,
      duration_minutes: createForm.duration_minutes,
      price: createForm.price,
    }

    await servicesStore.createTutorService(payload)

    successMessage.value = 'Service created.'
    showCreateForm.value = false
    resetCreateForm()
    await loadServices()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to create service.'
  }
}

async function updateService(serviceId: number): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload: TutorServicePayload = {
      title: editForm.title,
      description: editForm.description.trim() === '' ? null : editForm.description,
      duration_minutes: editForm.duration_minutes,
      price: editForm.price,
    }

    await servicesStore.updateTutorService(serviceId, payload)

    successMessage.value = 'Service updated.'
    editingServiceId.value = null
    await loadServices()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update service.'
  }
}

async function deactivateService(serviceId: number): Promise<void> {
  errorMessage.value = ''
  successMessage.value = ''

  const shouldContinue = window.confirm(
    'Disable this service? It will be hidden. Only unbooked timeslots will be deactivated; paid bookings remain scheduled.',
  )
  if (!shouldContinue) {
    return
  }

  try {
    await servicesStore.deactivateTutorService(serviceId)
    successMessage.value = 'Service disabled.'
    await loadServices()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to disable service.'
  }
}

onMounted(() => {
  void loadServices()
})
</script>

<template>
  <main class="page-shell">
    <section class="heading-row">
      <div>
        <h1>Services</h1>
        <p class="subtitle">Manage your tutoring services and their timeslots.</p>
      </div>
      <RouterLink to="/tutor/dashboard" class="back-btn">Back</RouterLink>
    </section>

    <p class="summary">You have {{ activeServicesCount }} active service(s).</p>

    <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="feedback success">{{ successMessage }}</p>

    <section class="create-wrap">
      <button type="button" class="primary-btn" @click="showCreateForm = !showCreateForm">
        {{ showCreateForm ? 'Close form' : 'Add service' }}
      </button>

      <form v-if="showCreateForm" class="panel create-panel" @submit.prevent="createService">
        <label class="field wide">
          <span>Title</span>
          <input v-model="createForm.title" type="text" required />
        </label>

        <label class="field wide">
          <span>Description</span>
          <textarea v-model="createForm.description" rows="3" />
        </label>

        <label class="field">
          <span>Duration (minutes)</span>
          <input v-model.number="createForm.duration_minutes" type="number" min="1" required />
        </label>

        <label class="field">
          <span>Price</span>
          <input
            v-model.number="createForm.price"
            type="number"
            min="0.01"
            step="0.01"
            required
          />
        </label>

        <div class="form-actions">
          <button type="button" class="ghost-btn" @click="showCreateForm = false">Cancel</button>
          <button type="submit" class="primary-btn">Create</button>
        </div>
      </form>
    </section>

    <p v-if="isLoading" class="muted">Loading services...</p>

    <section v-else class="service-list">
      <article v-for="service in services" :key="service.id" class="panel service-card">
        <template v-if="editingServiceId !== service.id">
          <div class="service-header">
            <div>
              <div class="title-row">
                <h2>{{ service.title }}</h2>
                <span v-if="!isActive(service.is_active)" class="badge inactive">Inactive</span>
              </div>
              <p v-if="service.description" class="desc">{{ service.description }}</p>
              <p class="meta">
                Duration: <strong>{{ service.duration_minutes }}</strong> min |
                Price: <strong>EUR {{ formatPrice(service.price) }}</strong>
              </p>
            </div>

            <div class="actions">
              <RouterLink :to="`/tutor/services/${service.id}/timeslots`" class="dark-btn"
                >Timeslots</RouterLink
              >
              <button type="button" class="ghost-btn" @click="startEdit(service)">Edit</button>
              <button
                v-if="isActive(service.is_active)"
                type="button"
                class="danger-btn"
                @click="deactivateService(service.id)"
              >
                Disable
              </button>
            </div>
          </div>
        </template>

        <form v-else class="edit-grid" @submit.prevent="updateService(service.id)">
          <label class="field wide">
            <span>Title</span>
            <input v-model="editForm.title" type="text" required />
          </label>

          <label class="field wide">
            <span>Description</span>
            <textarea v-model="editForm.description" rows="3" />
          </label>

          <label class="field">
            <span>Duration (minutes)</span>
            <input v-model.number="editForm.duration_minutes" type="number" min="1" required />
          </label>

          <label class="field">
            <span>Price</span>
            <input v-model.number="editForm.price" type="number" min="0.01" step="0.01" required />
          </label>

          <div class="form-actions">
            <button type="button" class="ghost-btn" @click="cancelEdit">Cancel</button>
            <button type="submit" class="primary-btn">Save changes</button>
          </div>
        </form>
      </article>

      <p v-if="services.length === 0" class="muted">No services yet. Create your first service.</p>
    </section>
  </main>
</template>

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
  line-height: 1.2;
  margin-bottom: 0.2rem;
}

.subtitle {
  color: #884e1c;
}

.summary {
  color: #884e1c;
  font-weight: 700;
  margin-bottom: 0.9rem;
}

.feedback {
  border-radius: 10px;
  margin-bottom: 0.7rem;
  padding: 0.65rem 0.8rem;
}

.feedback.error {
  background: #fff1f1;
  border: 1px solid #f2c6c6;
  color: #b42318;
}

.feedback.success {
  background: #eefbf1;
  border: 1px solid #bfe6c8;
  color: #217348;
}

.create-wrap {
  margin-bottom: 0.9rem;
}

.panel {
  background: #fff;
  border: 1px solid #ebdccd;
  border-radius: 14px;
  box-shadow: 0 12px 26px rgba(15, 51, 65, 0.07);
}

.create-panel {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: 0.75rem;
  padding: 0.9rem;
}

.service-list {
  display: grid;
  gap: 0.8rem;
}

.service-card {
  padding: 0.95rem;
}

.service-header {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  justify-content: space-between;
}

.title-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
}

h2 {
  color: #0f3341;
  font-size: 1.22rem;
}

.badge {
  border: 1px solid #d5dbe0;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.18rem 0.45rem;
}

.badge.inactive {
  background: #f6f7f8;
  color: #56636d;
}

.desc {
  color: #5d6c76;
  margin-bottom: 0.25rem;
  max-width: 64ch;
}

.meta {
  color: #445661;
  font-size: 0.92rem;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 124px;
}

.edit-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field {
  display: grid;
  gap: 0.28rem;
}

.field.wide {
  grid-column: 1 / -1;
}

.field span {
  color: #223844;
  font-size: 0.9rem;
  font-weight: 700;
}

input,
textarea {
  border: 1px solid #d5d8db;
  border-radius: 10px;
  padding: 0.55rem 0.65rem;
}

input:focus,
textarea:focus {
  border-color: #c57632;
  outline: none;
}

.form-actions {
  display: flex;
  gap: 0.5rem;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.primary-btn,
.ghost-btn,
.dark-btn,
.danger-btn,
.back-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
  text-align: center;
  text-decoration: none;
}

.primary-btn {
  background: #c57632;
  color: #fff;
}

.primary-btn:hover {
  background: #d68744;
}

.dark-btn {
  background: #0f3341;
  color: #fff;
}

.dark-btn:hover {
  background: #174559;
}

.ghost-btn,
.back-btn {
  background: #fff;
  border: 1px solid #d8dee3;
  color: #0f3341;
}

.ghost-btn:hover,
.back-btn:hover {
  background: #f6f8f9;
}

.danger-btn {
  background: #fff3f3;
  border: 1px solid #f0caca;
  color: #aa2a2a;
}

.danger-btn:hover {
  background: #ffeaea;
}

.muted {
  color: #63727d;
}

@media (max-width: 760px) {
  .create-panel,
  .edit-grid {
    grid-template-columns: 1fr;
  }

  .service-header {
    flex-direction: column;
  }

  .actions {
    flex-direction: row;
    flex-wrap: wrap;
    min-width: 0;
  }
}
</style>
