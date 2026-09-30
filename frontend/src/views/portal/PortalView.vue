<template>
  <div class="portal">
    <div v-if="loading" class="iba-card"><ProgressSpinner style="width: 36px; height: 36px" /></div>

    <section v-else-if="!athletes.length" class="iba-card empty">
      <i class="pi pi-user" aria-hidden="true"></i>
      <p>Nenhum atleta ativo vinculado à sua conta. Fale com a escolinha.</p>
    </section>

    <template v-else>
      <section class="iba-card hero">
        <span class="hero__avatar" aria-hidden="true">{{ initials(current?.name) }}</span>
        <div class="hero__info">
          <p class="hello">{{ auth.user?.role === 'responsavel' ? `Olá, ${firstName}! Acompanhe` : 'Olá! Este é o seu espaço' }}</p>
          <h1>{{ current?.name }}</h1>
          <p class="meta">
            <span v-if="current?.category"><i class="pi pi-flag" aria-hidden="true"></i> {{ current.category }}</span>
            <span v-if="current?.age !== null"><i class="pi pi-calendar" aria-hidden="true"></i> {{ current.age }} anos</span>
          </p>
        </div>
        <div v-if="athletes.length > 1" class="switch">
          <label for="child" class="sr-only">Escolher atleta</label>
          <Select v-model="selectedId" input-id="child" :options="athletes" option-label="name" option-value="id" aria-label="Escolher atleta" />
        </div>
      </section>

      <div v-if="loadingOverview" class="iba-card"><ProgressSpinner style="width: 36px; height: 36px" /></div>
      <Tabs v-else-if="overview" v-model:value="tab" class="iba-card tabs">
        <TabList>
          <Tab value="evolucao">Evolução</Tab>
          <Tab value="frequencia">Frequência</Tab>
          <Tab value="turmas">Turmas e horários</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="evolucao">
            <EvolutionPanel :data="overview.evolution" :athlete-id="selectedId" />
          </TabPanel>

          <TabPanel value="frequencia">
            <p class="iba-muted small">Últimos 90 dias</p>
            <div class="kpis">
              <div class="kpi"><span>Frequência</span><strong>{{ overview.attendance.rate !== null ? `${overview.attendance.rate}%` : '—' }}</strong></div>
              <div class="kpi"><span>Presenças</span><strong class="p">{{ overview.attendance.present }}</strong></div>
              <div class="kpi"><span>Faltas</span><strong class="f">{{ overview.attendance.absent }}</strong></div>
              <div class="kpi"><span>Justificadas</span><strong class="j">{{ overview.attendance.justified }}</strong></div>
            </div>
            <p v-if="!overview.attendance.total" class="iba-muted">Nenhuma chamada registrada no período.</p>
            <ul class="list">
              <li v-for="(r, i) in overview.attendance.recent" :key="i">
                <span>{{ formatDate(r.session_date) }} · {{ r.class_name }}</span>
                <span :class="['st', `st--${r.status}`]"><i :class="attendanceStatus[r.status].icon" aria-hidden="true"></i> {{ attendanceStatus[r.status].label }}</span>
              </li>
            </ul>
          </TabPanel>

          <TabPanel value="turmas">
            <p v-if="!overview.classes.length" class="iba-muted">Ainda não está em nenhuma turma.</p>
            <div class="classes">
              <article v-for="c in overview.classes" :key="c.id" class="class">
                <strong>{{ c.name }}</strong>
                <span class="when"><i class="pi pi-calendar" aria-hidden="true"></i> {{ weekdayLabel(c.weekday) }}, {{ c.start_time }}–{{ c.end_time }}</span>
                <span class="iba-muted small"><template v-if="c.category">{{ c.category }} · </template>Professor: {{ c.coach_name || 'a definir' }}</span>
              </article>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import Select from 'primevue/select'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import ProgressSpinner from 'primevue/progressspinner'
import EvolutionPanel from '@/components/evolution/EvolutionPanel.vue'
import { portalApi } from '@/api/evolution'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { attendanceStatus, formatDate, initials, weekdayLabel } from '@/utils/format'

const auth = useAuthStore()
const { notify } = useApiError()
const athletes = ref([])
const selectedId = ref(null)
const overview = ref(null)
const loading = ref(true)
const loadingOverview = ref(false)
const tab = ref('evolucao')

const current = computed(() => athletes.value.find((a) => a.id === selectedId.value))
const firstName = computed(() => (auth.user?.name || '').split(' ')[0])

async function loadOverview() {
  if (!selectedId.value) return
  loadingOverview.value = true
  try {
    overview.value = await portalApi.overview(selectedId.value)
  } catch (e) {
    notify(e)
  } finally {
    loadingOverview.value = false
  }
}

watch(selectedId, loadOverview)

onMounted(async () => {
  try {
    athletes.value = await portalApi.athletes()
    selectedId.value = athletes.value[0]?.id ?? null
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.portal { max-width: 1000px; margin: 0 auto; }
.empty { text-align: center; padding: 2rem; }
.empty i { font-size: 2rem; color: var(--iba-gold); }
.hero { display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap; border-top: 3px solid var(--iba-gold); }
.hero__avatar {
  width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0;
  background: var(--iba-black); color: var(--iba-gold-light); border: 3px solid var(--iba-gold);
  font-family: var(--iba-font-title); font-weight: 800; font-size: 1.4rem;
}
.hero__info { flex: 1; min-width: 200px; }
.hello { margin: 0 0 .15rem; color: var(--iba-text-muted); font-size: .85rem; }
.meta { display: flex; gap: 1rem; margin: .4rem 0 0; color: var(--iba-text-muted); font-size: .85rem; }
.tabs { margin-top: 1rem; padding: 0 1rem 1rem; }
.small { font-size: .78rem; }
.kpis { display: flex; gap: 2rem; flex-wrap: wrap; margin: .5rem 0 1rem; }
.kpi { display: flex; flex-direction: column; }
.kpi span { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; color: var(--iba-text-muted); }
.kpi strong { font-family: var(--iba-font-title); font-size: 1.4rem; }
.p { color: var(--iba-success); }
.f { color: var(--iba-danger); }
.j { color: var(--iba-blue); }
.list { list-style: none; padding: 0; margin: 0; display: grid; gap: .3rem; }
.list li { display: flex; justify-content: space-between; gap: 1rem; font-size: .88rem; padding: .35rem 0; border-bottom: 1px solid var(--iba-border); }
.st { font-weight: 600; white-space: nowrap; }
.st--presente { color: var(--iba-success); }
.st--falta { color: var(--iba-danger); }
.st--justificada { color: var(--iba-blue); }
.classes { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: .75rem; }
.class { display: flex; flex-direction: column; gap: .3rem; padding: .9rem 1rem; border: 1px solid var(--iba-border); border-left: 4px solid var(--iba-gold); border-radius: 10px; }
.when { font-weight: 600; display: flex; gap: .4rem; align-items: center; }
</style>
