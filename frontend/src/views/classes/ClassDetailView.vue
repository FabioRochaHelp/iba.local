<template>
  <div>
    <div v-if="loading" class="iba-card"><ProgressSpinner style="width: 40px; height: 40px" /></div>
    <template v-else-if="cls">
      <section class="iba-card hero">
        <div>
          <h1>{{ cls.name }}</h1>
          <p class="meta">
            <span><i class="pi pi-calendar" aria-hidden="true"></i> {{ weekdayLabel(cls.weekday) }}, {{ cls.start_time }}–{{ cls.end_time }}</span>
            <span v-if="cls.category"><i class="pi pi-flag" aria-hidden="true"></i> {{ cls.category }}</span>
            <span v-if="cls.min_birth_year || cls.max_birth_year"><i class="pi pi-id-card" aria-hidden="true"></i> nascidos {{ cls.min_birth_year || '…' }}–{{ cls.max_birth_year || '…' }}</span>
            <span><i class="pi pi-user" aria-hidden="true"></i> {{ cls.coach_name || 'sem professor' }}</span>
          </p>
        </div>
        <div class="hero__actions">
          <RouterLink :to="{ name: 'classes' }"><Button label="Voltar" icon="pi pi-arrow-left" text severity="secondary" /></RouterLink>
          <RouterLink v-if="cls.active" :to="{ name: 'attendance-class', params: { classId: cls.id } }">
            <Button label="Fazer chamada" icon="pi pi-check-square" />
          </RouterLink>
        </div>
      </section>

      <Tabs v-model:value="tab" class="iba-card tabs">
        <TabList>
          <Tab value="atletas">Atletas ({{ cls.athletes.length }})</Tab>
          <Tab value="frequencia">Frequência</Tab>
        </TabList>
        <TabPanels>
          <!-- ATLETAS -->
          <TabPanel value="atletas">
            <div v-if="auth.isAdmin" class="add">
              <AthletePicker v-model="picked" input-id="add-athlete" class="add__picker" />
              <Button label="Adicionar" icon="pi pi-plus" :disabled="!picked" @click="addPicked" />
              <Button label="Sugestões pela idade" icon="pi pi-sparkles" text @click="openSuggestions" />
            </div>
            <DataTable :value="cls.athletes" data-key="id" size="small">
              <template #empty><p class="iba-muted empty">Nenhum atleta nesta turma{{ auth.isAdmin ? ' — adicione acima.' : '.' }}</p></template>
              <Column header="Atleta">
                <template #body="{ data }">
                  <RouterLink :to="{ name: 'athlete-show', params: { id: data.id } }" class="alink">{{ data.name }}</RouterLink>
                  <i
                    v-if="data.has_health_condition" v-tooltip.top="data.health_condition" class="pi pi-heart-fill health"
                    :aria-label="`Condição de saúde: ${data.health_condition}`"
                  ></i>
                </template>
              </Column>
              <Column header="Categoria"><template #body="{ data }">{{ data.category || '—' }}</template></Column>
              <Column header="Plano" class="col-md"><template #body="{ data }">{{ data.plan_days ? `${data.plan_days}x/semana` : '—' }}</template></Column>
              <Column header="Na turma desde" class="col-md"><template #body="{ data }">{{ formatDate(data.joined_at) }}</template></Column>
              <Column v-if="auth.isAdmin" header="" class="col-actions">
                <template #body="{ data }">
                  <Button icon="pi pi-user-minus" text rounded severity="danger" :aria-label="`Remover ${data.name} da turma`" @click="remove(data)" />
                </template>
              </Column>
            </DataTable>
          </TabPanel>

          <!-- FREQUÊNCIA -->
          <TabPanel value="frequencia">
            <div class="period">
              <SelectButton v-model="period" :options="periods" option-label="label" option-value="value" :allow-empty="false" aria-label="Período" />
            </div>
            <div v-if="report" class="report">
              <section>
                <h3>Por atleta</h3>
                <p class="iba-muted small">Frequência = presenças ÷ chamadas registradas (falta justificada não conta como presença).</p>
                <table class="rtable">
                  <thead><tr><th scope="col">Atleta</th><th scope="col" class="num">P</th><th scope="col" class="num">F</th><th scope="col" class="num">J</th><th scope="col">Frequência</th></tr></thead>
                  <tbody>
                    <tr v-for="a in report.athletes" :key="a.athlete_id">
                      <td>{{ a.name }}</td>
                      <td class="num">{{ a.present }}</td>
                      <td class="num">{{ a.absent }}</td>
                      <td class="num">{{ a.justified }}</td>
                      <td>
                        <div class="rate">
                          <div class="meter" role="meter" :aria-valuenow="a.rate ?? 0" aria-valuemin="0" aria-valuemax="100" :aria-label="`Frequência de ${a.name}`">
                            <span :style="{ width: `${a.rate ?? 0}%` }"></span>
                          </div>
                          <strong :class="{ low: a.rate !== null && a.rate < 60 }">{{ a.rate !== null ? `${a.rate}%` : '—' }}</strong>
                          <i v-if="a.rate !== null && a.rate < 60" v-tooltip.top="'Frequência abaixo de 60%'" class="pi pi-exclamation-triangle low" aria-label="Frequência baixa"></i>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="!report.athletes.length"><td colspan="5" class="iba-muted">Nenhuma chamada no período.</td></tr>
                  </tbody>
                </table>
              </section>
              <section>
                <h3>Chamadas</h3>
                <ul class="sessions">
                  <li v-for="s in report.sessions" :key="s.id">
                    <RouterLink :to="{ name: 'attendance-class', params: { classId: cls.id }, query: { data: s.session_date } }">
                      {{ formatDate(s.session_date) }}
                    </RouterLink>
                    <span class="counts">
                      <span class="c-p" :aria-label="`${s.present} presentes`">P {{ s.present }}</span>
                      <span class="c-f" :aria-label="`${s.absent} faltas`">F {{ s.absent }}</span>
                      <span class="c-j" :aria-label="`${s.justified} justificadas`">J {{ s.justified }}</span>
                    </span>
                  </li>
                  <li v-if="!report.sessions.length" class="iba-muted">Nenhuma chamada no período.</li>
                </ul>
              </section>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>

    <Dialog v-model:visible="suggestionsOpen" header="Sugestões pela faixa de nascimento" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '95vw' }">
      <p v-if="!cls?.min_birth_year && !cls?.max_birth_year" class="iba-muted small">Defina a faixa de nascimento da turma para sugestões mais precisas. Mostrando todos os atletas ativos fora da turma.</p>
      <p v-if="!suggestions.length" class="iba-muted">Nenhum atleta encontrado.</p>
      <ul class="sugg">
        <li v-for="s in suggestions" :key="s.id">
          <label>
            <Checkbox v-model="selectedSuggestions" :value="s.id" :input-id="`sg-${s.id}`" />
            <span>{{ s.name }}</span>
            <small class="iba-muted">{{ s.category || '—' }}<template v-if="s.classes_count"> · já em {{ s.classes_count }} turma(s)</template></small>
          </label>
        </li>
      </ul>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="suggestionsOpen = false" />
        <Button :label="`Adicionar ${selectedSuggestions.length}`" icon="pi pi-check" :disabled="!selectedSuggestions.length" :loading="saving" @click="addSuggestions" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import SelectButton from 'primevue/selectbutton'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import ProgressSpinner from 'primevue/progressspinner'
