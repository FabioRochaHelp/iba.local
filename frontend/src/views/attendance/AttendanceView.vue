<template>
  <div class="attendance">
    <!-- Escolha da turma -->
    <template v-if="!classId">
      <div class="iba-page-header">
        <div>
          <h1>Chamada</h1>
          <p>{{ weekdayLabel(todayWeekday) }}, {{ formatDate(today) }}</p>
        </div>
      </div>
      <div v-if="loadingClasses" class="iba-card"><ProgressSpinner style="width: 36px; height: 36px" /></div>
      <template v-else>
        <h2 class="section-title">Turmas de hoje</h2>
        <p v-if="!todayClasses.length" class="iba-muted">Nenhuma turma sua hoje.</p>
        <div class="pick">
          <RouterLink v-for="c in todayClasses" :key="c.id" :to="{ name: 'attendance-class', params: { classId: c.id } }" class="iba-card pick__item pick__item--today">
            <strong>{{ c.name }}</strong>
            <span>{{ c.start_time }}–{{ c.end_time }} · {{ c.athletes_count }} atleta(s)</span>
            <i class="pi pi-angle-right" aria-hidden="true"></i>
          </RouterLink>
        </div>
        <template v-if="otherClasses.length">
          <h2 class="section-title">Outras turmas</h2>
          <div class="pick">
            <RouterLink v-for="c in otherClasses" :key="c.id" :to="{ name: 'attendance-class', params: { classId: c.id } }" class="iba-card pick__item">
              <strong>{{ c.name }}</strong>
              <span>{{ weekdayLabel(c.weekday) }} · {{ c.start_time }}</span>
              <i class="pi pi-angle-right" aria-hidden="true"></i>
            </RouterLink>
          </div>
        </template>
      </template>
    </template>

    <!-- Lista de chamada -->
    <template v-else>
      <header class="sheet-head iba-card">
        <div class="sheet-head__top">
          <RouterLink :to="{ name: 'attendance' }" aria-label="Voltar para a lista de turmas"><Button icon="pi pi-arrow-left" text rounded severity="secondary" /></RouterLink>
          <div class="sheet-head__title">
            <h1>{{ session?.class.name || 'Chamada' }}</h1>
            <p v-if="session" class="iba-muted">{{ weekdayLabel(session.class.weekday) }} · {{ session.class.start_time }}–{{ session.class.end_time }}</p>
          </div>
        </div>
        <div class="sheet-head__controls">
          <label for="att-date" class="sr-only">Data da chamada</label>
          <InputText id="att-date" v-model="date" type="date" :max="today" class="date" />
          <Button label="Todos presentes" icon="pi pi-check-circle" size="small" outlined :disabled="!rows.length" @click="markAll('presente')" />
        </div>
        <Message v-if="session && dayMismatch" severity="warn" :closable="false" class="msg">
          Esta turma treina às {{ weekdayLabel(session.class.weekday).toLowerCase() }}s — confira a data (reposição?).
        </Message>
        <div v-if="rows.length" class="counters" aria-live="polite">
          <span class="c c--p"><i class="pi pi-check" aria-hidden="true"></i> {{ counts.presente }} presentes</span>
          <span class="c c--f"><i class="pi pi-times" aria-hidden="true"></i> {{ counts.falta }} faltas</span>
          <span class="c c--j"><i class="pi pi-file" aria-hidden="true"></i> {{ counts.justificada }} justif.</span>
          <span class="c c--n">{{ counts.none }} sem marcar</span>
        </div>
      </header>

      <div v-if="loadingSession" class="iba-card"><ProgressSpinner style="width: 36px; height: 36px" /></div>
      <p v-else-if="session && !rows.length" class="iba-card iba-muted">Nenhum atleta nesta turma. Peça ao administrador para incluir os atletas.</p>

      <ul v-else class="roster">
        <li v-for="r in rows" :key="r.athlete_id" :class="['iba-card row', r.status && `row--${r.status}`]">
          <div class="row__who">
            <span class="row__name">{{ r.name }}</span>
            <button
              v-if="r.has_health_condition" type="button" class="health" :aria-label="`Condição de saúde de ${r.name}: ${r.health_condition}`"
              @click="toast.add({ severity: 'warn', summary: r.name, detail: r.health_condition, life: 6000 })"
            >
              <i class="pi pi-heart-fill" aria-hidden="true"></i>
            </button>
          </div>
          <div class="row__buttons" role="radiogroup" :aria-label="`Presença de ${r.name}`">
            <button
              v-for="(info, key) in attendanceStatus" :key="key" type="button" role="radio" :aria-checked="r.status === key"
              :class="['st', `st--${key}`, { active: r.status === key }]" :aria-label="`${info.label}: ${r.name}`" @click="setStatus(r, key)"
            >
              <i :class="info.icon" aria-hidden="true"></i><span>{{ info.short }}</span>
            </button>
          </div>
          <div v-if="r.status === 'justificada' || r.note" class="row__note">
            <InputText v-model="r.note" maxlength="255" placeholder="Motivo (ex.: atestado, viagem)" :aria-label="`Observação de ${r.name}`" fluid @input="dirty = true" />
          </div>
        </li>
      </ul>

      <div v-if="rows.length" class="notes iba-card">
        <label for="att-notes">Observações do treino</label>
        <InputText id="att-notes" v-model="notes" maxlength="255" placeholder="Ex.: treino tático, chuva..." fluid @input="dirty = true" />
      </div>

      <div v-if="rows.length" class="savebar">
        <span class="savebar__info">{{ marked }}/{{ rows.length }} marcados<template v-if="dirty"> · não salvo</template></span>
        <Button label="Salvar chamada" icon="pi pi-save" :loading="saving" :disabled="!marked" @click="save" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { classesApi } from '@/api/classes'
