<template>
  <Tag :value="info.label" :severity="info.severity" rounded />
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'
import { athleteStatus } from '@/utils/format'

const props = defineProps({ status: { type: String, required: true } })
const info = computed(() => athleteStatus[props.status] || { label: props.status, severity: 'secondary' })
</script>