import AthletePicker from '@/components/AthletePicker.vue'
import { classesApi } from '@/api/classes'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { formatDate, todayIso, weekdayLabel } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const { notify } = useApiError()

const cls = ref(null)
const loading = ref(true)
const saving = ref(false)
const tab = ref('atletas')
const picked = ref(null)
const suggestionsOpen = ref(false)
const suggestions = ref([])
const selectedSuggestions = ref([])
const report = ref(null)
const period = ref(30)
const periods = [
  { value: 30, label: '30 dias' },
  { value: 90, label: '90 dias' },
  { value: 365, label: '12 meses' }
]

async function load() {
  loading.value = true
  try {
    cls.value = await classesApi.get(route.params.id)
  } catch (e) {
    notify(e)
    if ([403, 404].includes(e.response?.status)) router.replace({ name: 'classes' })
  } finally {
    loading.value = false
  }
}

async function loadReport() {
  const from = new Date()
  from.setDate(from.getDate() - period.value)
  const f = `${from.getFullYear()}-${String(from.getMonth() + 1).padStart(2, '0')}-${String(from.getDate()).padStart(2, '0')}`
  try {
    report.value = await classesApi.report(route.params.id, { from: f, to: todayIso() })
  } catch (e) {
    notify(e)
  }
}