import { useApiError } from '@/composables/useApiError'
import { attendanceStatus, formatDate, isoWeekday, todayIso, weekdayLabel } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { notify } = useApiError()

const today = todayIso()
const todayWeekday = isoWeekday()
const classId = computed(() => route.params.classId)
const validDate = (d) => (/^\d{4}-\d{2}-\d{2}$/.test(d || '') && d <= today ? d : today)
const date = ref(validDate(route.query.data))

const classes = ref([])
const loadingClasses = ref(false)
const session = ref(null)
const rows = ref([])
const notes = ref('')
const loadingSession = ref(false)
const saving = ref(false)
const dirty = ref(false)

const todayClasses = computed(() => classes.value.filter((c) => c.active && c.weekday === todayWeekday))
const otherClasses = computed(() => classes.value.filter((c) => c.active && c.weekday !== todayWeekday))
const counts = computed(() => {
  const c = { presente: 0, falta: 0, justificada: 0, none: 0 }
  for (const r of rows.value) c[r.status || 'none']++
  return c
})
const marked = computed(() => rows.value.length - counts.value.none)
const dayMismatch = computed(() => {
  if (!session.value) return false
  const [y, m, d] = session.value.session_date.split('-').map(Number)
  return isoWeekday(new Date(y, m - 1, d)) !== session.value.class.weekday
})

async function loadClasses() {
  loadingClasses.value = true
  try {
    classes.value = await classesApi.list({ active: 1 })
  } catch (e) {
    notify(e)
  } finally {
    loadingClasses.value = false
  }
}

function applySession(s) {
  session.value = s
  rows.value = s.roster.map((r) => ({ ...r, note: r.note || '' }))
  notes.value = s.notes || ''
  dirty.value = false
}

async function openSession() {
  if (!classId.value) return
  loadingSession.value = true
  try {
    applySession(await classesApi.openSession(classId.value, date.value))
  } catch (e) {
    notify(e)
    if ([403, 404].includes(e.response?.status)) router.replace({ name: 'attendance' })
  } finally {
    loadingSession.value = false
  }
}

function setStatus(r, status) {
  r.status = r.status === status ? null : status
  dirty.value = true
}

function markAll(status) {
  for (const r of rows.value) if (!r.status) r.status = status
  dirty.value = true
}

async function save() {
  saving.value = true
  try {
    const records = rows.value
      .filter((r) => r.status)
      .map((r) => ({ athlete_id: r.athlete_id, status: r.status, note: r.note?.trim() || null }))
    applySession(await classesApi.saveAttendance(session.value.id, { records, notes: notes.value || null }))
    toast.add({
      severity: 'success',
      summary: 'Chamada salva',
      detail: counts.value.none ? `${counts.value.none} atleta(s) ficaram sem marcação.` : `${counts.value.presente} presentes, ${counts.value.falta} faltas.`,
      life: 3500
    })
  } catch (e) {
    notify(e)
  } finally {
    saving.value = false
  }
}

watch(date, (d, old) => {
  if (d === old || !classId.value) return
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d || '') || d > today) {
    date.value = old
    return
  }
  router.replace({ query: { ...route.query, data: d } })
  openSession()
})

