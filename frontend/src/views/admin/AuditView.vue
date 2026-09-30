<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Auditoria</h1>
        <p>Registro de quem fez o quê, quando e de onde. Somente leitura.</p>
      </div>
    </div>

    <section class="iba-card filters">
      <Select v-model="entity" :options="entities" option-label="label" option-value="value" placeholder="Área" show-clear aria-label="Área" />
      <Select v-model="action" :options="actionOptions" option-label="label" option-value="value" placeholder="Ação" show-clear filter aria-label="Ação" />
    </section>

    <section class="iba-card table-card">
      <DataTable
        :value="rows" lazy :loading="loading" :total-records="total" :rows="perPage" :first="first" paginator
        :rows-per-page-options="[25, 50, 100]" data-key="id" size="small"
        current-page-report-template="{first}–{last} de {totalRecords}"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        @page="onPage"
      >
        <template #empty><p class="empty">Nenhum registro.</p></template>
        <Column header="Quando">
          <template #body="{ data }"><span class="nowrap">{{ formatDate(data.created_at) }} {{ String(data.created_at).slice(11, 16) }}</span></template>
        </Column>
        <Column header="Usuário">
          <template #body="{ data }">{{ data.user_name || 'sistema' }}</template>
        </Column>
        <Column header="Ação">
          <template #body="{ data }">
            <span :class="['act', danger.includes(data.action) && 'act--danger']">
              <i :class="iconFor(data.action)" aria-hidden="true"></i> {{ actionLabel(data.action) }}
            </span>
          </template>
        </Column>
        <Column header="Registro" class="col-md">
          <template #body="{ data }">
            <RouterLink v-if="linkFor(data)" :to="linkFor(data)">{{ entityLabel(data.entity) }} #{{ data.entity_id }}</RouterLink>
            <span v-else-if="data.entity">{{ entityLabel(data.entity) }}<template v-if="data.entity_id"> #{{ data.entity_id }}</template></span>
          </template>
        </Column>
        <Column header="Detalhes" class="col-md">
          <template #body="{ data }"><span class="details">{{ summarize(data.payload) }}</span></template>
        </Column>
        <Column header="IP" class="col-lg">
          <template #body="{ data }"><code class="ip">{{ data.ip }}</code></template>
        </Column>
      </DataTable>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import { auditApi } from '@/api/admin'
import { useApiError } from '@/composables/useApiError'
import { formatDate } from '@/utils/format'

const { notify } = useApiError()
const rows = ref([])
const total = ref(0)
const loading = ref(false)
const perPage = ref(50)
const first = ref(0)
const entity = ref(null)
const action = ref(null)

const ACTIONS = {
  login: 'Entrou no sistema', login_failed: 'Tentativa de login falhou', logout: 'Saiu do sistema', password_changed: 'Trocou a senha',
  user_created: 'Criou usuário', user_updated: 'Alterou usuário', user_password_reset: 'Gerou nova senha',
  athlete_created: 'Cadastrou atleta', athlete_updated: 'Alterou atleta', athlete_deleted: 'Excluiu atleta',
  guardian_created: 'Cadastrou responsável', guardian_updated: 'Alterou responsável', guardian_deleted: 'Excluiu responsável',
  spreadsheet_imported: 'Importou planilha',
  plan_created: 'Criou plano', plan_updated: 'Alterou plano',
  invoices_generated: 'Gerou mensalidades', invoice_updated: 'Ajustou mensalidade', invoice_cancelled: 'Cancelou mensalidade',
  payment_registered: 'Recebeu mensalidade', payment_reversed: 'Estornou pagamento',
  uniform_item_created: 'Criou item de uniforme', uniform_item_updated: 'Alterou item de uniforme',
  uniform_order_created: 'Criou pedido de uniforme', uniform_payment_registered: 'Recebeu uniforme', uniform_payment_reversed: 'Estornou uniforme',
  uniform_delivered: 'Entregou uniforme', uniform_delivery_undone: 'Desfez entrega', uniform_order_cancelled: 'Cancelou pedido',
  sponsor_created: 'Cadastrou patrocinador', sponsor_updated: 'Alterou patrocinador', sponsor_deleted: 'Excluiu patrocinador',
  sponsorship_created: 'Lançou patrocínio', sponsorship_deleted: 'Excluiu patrocínio',
  class_created: 'Criou turma', class_updated: 'Alterou turma', class_athletes_updated: 'Alterou atletas da turma',
  attendance_saved: 'Salvou chamada',
  evaluation_created: 'Registrou avaliação', evaluation_deleted: 'Excluiu avaliação',
  measurement_created: 'Registrou medidas', measurement_deleted: 'Excluiu medidas',
  coach_note_created: 'Registrou observação', coach_note_visibility: 'Alterou visibilidade da observação', coach_note_deleted: 'Excluiu observação',
  goal_created: 'Criou meta', goal_updated: 'Atualizou meta', goal_deleted: 'Excluiu meta',
  criterion_created: 'Criou critério de avaliação', criterion_updated: 'Alterou critério de avaliação'
}
const ENTITIES = {
  user: 'Usuário', athlete: 'Atleta', guardian: 'Responsável', plan: 'Plano', invoice: 'Mensalidade',
  uniform_item: 'Item', uniform_order: 'Pedido', sponsor: 'Patrocinador', sponsorship: 'Patrocínio',
  class: 'Turma', attendance_session: 'Chamada'
}
const danger = ['evaluation_deleted', 'coach_note_deleted', 'login_failed', 'payment_reversed', 'uniform_payment_reversed', 'invoice_cancelled', 'athlete_deleted', 'sponsorship_deleted', 'user_password_reset']

