<template>
  <div class="evo">
    <!-- Resumo -->
    <div class="kpis">
      <div class="kpi">
        <span>Média da última avaliação</span>
        <strong>{{ latest ? formatNumber(latest.average, 1) : '—' }}<small v-if="latest"> / 5</small></strong>
        <em v-if="latestDelta !== null" :class="latestDelta >= 0 ? 'up' : 'down'">
          <i :class="latestDelta >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" aria-hidden="true"></i>
          {{ latestDelta >= 0 ? '+' : '−' }}{{ formatNumber(Math.abs(latestDelta), 1) }} vs. anterior
        </em>
      </div>
      <div class="kpi"><span>Avaliações</span><strong>{{ data.evaluations.length }}</strong></div>
      <div class="kpi"><span>Metas em andamento</span><strong>{{ goalsOpen }}</strong></div>
      <div v-if="lastMeasure" class="kpi"><span>Altura / peso</span><strong class="sm">{{ formatNumber(lastMeasure.height_cm, 1, 0) }} cm · {{ formatNumber(lastMeasure.weight_kg, 1) }} kg</strong></div>
    </div>

    <div class="bar">
      <SelectButton v-model="section" :options="sections" option-label="label" option-value="value" :allow-empty="false" aria-label="Seção da evolução" />
      <div v-if="editable" class="bar__actions">
        <Button v-if="section === 'avaliacoes'" label="Nova avaliação" icon="pi pi-plus" size="small" @click="openEval" />
        <Button v-if="section === 'medidas'" label="Nova medição" icon="pi pi-plus" size="small" @click="openMeasure" />
        <Button v-if="section === 'observacoes'" label="Nova observação" icon="pi pi-plus" size="small" @click="openNote" />
        <Button v-if="section === 'metas'" label="Nova meta" icon="pi pi-plus" size="small" @click="openGoal" />
      </div>
    </div>

    <!-- AVALIAÇÕES -->
    <section v-if="section === 'avaliacoes'">
      <LineChart title="Média geral por avaliação (1 a 5)" :points="averageSeries" value-label="Média" :min="1" :max="5" :digits="1" />
      <template v-if="criteriaRows.length">
        <h3 class="sub">Por critério <small class="iba-muted">(última avaliação × anterior)</small></h3>
        <div class="crit-groups">
          <div v-for="g in criteriaGroups" :key="g.category" class="crit-group">
            <h4>{{ criterionCategories[g.category] }}</h4>
            <ul>
              <li v-for="c in g.rows" :key="c.id">
                <span class="crit-name">{{ c.name }}</span>
                <span class="pips" role="meter" :aria-valuenow="c.last" aria-valuemin="1" aria-valuemax="5" :aria-label="`${c.name}: ${c.last} de 5`">
                  <span v-for="n in 5" :key="n" :class="['pip', { on: n <= c.last }]"></span>
                </span>
                <strong class="crit-score">{{ c.last }}</strong>
                <span v-if="c.prev !== null && c.last !== c.prev" :class="['delta', c.last > c.prev ? 'up' : 'down']">
                  <i :class="c.last > c.prev ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" aria-hidden="true"></i>{{ c.last > c.prev ? '+' : '−' }}{{ Math.abs(c.last - c.prev) }}
                </span>
                <span v-else-if="c.prev !== null" class="delta same" aria-label="sem mudança">=</span>
              </li>
            </ul>
          </div>
        </div>
      </template>
      <h3 class="sub">Histórico</h3>
      <p v-if="!data.evaluations.length" class="iba-muted">Nenhuma avaliação registrada.</p>
      <ul class="entries">
        <li v-for="e in data.evaluations" :key="e.id" class="entry">
          <div class="entry__head">
            <strong>{{ formatDate(e.evaluation_date) }}</strong>
            <span class="iba-muted">· média {{ formatNumber(e.average, 1) }} · {{ Object.keys(e.scores).length }} critério(s)<template v-if="e.evaluator_name"> · {{ e.evaluator_name }}</template></span>
            <Tag v-if="editable && !e.visible_to_athlete" value="Oculta para o atleta" severity="secondary" icon="pi pi-eye-slash" rounded />
            <Button
              v-if="canDelete(e.evaluated_by)" icon="pi pi-trash" text rounded severity="danger" size="small" class="ml-auto"
              :aria-label="`Excluir avaliação de ${formatDate(e.evaluation_date)}`" @click="remove('evaluation', e.id)"
            />
          </div>
          <p v-if="e.general_comment" class="entry__body">{{ e.general_comment }}</p>
        </li>
      </ul>
    </section>

    <!-- MEDIDAS -->
    <section v-if="section === 'medidas'">
      <div class="charts">
        <LineChart title="Altura" :points="series('height_cm')" value-label="Altura" unit="cm" :digits="0" />
        <LineChart title="Peso" :points="series('weight_kg')" value-label="Peso" unit="kg" :digits="1" />
        <LineChart v-if="series('sprint_20m_s').length" title="Velocidade — 20 m (menor é melhor)" :points="series('sprint_20m_s')" value-label="Tempo" unit="s" :digits="2" lower-is-better />
        <LineChart v-if="series('vertical_jump_cm').length" title="Salto vertical" :points="series('vertical_jump_cm')" value-label="Salto" unit="cm" :digits="0" />
      </div>
      <h3 class="sub">Registros</h3>
      <p v-if="!data.measurements.length" class="iba-muted">Nenhuma medição registrada.</p>
      <div v-else class="table-wrap">
        <table class="mtable">
          <thead>
            <tr><th scope="col">Data</th><th scope="col">Altura</th><th scope="col">Peso</th><th scope="col">IMC</th><th scope="col">20 m</th><th scope="col">Salto</th><th scope="col">Resistência</th><th v-if="editable" scope="col"><span class="sr-only">Ações</span></th></tr>
          </thead>
          <tbody>
            <tr v-for="m in data.measurements" :key="m.id">
              <td>{{ formatDate(m.measured_at) }}</td>
              <td>{{ m.height_cm ? `${formatNumber(m.height_cm, 1, 0)} cm` : '—' }}</td>
              <td>{{ m.weight_kg ? `${formatNumber(m.weight_kg, 1)} kg` : '—' }}</td>
              <td>{{ formatNumber(m.bmi, 1) }}</td>
              <td>{{ m.sprint_20m_s ? `${formatNumber(m.sprint_20m_s, 2)} s` : '—' }}</td>
              <td>{{ m.vertical_jump_cm ? `${formatNumber(m.vertical_jump_cm, 1, 0)} cm` : '—' }}</td>
              <td>{{ m.endurance_m ? `${m.endurance_m} m` : '—' }}</td>
              <td v-if="editable">
                <Button
                  v-if="canDelete(m.recorded_by)" icon="pi pi-trash" text rounded severity="danger" size="small"
                  :aria-label="`Excluir medição de ${formatDate(m.measured_at)}`" @click="remove('measurement', m.id)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="iba-muted small">IMC calculado automaticamente. Para crianças e adolescentes, interprete o IMC pelas curvas de crescimento por idade.</p>
    </section>

    <!-- OBSERVAÇÕES -->
    <section v-if="section === 'observacoes'">
      <p v-if="!data.notes.length" class="iba-muted">Nenhuma observação{{ editable ? '' : ' compartilhada' }}.</p>
      <ul class="entries">
        <li v-for="n in data.notes" :key="n.id" :class="['entry', `entry--${n.type}`]">
          <div class="entry__head">
            <Tag :value="noteTypes[n.type].label" :icon="noteTypes[n.type].icon" :severity="noteTypes[n.type].severity" rounded />
            <strong>{{ formatDate(n.note_date) }}</strong>
            <span v-if="n.author_name" class="iba-muted">· {{ n.author_name }}</span>
            <template v-if="editable">
              <Button
                v-if="canDelete(n.author_id)" v-tooltip.top="n.visible_to_athlete ? 'Visível para o atleta — clique para ocultar' : 'Oculta — clique para mostrar ao atleta'"
                :icon="n.visible_to_athlete ? 'pi pi-eye' : 'pi pi-eye-slash'" :label="n.visible_to_athlete ? 'Visível' : 'Interna'" text size="small"
                :severity="n.visible_to_athlete ? 'info' : 'secondary'" class="ml-auto" @click="toggleNote(n)"
              />
              <span v-else class="ml-auto small iba-muted">{{ n.visible_to_athlete ? 'Visível ao atleta' : 'Interna' }}</span>
              <Button v-if="canDelete(n.author_id)" icon="pi pi-trash" text rounded severity="danger" size="small" aria-label="Excluir observação" @click="remove('note', n.id)" />
            </template>
          </div>
          <p class="entry__body">{{ n.content }}</p>
        </li>
      </ul>
    </section>

    <!-- METAS -->
    <section v-if="section === 'metas'">
      <p v-if="!data.goals.length" class="iba-muted">Nenhuma meta definida.</p>
      <ul class="entries">
        <li v-for="g in data.goals" :key="g.id" :class="['entry', 'goal', `goal--${g.status}`]">
          <div class="entry__head">
            <Tag :value="goalStatus[g.status].label" :icon="goalStatus[g.status].icon" :severity="goalStatus[g.status].severity" rounded />
            <strong>{{ g.title }}</strong>
            <span class="iba-muted small">
              <template v-if="g.status === 'atingida' && g.achieved_at">atingida em {{ formatDate(g.achieved_at) }}</template>
              <template v-else-if="g.target_date">prazo {{ formatDate(g.target_date) }}</template>
            </span>
            <div v-if="editable" class="ml-auto goal__actions">
              <Button v-if="g.status !== 'atingida'" label="Atingida" icon="pi pi-trophy" text size="small" severity="success" @click="setGoal(g, 'atingida')" />
              <Button v-if="g.status !== 'em_andamento'" label="Reabrir" icon="pi pi-replay" text size="small" severity="secondary" @click="setGoal(g, 'em_andamento')" />
              <Button
                v-if="g.status === 'em_andamento'" v-tooltip.top="'Cancelar meta'" icon="pi pi-ban" text rounded size="small" severity="secondary"
                aria-label="Cancelar meta" @click="setGoal(g, 'cancelada')"
              />
              <Button v-if="canDelete(g.created_by)" icon="pi pi-trash" text rounded size="small" severity="danger" aria-label="Excluir meta" @click="remove('goal', g.id)" />
            </div>
          </div>
          <p v-if="g.description" class="entry__body">{{ g.description }}</p>
        </li>
      </ul>
    </section>

    <!-- Diálogos (somente equipe) -->
    <template v-if="editable">
      <Dialog v-model:visible="evalOpen" header="Nova avaliação" modal :style="{ width: '640px' }" :breakpoints="{ '680px': '96vw' }">
        <form id="eval-form" class="dform" novalidate @submit.prevent="saveEval">
          <div class="row">
            <div class="field">
              <label for="ev-date">Data</label>
              <InputText id="ev-date" v-model="evalForm.evaluation_date" type="date" :max="today" fluid />
            </div>
            <label class="toggle"><ToggleSwitch v-model="evalForm.visible_to_athlete" input-id="ev-vis" /><span>Visível para o atleta e responsável</span></label>
          </div>
          <p class="iba-muted small">Dê notas de 1 (precisa desenvolver) a 5 (excelente). Critérios sem nota não entram na média.</p>
          <div v-for="g in activeCriteriaGroups" :key="g.category" class="eval-group">
            <h4>{{ criterionCategories[g.category] }}</h4>
            <div v-for="c in g.rows" :key="c.id" class="eval-row">
              <span>{{ c.name }}</span>
              <div class="scores" role="radiogroup" :aria-label="c.name">
                <button
                  v-for="n in 5" :key="n" type="button" role="radio" :aria-checked="evalForm.scores[c.id] === n"
                  :class="['sc', { active: evalForm.scores[c.id] === n }]" :aria-label="`${c.name}: nota ${n}`"
                  @click="evalForm.scores[c.id] = evalForm.scores[c.id] === n ? undefined : n"
                >
                  {{ n }}
                </button>
              </div>
            </div>
          </div>
          <small v-if="errors.scores" class="field__error">{{ errors.scores }}</small>
          <div class="field">
            <label for="ev-comment">Comentário geral</label>
            <Textarea id="ev-comment" v-model="evalForm.general_comment" rows="3" maxlength="2000" auto-resize fluid />
          </div>
        </form>
        <template #footer>
          <span class="iba-muted small mr-auto">{{ scoredCount }} critério(s) avaliados</span>
          <Button label="Cancelar" text severity="secondary" @click="evalOpen = false" />
          <Button type="submit" form="eval-form" label="Salvar avaliação" icon="pi pi-check" :loading="saving" :disabled="!scoredCount" />
        </template>
      </Dialog>

      <Dialog v-model:visible="measureOpen" header="Nova medição" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '96vw' }">
        <form id="measure-form" class="dform" novalidate @submit.prevent="saveMeasure">
          <div class="field">
            <label for="ms-date">Data</label>
            <InputText id="ms-date" v-model="measureForm.measured_at" type="date" :max="today" fluid />
          </div>
          <div class="row">
            <div class="field"><label for="ms-h">Altura (cm)</label><InputNumber v-model="measureForm.height_cm" input-id="ms-h" :min="50" :max="230" :max-fraction-digits="1" locale="pt-BR" fluid /></div>
            <div class="field"><label for="ms-w">Peso (kg)</label><InputNumber v-model="measureForm.weight_kg" input-id="ms-w" :min="10" :max="200" :max-fraction-digits="1" locale="pt-BR" fluid /></div>
          </div>
          <div class="row3">
            <div class="field"><label for="ms-s">20 m (s)</label><InputNumber v-model="measureForm.sprint_20m_s" input-id="ms-s" :min="1" :max="20" :max-fraction-digits="2" locale="pt-BR" fluid /></div>
            <div class="field"><label for="ms-j">Salto (cm)</label><InputNumber v-model="measureForm.vertical_jump_cm" input-id="ms-j" :min="0" :max="150" :max-fraction-digits="1" locale="pt-BR" fluid /></div>
            <div class="field"><label for="ms-e">Resistência (m)</label><InputNumber v-model="measureForm.endurance_m" input-id="ms-e" :min="0" :max="10000" :use-grouping="false" fluid /></div>
          </div>
          <small v-if="errors.height_cm" class="field__error">{{ errors.height_cm }}</small>
          <div class="field"><label for="ms-n">Observação</label><InputText id="ms-n" v-model="measureForm.notes" maxlength="255" fluid /></div>
        </form>
        <template #footer>
          <Button label="Cancelar" text severity="secondary" @click="measureOpen = false" />
          <Button type="submit" form="measure-form" label="Salvar" icon="pi pi-check" :loading="saving" />
        </template>
      </Dialog>

      <Dialog v-model:visible="noteOpen" header="Nova observação" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '96vw' }">
        <form id="note-form" class="dform" novalidate @submit.prevent="saveNote">
          <div class="row">
            <div class="field"><label for="nt-date">Data</label><InputText id="nt-date" v-model="noteForm.note_date" type="date" :max="today" fluid /></div>
            <div class="field">
              <label for="nt-type">Tipo</label>
              <Select v-model="noteForm.type" input-id="nt-type" :options="noteTypeOptions" option-label="label" option-value="value" fluid />
            </div>
          </div>
          <div class="field">
            <label for="nt-content">Observação *</label>
            <Textarea id="nt-content" v-model="noteForm.content" rows="4" maxlength="2000" auto-resize :invalid="!!errors.content" fluid />
            <small v-if="errors.content" class="field__error">{{ errors.content }}</small>
          </div>
          <label class="toggle"><ToggleSwitch v-model="noteForm.visible_to_athlete" input-id="nt-vis" /><span>Mostrar para o atleta e responsável</span></label>
          <p class="iba-muted small">Por padrão a observação é interna (só a equipe vê).</p>
        </form>
        <template #footer>
          <Button label="Cancelar" text severity="secondary" @click="noteOpen = false" />
          <Button type="submit" form="note-form" label="Salvar" icon="pi pi-check" :loading="saving" />
        </template>
      </Dialog>

      <Dialog v-model:visible="goalOpen" header="Nova meta" modal :style="{ width: '480px' }" :breakpoints="{ '520px': '96vw' }">
        <form id="goal-form" class="dform" novalidate @submit.prevent="saveGoal">
          <div class="field">
            <label for="gl-title">Meta *</label>
            <InputText id="gl-title" v-model="goalForm.title" maxlength="120" placeholder="Ex.: melhorar o passe com a perna esquerda" :invalid="!!errors.title" fluid />
            <small v-if="errors.title" class="field__error">{{ errors.title }}</small>
          </div>
          <div class="field"><label for="gl-desc">Detalhes</label><Textarea id="gl-desc" v-model="goalForm.description" rows="2" maxlength="500" auto-resize fluid /></div>
          <div class="field"><label for="gl-date">Prazo</label><InputText id="gl-date" v-model="goalForm.target_date" type="date" fluid /></div>
        </form>
        <template #footer>
          <Button label="Cancelar" text severity="secondary" @click="goalOpen = false" />
          <Button type="submit" form="goal-form" label="Salvar" icon="pi pi-check" :loading="saving" />
        </template>
      </Dialog>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import Tag from 'primevue/tag'
