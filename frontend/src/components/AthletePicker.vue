<template>
  <AutoComplete
    v-model="selected" :input-id="inputId" :suggestions="suggestions" option-label="name" :min-length="2" :delay="300"
    force-selection dropdown :invalid="invalid" placeholder="Digite o nome do atleta" fluid @complete="search"
  >
    <template #option="{ option }">
      <div class="opt">
        <strong>{{ option.name }}</strong>
        <small class="iba-muted">{{ option.category || 'sem categoria' }} · {{ option.guardian.name }}</small>
      </div>
    </template>
  </AutoComplete>
</template>

<script setup>
import { ref, watch } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { athletesApi } from '@/api/athletes'

const model = defineModel({ type: Object, default: null })
defineProps({ inputId: { type: String, default: 'athlete' }, invalid: { type: Boolean, default: false } })

const selected = ref(model.value)
const suggestions = ref([])

watch(selected, (v) => { model.value = v && typeof v === 'object' ? v : null })
watch(model, (v) => { if (v !== selected.value) selected.value = v })

async function search(e) {
  try {
    suggestions.value = (await athletesApi.list({ search: e.query, per_page: 10, status: 'ativo' })).data
  } catch {
    suggestions.value = []
  }
}
</script>

<style scoped>
.opt { display: flex; flex-direction: column; line-height: 1.3; }
</style>
