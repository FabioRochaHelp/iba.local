<template>
  <span class="phone">
    <a
      v-if="url && whatsapp" :href="url" target="_blank" rel="noopener noreferrer" class="phone__wa"
      :aria-label="`Abrir conversa no WhatsApp com ${formatted}`" @click.stop
    >
      <i class="pi pi-whatsapp" aria-hidden="true"></i>{{ formatted }}
    </a>
    <a v-else :href="`tel:+55${phone}`" @click.stop>{{ formatted }}</a>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { formatPhone, whatsappUrl } from '@/utils/format'

const props = defineProps({
  phone: { type: String, required: true },
  whatsapp: { type: Boolean, default: true },
  message: { type: String, default: '' }
})

const formatted = computed(() => formatPhone(props.phone))
const url = computed(() => whatsappUrl(props.phone, props.message))
</script>

<style scoped>
.phone a { text-decoration: none; white-space: nowrap; color: var(--iba-text); }
.phone a:hover { text-decoration: underline; }
.phone__wa i { color: #25a244; margin-right: .3rem; }
</style>