import LineChart from './LineChart.vue'
import { evolutionApi } from '@/api/evolution'
import { useApiError } from '@/composables/useApiError'
import { criterionCategories, formatDate, formatNumber, goalStatus, noteTypes, todayIso } from '@/utils/format'

const props = defineProps({
  data: { type: Object, required: true },
  athleteId: { type: Number, required: true },
  editable: { type: Boolean, default: false },
  userId: { type: Number, default: null },
  isAdmin: { type: Boolean, default: false }
})
const emit = defineEmits(['changed'])

const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()
const today = todayIso()

const section = ref('avaliacoes')
const sections = [
  { value: 'avaliacoes', label: 'Avaliações' },
  { value: 'medidas', label: 'Medidas' },
  { value: 'observacoes', label: 'Observações' },
  { value: 'metas', label: 'Metas' }
]
const noteTypeOptions = Object.entries(noteTypes).map(([value, t]) => ({ value, label: t.label }))

// ---------- derivados ----------
const chronological = computed(() => [...props.data.evaluations].reverse())
const latest = computed(() => props.data.evaluations[0] || null)
const latestDelta = computed(() => {
  const [a, b] = props.data.evaluations
  return a && b && a.average != null && b.average != null ? a.average - b.average : null
})
const goalsOpen = computed(() => props.data.goals.filter((g) => g.status === 'em_andamento').length)
const lastMeasure = computed(() => props.data.measurements.find((m) => m.height_cm && m.weight_kg) || null)
const averageSeries = computed(() => chronological.value.filter((e) => e.average != null).map((e) => ({ date: e.evaluation_date, value: e.average })))

