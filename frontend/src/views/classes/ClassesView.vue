<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Turmas</h1>
        <p>{{ auth.isAdmin ? 'Turmas por dia, horário e categoria.' : 'Suas turmas.' }}</p>
      </div>
      <div class="header-actions">
        <RouterLink :to="{ name: 'attendance' }"><Button label="Fazer chamada" icon="pi pi-check-square" outlined /></RouterLink>
        <Button v-if="auth.isAdmin" label="Nova turma" icon="pi pi-plus" @click="openForm()" />
      </div>
    </div>

    <section v-if="!loading && !classes.length" class="iba-card empty">
      <i class="pi pi-calendar" aria-hidden="true"></i>
      <p v-if="auth.isAdmin">Nenhuma turma cadastrada. Crie as turmas de segunda e quarta para começar a fazer chamada.</p>
      <p v-else>Você ainda não é professor de nenhuma turma. Fale com o administrador.</p>
    </section>

    <div v-for="day in groupedByDay" :key="day.weekday" class="day">
      <h2 class="day__title">{{ weekdayLabel(day.weekday) }}<span v-if="day.weekday === today" class="today">hoje</span></h2>
      <div class="cards">
        <article v-for="c in day.classes" :key="c.id" :class="['iba-card class-card', { inactive: !c.active }]">
          <RouterLink :to="{ name: 'class-show', params: { id: c.id } }" class="class-card__link">
            <header>
              <h3>{{ c.name }}</h3>
              <Tag v-if="!c.active" value="Inativa" severity="secondary" rounded />
              <Tag v-else-if="c.category" :value="c.category" rounded class="cat" />
            </header>
            <p class="time"><i class="pi pi-clock" aria-hidden="true"></i> {{ c.start_time }} – {{ c.end_time }}</p>
            <p class="meta">
              <span><i class="pi pi-users" aria-hidden="true"></i> {{ c.athletes_count }} atleta(s)</span>
              <span><i class="pi pi-user" aria-hidden="true"></i> {{ c.coach_name || 'sem professor' }}</span>
            </p>
            <p class="iba-muted small">Última chamada: {{ c.last_session_date ? formatDate(c.last_session_date) : 'nenhuma' }}</p>
          </RouterLink>
          <footer v-if="c.active">
            <RouterLink :to="{ name: 'attendance-class', params: { classId: c.id } }">
              <Button label="Chamada" icon="pi pi-check-square" size="small" />
            </RouterLink>
            <Button v-if="auth.isAdmin" label="Editar" icon="pi pi-pencil" size="small" text @click="openForm(c)" />
          </footer>
        </article>
      </div>
    </div>

    <Dialog v-model:visible="dialog" :header="editing ? 'Editar turma' : 'Nova turma'" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '95vw' }">
      <form id="class-form" class="cform" novalidate @submit.prevent="save">
        <div class="field">
          <label for="cl-name">Nome *</label>
          <InputText id="cl-name" v-model="form.name" maxlength="80" placeholder="Ex.: Sub-13 · Segunda" :invalid="!!errors.name" fluid />
          <small v-if="errors.name" class="field__error">{{ errors.name }}</small>
        </div>
        <div class="row3">
          <div class="field">
            <label for="cl-day">Dia *</label>
            <Select v-model="form.weekday" input-id="cl-day" :options="WEEKDAYS" option-label="label" option-value="value" fluid />
          </div>
          <div class="field">
            <label for="cl-start">Início *</label>
            <InputText id="cl-start" v-model="form.start_time" type="time" :invalid="!!errors.start_time" fluid />
          </div>
          <div class="field">
            <label for="cl-end">Término *</label>
            <InputText id="cl-end" v-model="form.end_time" type="time" :invalid="!!errors.end_time" fluid />
            <small v-if="errors.end_time" class="field__error">{{ errors.end_time }}</small>
          </div>
        </div>
        <div class="row3">
          <div class="field">
            <label for="cl-cat">Categoria</label>
            <InputText id="cl-cat" v-model="form.category" maxlength="20" placeholder="Sub-13" fluid />
          </div>
          <div class="field">
            <label for="cl-min">Nascidos de</label>
            <InputNumber v-model="form.min_birth_year" input-id="cl-min" :use-grouping="false" :min="1990" :max="2100" placeholder="2012" fluid />
          </div>
          <div class="field">
            <label for="cl-max">até</label>
            <InputNumber
              v-model="form.max_birth_year" input-id="cl-max" :use-grouping="false" :min="1990" :max="2100" placeholder="2014"
              :invalid="!!errors.max_birth_year" fluid
            />
          </div>
        </div>
        <div class="field">
          <label for="cl-coach">Professor responsável</label>
          <Select
            v-model="form.coach_id" input-id="cl-coach" :options="coaches" option-label="name" option-value="id" placeholder="Selecione"
            show-clear :invalid="!!errors.coach_id" fluid
          />
          <small class="iba-muted">Só o professor responsável (e o administrador) faz a chamada desta turma.</small>
        </div>
        <label v-if="editing" class="toggle"><ToggleSwitch v-model="form.active" input-id="cl-active" /><span>Turma ativa</span></label>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialog = false" />
        <Button type="submit" form="class-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Tag from 'primevue/tag'