const entities = Object.entries(ENTITIES).map(([value, label]) => ({ value, label }))
const actionOptions = Object.entries(ACTIONS).map(([value, label]) => ({ value, label }))
const actionLabel = (a) => ACTIONS[a] || a
const entityLabel = (e) => ENTITIES[e] || e
const iconFor = (a) => (a.startsWith('login') || a === 'logout' ? 'pi pi-sign-in'
  : a.includes('payment') || a.includes('invoice') ? 'pi pi-wallet'
    : a.includes('athlete') || a.includes('guardian') ? 'pi pi-users'
      : a.includes('attendance') || a.includes('class') ? 'pi pi-calendar'
        : a.includes('user') || a.includes('password') ? 'pi pi-key' : 'pi pi-history')

function linkFor(row) {
  if (!row.entity_id) return null
  if (row.entity === 'athlete') return { name: 'athlete-show', params: { id: row.entity_id } }
  if (row.entity === 'class') return { name: 'class-show', params: { id: row.entity_id } }
  return null
}

/** Resumo em texto puro (nunca HTML) do payload. */
function summarize(payload) {
  if (!payload || typeof payload !== 'object') return ''
  return Object.entries(payload)
    .map(([k, v]) => `${k}: ${typeof v === 'object' && v !== null ? JSON.stringify(v) : v}`)
    .join(' · ')
    .slice(0, 160)
}

async function load() {
  loading.value = true
  try {
    const res = await auditApi.list({
      page: Math.floor(first.value / perPage.value) + 1,
      per_page: perPage.value,
      entity: entity.value || undefined,
      action: action.value || undefined
    })
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

watch([entity, action], () => {
  first.value = 0
  load()
})

function onPage(e) {
  first.value = e.first
  perPage.value = e.rows
  load()
}

onMounted(load)
</script>

<style scoped>
.filters { display: flex; gap: .75rem; flex-wrap: wrap; padding: 1rem; margin-bottom: 1rem; }
.filters > * { min-width: 200px; }
.table-card { padding: 0; overflow: hidden; }
.nowrap { white-space: nowrap; }
.act { display: inline-flex; gap: .4rem; align-items: center; }
.act i { color: var(--iba-text-muted); font-size: .8rem; }
.act--danger { color: var(--iba-danger); font-weight: 500; }
.act--danger i { color: var(--iba-danger); }
.details { font-size: .78rem; color: var(--iba-text-muted); }
.ip { font-size: .75rem; }
.empty { text-align: center; padding: 1.5rem; color: var(--iba-text-muted); }
@media (max-width: 960px) { :deep(.col-lg) { display: none; } }
@media (max-width: 640px) { :deep(.col-md) { display: none; } }
</style>