function series(field) {
  return [...props.data.measurements].reverse().filter((m) => m[field] != null).map((m) => ({ date: m.measured_at, value: m[field] }))
}

const criteriaRows = computed(() => {
  const rows = []
  for (const c of props.data.criteria) {
    const withScore = props.data.evaluations.filter((e) => e.scores[c.id] != null)
    if (!withScore.length) continue
    rows.push({ ...c, last: withScore[0].scores[c.id], prev: withScore[1] ? withScore[1].scores[c.id] : null })
  }
  return rows
})
const group = (list) => Object.keys(criterionCategories).map((category) => ({ category, rows: list.filter((c) => c.category === category) })).filter((g) => g.rows.length)
const criteriaGroups = computed(() => group(criteriaRows.value))
const activeCriteriaGroups = computed(() => group(props.data.criteria.filter((c) => c.active)))

const canDelete = (authorId) => props.editable && (props.isAdmin || authorId === props.userId)

// ---------- formulários ----------
const saving = ref(false)
const errors = ref({})
const evalOpen = ref(false)
const evalForm = reactive({ evaluation_date: today, visible_to_athlete: true, general_comment: '', scores: {} })
const scoredCount = computed(() => Object.values(evalForm.scores).filter(Boolean).length)
const measureOpen = ref(false)
const measureForm = reactive({})
const noteOpen = ref(false)
const noteForm = reactive({})
const goalOpen = ref(false)
const goalForm = reactive({})