watch(classId, (id) => {
  if (id) openSession()
  else loadClasses()
})

onBeforeRouteLeave((to, from, next) => {
  if (!dirty.value) return next()
  confirm.require({
    header: 'Chamada não salva',
    message: 'Você marcou presenças que ainda não foram salvas. Sair mesmo assim?',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Continuar na chamada', severity: 'secondary', text: true },
    acceptProps: { label: 'Sair sem salvar', severity: 'danger' },
    accept: () => next(),
    reject: () => next(false)
  })
})

onMounted(() => (classId.value ? openSession() : loadClasses()))
</script>

<style scoped>
.attendance { max-width: 720px; margin: 0 auto; padding-bottom: 5rem; }
.section-title { font-size: .85rem; margin: 1.25rem 0 .6rem; }
.pick { display: grid; gap: .6rem; }
.pick__item { display: grid; grid-template-columns: 1fr auto; grid-template-rows: auto auto; gap: .15rem 1rem; align-items: center; text-decoration: none; color: inherit; padding: 1rem 1.1rem; }
.pick__item span { font-size: .85rem; color: var(--iba-text-muted); grid-column: 1; }
.pick__item i { grid-row: 1 / span 2; grid-column: 2; color: var(--iba-gold); font-size: 1.2rem; }
.pick__item--today { border-left: 4px solid var(--iba-gold); }

.sheet-head { position: sticky; top: calc(var(--iba-topbar-h) + .25rem); z-index: 5; padding: .75rem 1rem; margin-bottom: .75rem; }
.sheet-head__top { display: flex; align-items: center; gap: .25rem; }
.sheet-head__title h1 { font-size: 1.05rem; }
.sheet-head__title p { margin: .1rem 0 0; font-size: .8rem; }
.sheet-head__controls { display: flex; gap: .5rem; align-items: center; margin-top: .6rem; flex-wrap: wrap; }
.date { width: 160px; }
.msg { margin-top: .5rem; }
.counters { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .6rem; font-size: .8rem; font-weight: 600; }
.c { display: inline-flex; gap: .25rem; align-items: center; }
.c--p { color: var(--iba-success); }
.c--f { color: var(--iba-danger); }
.c--j { color: var(--iba-blue); }
.c--n { color: var(--iba-text-muted); font-weight: 500; }

.roster { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
.row { display: grid; grid-template-columns: 1fr auto; gap: .5rem; align-items: center; padding: .6rem .75rem; border-left: 4px solid var(--iba-border); }
.row--presente { border-left-color: var(--iba-success); }
.row--falta { border-left-color: var(--iba-danger); }
.row--justificada { border-left-color: var(--iba-blue); }
.row__who { display: flex; align-items: center; gap: .4rem; min-width: 0; }
.row__name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.health { border: 0; background: none; color: var(--iba-danger); cursor: pointer; padding: .25rem; }
.row__buttons { display: flex; gap: .35rem; }
/* Alvos de toque grandes (≥ 44px) para uso no campo */
.st {
  width: 48px; height: 44px; border-radius: 10px; border: 1.5px solid var(--iba-border); background: var(--iba-card);
  color: var(--iba-text-muted); font-weight: 700; display: grid; place-items: center; cursor: pointer; font-size: .8rem; line-height: 1;
}
.st i { font-size: .95rem; }
.st span { font-size: .65rem; margin-top: .1rem; }
.st--presente.active { background: var(--iba-success); border-color: var(--iba-success); color: #fff; }
.st--falta.active { background: var(--iba-danger); border-color: var(--iba-danger); color: #fff; }
.st--justificada.active { background: var(--iba-blue); border-color: var(--iba-blue); color: #fff; }
.st:focus-visible { outline: 2px solid var(--iba-gold); outline-offset: 2px; }
.row__note { grid-column: 1 / -1; }
.notes { margin-top: .75rem; display: grid; gap: .35rem; }
.notes label { font-weight: 600; font-size: .85rem; }

.savebar {
  position: fixed; left: var(--iba-sidebar-w); right: 0; bottom: 0; z-index: 20;
  display: flex; justify-content: space-between; align-items: center; gap: 1rem;
  padding: .75rem max(1rem, calc((100% - 720px) / 2)); background: var(--iba-card); border-top: 2px solid var(--iba-gold);
  box-shadow: 0 -4px 16px rgba(0, 0, 0, .08);
}
.savebar__info { font-size: .85rem; color: var(--iba-text-muted); }
@media (max-width: 960px) { .savebar { left: 0; } }
</style>
