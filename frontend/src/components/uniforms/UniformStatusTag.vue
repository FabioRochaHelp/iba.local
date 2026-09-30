<template>
  <Tag :severity="info.severity" rounded>
    <i :class="info.icon" aria-hidden="true"></i>
    <span>{{ info.label }}</span>
  </Tag>
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'
import { uniformStatus } from '@/utils/format'

const props = defineProps({ status: { type: String, required: true } })
const info = computed(() => uniformStatus[props.status] || { label: props.status, severity: 'secondary', icon: '' })
</script>

<style scoped>
:deep(.p-tag) { gap: .3rem; }
i { font-size: .7rem; }
</style>
