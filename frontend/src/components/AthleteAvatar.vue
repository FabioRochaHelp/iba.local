<template>
  <span class="athlete-avatar" :class="`athlete-avatar--${size}`">
    <img v-if="src && !failed" :src="src" :alt="alt" loading="lazy" decoding="async" @error="failed = true" />
    <!-- Sem foto: silhueta padrão (SVG inline, sem requisição extra) -->
    <svg
      v-else viewBox="0 0 64 64" focusable="false"
      :role="alt ? 'img' : null" :aria-label="alt ? `${alt} (sem foto)` : null" :aria-hidden="alt ? null : 'true'"
    >
      <circle cx="32" cy="24" r="12" />
      <path d="M10 60c1.5-12.5 10.6-20 22-20s20.5 7.5 22 20z" />
    </svg>
  </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { athletesApi } from '@/api/athletes'

const props = defineProps({
  athlete: { type: Object, default: null },
  /** data URL de pré-visualização (formulário); tem prioridade sobre a foto salva */
  preview: { type: String, default: null },
  size: { type: String, default: 'sm', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
  alt: { type: String, default: '' }
})

const failed = ref(false)
const src = computed(() => props.preview || athletesApi.photoUrl(props.athlete?.id, props.athlete?.photo_version))
watch(src, () => { failed.value = false })
</script>

<style scoped>
.athlete-avatar {
  display: inline-grid; place-items: center; flex-shrink: 0; overflow: hidden; border-radius: 50%;
  background: var(--iba-black); border: 2px solid var(--iba-gold);
}
.athlete-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.athlete-avatar svg { width: 78%; height: 78%; margin-top: 18%; fill: var(--iba-gold); opacity: .85; }
.athlete-avatar--sm { width: 36px; height: 36px; }
.athlete-avatar--md { width: 72px; height: 72px; border-width: 3px; }
.athlete-avatar--lg { width: 120px; height: 120px; border-width: 3px; }
</style>