watch(tab, (t) => t === 'frequencia' && loadReport())
watch(period, loadReport)

async function sync(ids, message) {
  saving.value = true
  try {
    cls.value = await classesApi.setAthletes(cls.value.id, ids)
    toast.add({ severity: 'success', summary: message, life: 2500 })
  } catch (e) {
    notify(e)
  } finally {
    saving.value = false
  }
}

async function addPicked() {
  if (!picked.value) return
  const ids = cls.value.athletes.map((a) => a.id)
  if (ids.includes(picked.value.id)) {
    toast.add({ severity: 'info', summary: 'Atleta já está na turma', life: 2500 })
    return
  }
  await sync([...ids, picked.value.id], `${picked.value.name} adicionado`)
  picked.value = null
}

async function remove(a) {
  await sync(cls.value.athletes.filter((x) => x.id !== a.id).map((x) => x.id), `${a.name} removido da turma`)
}

async function openSuggestions() {
  selectedSuggestions.value = []
  try {
    suggestions.value = await classesApi.suggestions(cls.value.id)
    suggestionsOpen.value = true
  } catch (e) {
    notify(e)
  }
}

async function addSuggestions() {
  await sync([...cls.value.athletes.map((a) => a.id), ...selectedSuggestions.value], `${selectedSuggestions.value.length} atleta(s) adicionado(s)`)
  suggestionsOpen.value = false
}

onMounted(load)
</script>

<style scoped>
.hero { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: center; border-top: 3px solid var(--iba-gold); }
.meta { display: flex; flex-wrap: wrap; gap: 1rem; margin: .5rem 0 0; color: var(--iba-text-muted); font-size: .88rem; }
.hero__actions { display: flex; gap: .25rem; align-items: center; }
.tabs { margin-top: 1rem; padding: 0 1rem 1rem; }
.add { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
.add__picker { flex: 1 1 260px; }
.alink { color: var(--iba-text); font-weight: 500; }
.health { color: var(--iba-danger); margin-left: .4rem; font-size: .8rem; }
.empty { text-align: center; padding: 1rem; }
.period { margin-bottom: 1rem; }
.report { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
.report h3 { font-size: .9rem; margin-bottom: .25rem; }
.small { font-size: .78rem; margin: 0 0 .75rem; }
.rtable { width: 100%; border-collapse: collapse; font-size: .9rem; }
.rtable th, .rtable td { padding: .45rem .4rem; border-bottom: 1px solid var(--iba-border); text-align: left; }
.rtable thead th { font-size: .72rem; text-transform: uppercase; color: var(--iba-text-muted); }
.num { text-align: right !important; width: 36px; }
.rate { display: flex; align-items: center; gap: .5rem; min-width: 150px; }
/* medidor: trilho e barra na mesma rampa */
.meter { flex: 1; height: 6px; border-radius: 3px; background: color-mix(in srgb, var(--c-expected) 18%, transparent); overflow: hidden; }
.meter span { display: block; height: 100%; background: var(--c-expected); border-radius: 3px; }
.rate strong { width: 40px; text-align: right; font-size: .85rem; }
.low { color: var(--iba-danger); }
.sessions { list-style: none; margin: .75rem 0 0; padding: 0; display: grid; gap: .4rem; }
.sessions li { display: flex; justify-content: space-between; gap: .5rem; padding: .45rem .6rem; border: 1px solid var(--iba-border); border-radius: 8px; font-size: .88rem; }
.sessions a { color: var(--iba-text); font-weight: 600; }
.counts { display: flex; gap: .5rem; font-size: .78rem; font-weight: 600; }
.c-p { color: var(--iba-success); }
.c-f { color: var(--iba-danger); }
.c-j { color: var(--iba-blue); }
.sugg { list-style: none; padding: 0; margin: 0; display: grid; gap: .4rem; max-height: 50vh; overflow: auto; }
.sugg label { display: flex; gap: .6rem; align-items: center; cursor: pointer; }
@media (max-width: 860px) { .report { grid-template-columns: 1fr; } }
@media (max-width: 640px) { :deep(.col-md) { display: none; } }
</style>
