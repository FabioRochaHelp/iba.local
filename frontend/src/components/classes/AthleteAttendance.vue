<template>
  <div>
    <div class="top">
      <SelectButton v-model="period" :options="periods" option-label="label" option-value="value" :allow-empty="false" aria-label="Período" />
    </div>
    <div v-if="stats" class="kpis">
      <div class="kpi"><span>Frequência</span><strong :class="{ low: stats.rate !== null && stats.rate < 60 }">{{ stats.rate !== null ? `${stats.rate}%` : '—' }}</strong></div>
      <div class="kpi"><span>Presenças</span><strong class="p">{{ stats.present }}</strong></div>
      <div class="kpi"><span>Faltas</span><strong class="f">{{ stats.absent }}</strong></div>
      <div class="kpi"><span>Justificadas</span><strong class="j">{{ stats.justified }}</strong></div>
    </div>
    <p v-if="stats && !stats.total" class="iba-muted">Nenhuma chamada registrada no período.</p>
    <ul v-if="stats?.recent.length" class="list">
      <li v-for="(r, i) in stats.recent" :key="i">
        <span>{{ formatDate(r.session_date) }} · {{ r.class_name }}</span>
        <span :class="['st', `st--${r.status}`]">
          <i :class="attendanceStatus[r.status].icon" aria-hidden="true"></i> {{ attendanceStatus[r.status].label }}
          <small v-if="r.note" class="iba-muted"> — {{ r.note }}</small>
        </span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import SelectButton from 'primevue/selectbutton'
import { classesApi } from '@/api/classes'
import { attendanceStatus, formatDate, todayIso } from '@/utils/format'

const props = defineProps({ athleteId: { type: Number, required: true } })
const stats = ref(null)
const period = ref(90)
const periods = [
  { value: 30, label: '30 dias' },
  { value: 90, label: '90 dias' },
  { value: 365, label: '12 meses' }
]

async function load() {
  const d = new Date()
  d.setDate(d.getDate() - period.value)
  const from = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  try {
    stats.value = await classesApi.athleteAttendance(props.athleteId, { from, to: todayIso() })
  } catch {
    stats.value = null
  }
}

watch(period, load)
onMounted(load)
</script>

<style scoped>
.top { margin-bottom: 1rem; }
.kpis { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; }
.kpi span { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; color: var(--iba-text-muted); }
.kpi strong { font-family: var(--iba-font-title); font-size: 1.4rem; }
.p { color: var(--iba-success); }
.f, .low { color: var(--iba-danger); }
.j { color: var(--iba-blue); }
.list { list-style: none; padding: 0; margin: 0; display: grid; gap: .35rem; }
.list li { display: flex; justify-content: space-between; gap: 1rem; font-size: .88rem; padding: .35rem 0; border-bottom: 1px solid var(--iba-border); }
.st { font-weight: 600; }
.st--presente { color: var(--iba-success); }
.st--falta { color: var(--iba-danger); }
.st--justificada { color: var(--iba-blue); }
</style>