import { classesApi } from '@/api/classes'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { WEEKDAYS, formatDate, isoWeekday, weekdayLabel } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const { notify } = useApiError()

const classes = ref([])
const coaches = ref([])
const loading = ref(true)
const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)
const errors = ref({})
const today = isoWeekday()
const emptyForm = () => ({ name: '', weekday: 1, start_time: '18:00', end_time: '19:00', category: '', min_birth_year: null, max_birth_year: null, coach_id: null, active: true })
const form = reactive(emptyForm())

const groupedByDay = computed(() => {
  const map = new Map()
  for (const c of classes.value) {
    if (!map.has(c.weekday)) map.set(c.weekday, [])
    map.get(c.weekday).push(c)
  }
  return [...map.entries()].sort(([a], [b]) => a - b).map(([weekday, list]) => ({ weekday, classes: list }))
})

async function load() {
  loading.value = true
  try {
    classes.value = await classesApi.list()
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

async function openForm(c = null) {
  editing.value = c
  errors.value = {}
  Object.assign(form, c ? { ...emptyForm(), ...c, category: c.category || '' } : emptyForm())
  if (!coaches.value.length) {
    try {
      coaches.value = await classesApi.coaches()
    } catch (e) {
      notify(e)
    }
  }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    weekday: form.weekday,
    start_time: form.start_time,
    end_time: form.end_time,
    category: form.category || null,
    min_birth_year: form.min_birth_year || null,
    max_birth_year: form.max_birth_year || null,
    coach_id: form.coach_id || null,
    ...(editing.value ? { active: form.active } : {})
  }
  try {
    const saved = editing.value ? await classesApi.update(editing.value.id, payload) : await classesApi.create(payload)
    toast.add({ severity: 'success', summary: 'Turma salva', life: 3000 })
    dialog.value = false
    if (!editing.value) router.push({ name: 'class-show', params: { id: saved.id } })
    else load()
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.empty { text-align: center; padding: 2rem; }
.empty i { font-size: 2rem; color: var(--iba-gold); }
.day { margin-bottom: 1.5rem; }
.day__title { font-size: .95rem; margin-bottom: .75rem; display: flex; align-items: center; gap: .5rem; }
.today { font-family: var(--iba-font-body); text-transform: none; font-size: .7rem; font-weight: 700; background: var(--iba-gold); color: #111; padding: .1rem .5rem; border-radius: 999px; letter-spacing: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
.class-card { border-top: 4px solid var(--iba-gold); display: flex; flex-direction: column; padding: 0; overflow: hidden; }
.class-card.inactive { opacity: .6; border-top-color: var(--iba-border); }
.class-card__link { padding: 1.1rem 1.25rem .5rem; color: inherit; text-decoration: none; flex: 1; }
.class-card header { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.class-card h3 { font-size: .95rem; }
.cat { background: var(--iba-black); color: var(--iba-gold-light); }
.time { font-size: 1.1rem; font-weight: 600; margin: .6rem 0 .3rem; display: flex; gap: .4rem; align-items: center; }
.meta { display: flex; flex-wrap: wrap; gap: .9rem; font-size: .85rem; margin: .3rem 0; }
.meta i, .time i { color: var(--iba-text-muted); }
.small { font-size: .78rem; margin: .3rem 0 0; }
.class-card footer { display: flex; gap: .25rem; padding: .5rem 1rem .9rem; }
.cform { display: grid; gap: 1rem; }
.row3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
.toggle { display: flex; align-items: center; gap: .6rem; }
@media (max-width: 520px) { .row3 { grid-template-columns: 1fr 1fr; } }
</style>
