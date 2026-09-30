<template>
  <div>
    <div v-if="loading" class="center"><ProgressSpinner style="width: 32px; height: 32px" /></div>
    <Message v-else-if="denied" severity="info" :closable="false">
      A evolução deste atleta é registrada pelo professor da turma dele. Você não é professor de nenhuma turma em que ele está.
    </Message>
    <template v-else-if="data">
      <Message v-if="!data.can_edit" severity="secondary" :closable="false" class="mb">Somente leitura.</Message>
      <EvolutionPanel :data="data" :athlete-id="athleteId" :editable="data.can_edit" :user-id="auth.user?.id" :is-admin="auth.isAdmin" @changed="load" />
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import EvolutionPanel from './EvolutionPanel.vue'
import { evolutionApi } from '@/api/evolution'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'

const props = defineProps({ athleteId: { type: Number, required: true } })
const auth = useAuthStore()
const { notify } = useApiError()
const data = ref(null)
const loading = ref(true)
const denied = ref(false)

async function load() {
  try {
    data.value = await evolutionApi.summary(props.athleteId)
  } catch (e) {
    if (e.response?.status === 403) denied.value = true
    else notify(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.center { display: grid; place-items: center; padding: 2rem; }
.mb { margin-bottom: 1rem; }
</style>