function openEval() {
  Object.assign(evalForm, { evaluation_date: todayIso(), visible_to_athlete: true, general_comment: '', scores: {} })
  errors.value = {}
  evalOpen.value = true
}
function openMeasure() {
  Object.assign(measureForm, { measured_at: todayIso(), height_cm: null, weight_kg: null, sprint_20m_s: null, vertical_jump_cm: null, endurance_m: null, notes: '' })
  errors.value = {}
  measureOpen.value = true
}
function openNote() {
  Object.assign(noteForm, { note_date: todayIso(), type: 'geral', content: '', visible_to_athlete: false })
  errors.value = {}
  noteOpen.value = true
}
function openGoal() {
  Object.assign(goalForm, { title: '', description: '', target_date: '' })
  errors.value = {}
  goalOpen.value = true
}

async function run(fn, success, close) {
  saving.value = true
  errors.value = {}
  try {
    await fn()
    toast.add({ severity: 'success', summary: success, life: 2500 })
    close?.()
    emit('changed')
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

const saveEval = () => run(() => evolutionApi.addEvaluation(props.athleteId, {
  evaluation_date: evalForm.evaluation_date,
  visible_to_athlete: evalForm.visible_to_athlete,
  general_comment: evalForm.general_comment || null,
  scores: Object.entries(evalForm.scores).filter(([, v]) => v).map(([criterion_id, score]) => ({ criterion_id: Number(criterion_id), score }))
}), 'Avaliação registrada', () => { evalOpen.value = false })

const saveMeasure = () => run(() => evolutionApi.addMeasurement(props.athleteId, { ...measureForm, notes: measureForm.notes || null }),
  'Medição registrada', () => { measureOpen.value = false })

const saveNote = () => run(() => evolutionApi.addNote(props.athleteId, { ...noteForm }), 'Observação registrada', () => { noteOpen.value = false })

const saveGoal = () => run(() => evolutionApi.addGoal(props.athleteId, {
  title: goalForm.title,
  description: goalForm.description || null,
  target_date: goalForm.target_date || null
}), 'Meta criada', () => { goalOpen.value = false })

const toggleNote = (n) => run(() => evolutionApi.setNoteVisibility(n.id, !n.visible_to_athlete),
  n.visible_to_athlete ? 'Observação agora é interna' : 'Observação visível para o atleta')

const setGoal = (g, status) => run(() => evolutionApi.updateGoal(g.id, { status }), status === 'atingida' ? 'Meta atingida! 🏆' : 'Meta atualizada')

function remove(kind, id) {
  const labels = { evaluation: 'a avaliação', measurement: 'a medição', note: 'a observação', goal: 'a meta' }
  const fns = { evaluation: evolutionApi.deleteEvaluation, measurement: evolutionApi.deleteMeasurement, note: evolutionApi.deleteNote, goal: evolutionApi.deleteGoal }
  confirm.require({
    header: 'Excluir registro',
    message: `Excluir ${labels[kind]}? Esta ação não pode ser desfeita.`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Excluir', severity: 'danger' },
    accept: () => run(() => fns[kind](id), 'Registro excluído')
  })
}
</script>

<style scoped>
.kpis { display: flex; flex-wrap: wrap; gap: 1.75rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; }
.kpi span { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; color: var(--iba-text-muted); }
.kpi strong { font-family: var(--iba-font-title); font-size: 1.45rem; }
.kpi strong small { font-size: .8rem; color: var(--iba-text-muted); }
.kpi strong.sm { font-size: 1.05rem; }
.kpi em { font-style: normal; font-size: .75rem; font-weight: 600; }
.up { color: var(--iba-success); }
.down { color: var(--iba-danger); }
.bar :deep(.p-selectbutton) { display: flex; flex-wrap: wrap; }
.bar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
.sub { font-size: .85rem; margin: 1.25rem 0 .6rem; }
.crit-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
.crit-group h4 { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--iba-text-muted); margin: 0 0 .35rem; }
.crit-group ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .3rem; }
.crit-group li { display: grid; grid-template-columns: 1fr auto 18px 44px; gap: .5rem; align-items: center; font-size: .85rem; }
/* pips: mesma rampa (trilho claro, preenchimento na cor da série) */
.pips { display: flex; gap: 3px; }
.pip { width: 14px; height: 8px; border-radius: 2px; background: color-mix(in srgb, var(--c-expected) 18%, transparent); }
.pip.on { background: var(--c-expected); }
.crit-score { text-align: right; }
.delta { font-size: .75rem; font-weight: 700; display: inline-flex; gap: .15rem; align-items: center; }
.delta i { font-size: .65rem; }
.delta.same { color: var(--iba-text-muted); }
.entries { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
.entry { border: 1px solid var(--iba-border); border-left: 4px solid var(--iba-border); border-radius: 8px; padding: .6rem .8rem; }
.entry--ponto_forte, .goal--atingida { border-left-color: var(--iba-success); }
.entry--a_melhorar { border-left-color: var(--iba-gold); }
.entry--comportamento, .goal--em_andamento { border-left-color: var(--iba-blue); }
.goal--cancelada { opacity: .6; }
.entry__head { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; font-size: .88rem; }
.entry__body { margin: .4rem 0 0; white-space: pre-line; font-size: .9rem; }
.ml-auto { margin-left: auto; }
.mr-auto { margin-right: auto; }
.goal__actions { display: flex; gap: .1rem; align-items: center; }
.charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; }
.table-wrap { overflow-x: auto; }
.mtable { width: 100%; border-collapse: collapse; font-size: .85rem; min-width: 560px; }
.mtable th, .mtable td { padding: .4rem; border-bottom: 1px solid var(--iba-border); text-align: left; white-space: nowrap; }
.mtable thead th { font-size: .7rem; text-transform: uppercase; color: var(--iba-text-muted); }
.small { font-size: .78rem; }
.dform { display: grid; gap: .9rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; align-items: end; }
.row3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
.field { display: flex; flex-direction: column; gap: .3rem; }
.field label { font-weight: 600; font-size: .82rem; }
.field__error { color: var(--iba-danger); }
.toggle { display: flex; gap: .5rem; align-items: center; font-size: .85rem; }
.eval-group h4 { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--iba-text-muted); margin: .25rem 0 .35rem; }
.eval-row { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .25rem 0; border-bottom: 1px dashed var(--iba-border); font-size: .88rem; }
.scores { display: flex; gap: .25rem; }
.sc { width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid var(--iba-border); background: var(--iba-card); color: var(--iba-text); font-weight: 700; cursor: pointer; }
.sc.active { background: var(--iba-gold); border-color: var(--iba-gold); color: #111; }
.sc:focus-visible { outline: 2px solid var(--iba-gold); outline-offset: 2px; }
@media (max-width: 560px) { .row, .row3 { grid-template-columns: 1fr; } }
</style>
