<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted, reactive, ref } from 'vue'

import FeedbackMessage from '@/components/common/FeedbackMessage.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import TutorServiceCard from '@/components/services/TutorServiceCard.vue'
import TutorServiceForm from '@/components/services/TutorServiceForm.vue'
import type { TutorService, TutorServicePayload } from '@/stores/services'
import { useServicesStore } from '@/stores/services'

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
    <PageHeader
      title="Services"
      subtitle="Manage your tutoring services and their timeslots."
      back-to="/tutor/dashboard"
    />

    <p class="summary">You have {{ activeServicesCount }} active service(s).</p>

    <FeedbackMessage v-if="errorMessage" :message="errorMessage" type="error" />
    <FeedbackMessage v-if="successMessage" :message="successMessage" type="success" />

    <section class="create-wrap">
      <button type="button" class="primary-btn" @click="showCreateForm = !showCreateForm">
        {{ showCreateForm ? 'Close form' : 'Add service' }}
      </button>

      <section v-if="showCreateForm" class="panel create-panel">
        <TutorServiceForm
          :title="createForm.title"
          :description="createForm.description"
          :duration-minutes="createForm.duration_minutes"
          :price="createForm.price"
          submit-label="Create"
          @update:title="createForm.title = $event"
          @update:description="createForm.description = $event"
          @update:duration-minutes="createForm.duration_minutes = $event"
          @update:price="createForm.price = $event"
          @cancel="showCreateForm = false"
          @submit="createService"
        />
      </section>
    </section>

    <p v-if="isLoading" class="muted">Loading services...</p>

    <section v-else class="service-list">
      <article v-for="service in services" :key="service.id" class="panel service-card">
        <TutorServiceCard
          v-if="editingServiceId !== service.id"
          :service="service"
          :active="isActive(service.is_active)"
          :can-deactivate="isActive(service.is_active)"
          :timeslots-to="`/tutor/services/${service.id}/timeslots`"
          @edit="startEdit(service)"
          @deactivate="deactivateService(service.id)"
        />

        <TutorServiceForm
          v-else
          :title="editForm.title"
          :description="editForm.description"
          :duration-minutes="editForm.duration_minutes"
          :price="editForm.price"
          submit-label="Save changes"
          @update:title="editForm.title = $event"
          @update:description="editForm.description = $event"
          @update:duration-minutes="editForm.duration_minutes = $event"
          @update:price="editForm.price = $event"
          @cancel="cancelEdit"
          @submit="updateService(service.id)"
        />
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

.summary {
  color: #884e1c;
  font-weight: 700;
  margin-bottom: 0.9rem;
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

.primary-btn {
  border: none;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.5rem 0.8rem;
}

.primary-btn {
  background: #c57632;
  color: #fff;
}

.primary-btn:hover {
  background: #d68744;
}

.muted {
  color: #63727d;
}

@media (max-width: 760px) {
  .create-panel {
    padding: 0.75rem;
  }
}
</style>
