<script setup lang="ts">
interface Props {
  message: string
  type?: 'success' | 'error' | 'warning' | 'info'
  inline?: boolean
}

withDefaults(defineProps<Props>(), {
  type: 'info',
  inline: false,
})
</script>

<template>
  <p class="feedback" :class="[type, { inline }]">
    {{ message }}
  </p>
</template>

<style scoped>
.feedback {
  border-radius: 10px;
  margin-bottom: 0.75rem;
  padding: 0.66rem 0.8rem;
}

.feedback.inline {
  margin-bottom: 0;
}

.feedback.success {
  background: #ecfdf3;
  border: 1px solid #b7e7c8;
  color: #067647;
}

.feedback.error {
  background: #fff1f1;
  border: 1px solid #f2c6c6;
  color: #b42318;
}

.feedback.warning {
  background: #fff7e9;
  border: 1px solid #f2d7a8;
  color: #8a5b13;
}

.feedback.info {
  background: #eff6ff;
  border: 1px solid #bfd4ff;
  color: #1f4f9a;
}
</style>
