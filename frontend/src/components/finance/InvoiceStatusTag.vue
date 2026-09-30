<template>
  <Tag :severity="info.severity" rounded>
    <i :class="info.icon" aria-hidden="true"></i>
    <span>{{ info.label }}</span>
  </Tag>
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'
import { invoiceStatus } from '@/utils/format'

const props = defineProps({
  status: { type: String, required: true },
  overdue: { type: Boolean, default: false }
})

// Atrasada = aberta/parcial vencida (status sempre com ícone + texto, nunca só cor).
const info = computed(() => invoiceStatus[props.overdue ? 'atrasada' : props.status] || { label: props.status, severity: 'secondary', icon: '' })
</script>

<style scoped>
:deep(.p-tag) { gap: .3rem; }
i { font-size: .7rem; }
</style>
