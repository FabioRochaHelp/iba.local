<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Mensalidades</h1>
        <p>Competência de {{ formatMonth(month) }}</p>
      </div>
      <div class="header-actions">
        <div class="month-nav" role="group" aria-label="Mês de referência">
          <Button icon="pi pi-chevron-left" text rounded severity="secondary" aria-label="Mês anterior" @click="month = shiftMonth(month, -1)" />
          <InputText v-model="month" type="month" aria-label="Mês" class="month-input" />
          <Button icon="pi pi-chevron-right" text rounded severity="secondary" aria-label="Próximo mês" @click="month = shiftMonth(month, 1)" />
        </div>
        <Button label="Gerar mensalidades" icon="pi pi-bolt" :loading="generating" @click="confirmGenerate" />
      </div>
    </div>

    <!-- Resumo (KPIs) -->
    <div class="kpis">
      <article class="iba-card kpi">
        <span class="kpi__label">Previsto</span>
        <strong class="kpi__value">{{ formatMoney(summary.expected) }}</strong>
        <span class="kpi__hint">{{ summary.total_count - summary.cancelled_count || 0 }} mensalidades</span>
      </article>
      <article class="iba-card kpi">
        <span class="kpi__label"><i class="pi pi-check-circle ok" aria-hidden="true"></i> Recebido</span>
        <strong class="kpi__value">{{ formatMoney(summary.received) }}</strong>
        <ProgressBar :value="receivedPct" :show-value="false" class="kpi__bar" :aria-label="`${receivedPct}% recebido`" />
        <span class="kpi__hint">{{ receivedPct }}% do previsto</span>
      </article>
      <article class="iba-card kpi">
        <span class="kpi__label"><i class="pi pi-clock warn" aria-hidden="true"></i> Em aberto</span>
        <strong class="kpi__value">{{ formatMoney(summary.pending) }}</strong>
        <span class="kpi__hint">{{ (summary.open_count || 0) + (summary.partial_count || 0) }} pendentes</span>
      </article>
      <article class="iba-card kpi">
        <span class="kpi__label"><i class="pi pi-exclamation-triangle danger" aria-hidden="true"></i> Atrasado</span>
        <strong class="kpi__value">{{ formatMoney(summary.overdue) }}</strong>
        <span class="kpi__hint">{{ summary.overdue_count || 0 }} vencidas</span>
      </article>
    </div>

    <section class="iba-card filters">
      <SelectButton
        v-model="status" :options="statusFilters" option-label="label" option-value="value" :allow-empty="false"
        aria-label="Filtrar por situação"
      />
      <IconField class="filters__search">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar atleta ou responsável" maxlength="100" aria-label="Buscar" fluid />
      </IconField>
    </section>

    <section class="iba-card table-card">
      <DataTable :value="rows" :loading="loading" data-key="id" row-hover :row-class="rowClass" class="inv-table" @row-click="openDrawer($event.data.id)">
        <template #empty>
          <div class="empty">
            <i class="pi pi-wallet" aria-hidden="true"></i>
            <p v-if="!summary.total_count">Nenhuma mensalidade gerada para {{ formatMonth(month) }}.</p>
            <p v-else>Nenhuma mensalidade nesta situação.</p>
            <Button v-if="!summary.total_count" label="Gerar agora" icon="pi pi-bolt" size="small" @click="confirmGenerate" />
          </div>
        </template>
        <Column header="Atleta">
          <template #body="{ data }">
            <strong>{{ data.athlete_name }}</strong>
            <div class="small iba-muted">{{ data.guardian_name }}</div>
          </template>
        </Column>
        <Column header="Plano" class="col-md">
          <template #body="{ data }">{{ data.plan_name || '—' }}</template>
        </Column>
        <Column header="Total" class="num">
          <template #body="{ data }">
            {{ formatMoney(data.final_amount) }}
            <div v-if="Number(data.discount) > 0" class="small iba-muted">desc. {{ formatMoney(data.discount) }}</div>
          </template>
        </Column>
        <Column header="Pago" class="num col-md">
          <template #body="{ data }">{{ formatMoney(data.paid_amount) }}</template>
        </Column>
        <Column header="Vencimento" class="col-md">
          <template #body="{ data }">{{ formatDate(data.due_date) }}</template>
        </Column>
        <Column header="Situação">
          <template #body="{ data }"><InvoiceStatusTag :status="data.status" :overdue="data.overdue" /></template>
        </Column>
        <Column header="" class="col-actions">
          <template #body="{ data }">
            <div class="actions">
              <Button
                v-if="['aberta', 'parcial'].includes(data.status)" label="Receber" icon="pi pi-wallet" size="small"
                :aria-label="`Receber mensalidade de ${data.athlete_name}`" @click.stop="openPay(data)"
              />
              <Button
                v-tooltip.top="'Detalhes'" icon="pi pi-angle-right" text rounded severity="secondary"
                :aria-label="`Detalhes da mensalidade de ${data.athlete_name}`" @click.stop="openDrawer(data.id)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
      <p v-if="total > rows.length" class="more iba-muted">Mostrando {{ rows.length }} de {{ total }}. Refine a busca.</p>
    </section>

    <PaymentDialog v-model:visible="payOpen" :invoice="payTarget" @paid="refresh" />
    <InvoiceDrawer v-model:visible="drawerOpen" :invoice-id="drawerId" @changed="refresh" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import SelectButton from 'primevue/selectbutton'
import ProgressBar from 'primevue/progressbar'
import InvoiceStatusTag from '@/components/finance/InvoiceStatusTag.vue'
import PaymentDialog from '@/components/finance/PaymentDialog.vue'
import InvoiceDrawer from '@/components/finance/InvoiceDrawer.vue'
import { invoicesApi, reportsApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { currentMonth, debounce, formatDate, formatMoney, formatMonth, shiftMonth } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()

const validMonth = (m) => (/^\d{4}-(0[1-9]|1[0-2])$/.test(m || '') ? m : null)
const month = ref(validMonth(route.query.mes) || currentMonth())
const status = ref(route.query.situacao || 'todas')
const search = ref('')
const rows = ref([])
const total = ref(0)
const summary = ref({})
const loading = ref(false)
const generating = ref(false)

const payOpen = ref(false)
const payTarget = ref(null)
const drawerOpen = ref(false)
const drawerId = ref(null)

const statusFilters = [
  { value: 'todas', label: 'Todas' },
  { value: 'pendente', label: 'Pendentes' },
  { value: 'atrasada', label: 'Atrasadas' },
  { value: 'paga', label: 'Pagas' },
  { value: 'cortesia', label: 'Cortesias' },
  { value: 'cancelada', label: 'Canceladas' }
]

const receivedPct = computed(() => {
  const e = Number(summary.value.expected || 0)
  return e > 0 ? Math.round((Number(summary.value.received || 0) / e) * 100) : 0
})

const rowClass = (d) => (d.status === 'cancelada' ? 'row-cancelled' : '')

async function loadList() {
  loading.value = true
  try {
    const res = await invoicesApi.list({
      month: month.value,
      status: status.value === 'todas' ? undefined : status.value,
      search: search.value || undefined,
      per_page: 100
    })
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

async function loadSummary() {
  try {
    summary.value = (await reportsApi.monthly(month.value)).invoices
  } catch (e) {
    notify(e)
  }
}

function refresh() {
  loadList()
  loadSummary()
}

watch(month, (m) => {
  if (!validMonth(m)) return
  router.replace({ query: { ...route.query, mes: m } })
  refresh()
})
watch(status, (s) => {
  router.replace({ query: { ...route.query, situacao: s } })
  loadList()
})
watch(search, debounce(loadList))

function confirmGenerate() {
  confirm.require({
    header: 'Gerar mensalidades',
    message: `Gerar as mensalidades de ${formatMonth(month.value)} para todos os atletas ativos com plano? Quem já tem mensalidade no mês não é afetado.`,
    icon: 'pi pi-bolt',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Gerar' },
    accept: async () => {
      generating.value = true
      try {
        const r = await invoicesApi.generate(month.value)
        toast.add({
          severity: r.created ? 'success' : 'info',
          summary: r.created ? `${r.created} mensalidade(s) gerada(s)` : 'Nada a gerar',
          detail: r.created ? `Total ${formatMoney(r.total)}${r.skipped ? ` · ${r.skipped} já existiam` : ''}` : 'Todos os atletas ativos já têm mensalidade neste mês.',
          life: 5000
        })
        refresh()
      } catch (e) {
        notify(e)
      } finally {
        generating.value = false
      }
    }
  })
}

function openPay(inv) {
  payTarget.value = inv
  payOpen.value = true
}

function openDrawer(id) {
  drawerId.value = id
  drawerOpen.value = true
}

onMounted(refresh)
</script>

<style scoped>
.header-actions { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
.month-nav { display: flex; align-items: center; gap: .25rem; }
.month-input { width: 170px; }

.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: .2rem; }
.kpi__label { font-size: .8rem; color: var(--iba-text-muted); font-weight: 500; display: flex; gap: .35rem; align-items: center; }
.kpi__value { font-family: var(--iba-font-title); font-weight: 800; font-size: 1.5rem; }
.kpi__hint { font-size: .75rem; color: var(--iba-text-muted); }
.kpi__bar { height: 6px; margin: .2rem 0; }
.ok { color: var(--iba-success); }
.warn { color: var(--iba-gold-text); }
.danger { color: var(--iba-danger); }

.filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; padding: 1rem; margin-bottom: 1rem; }
.filters__search { flex: 1 1 240px; }
.table-card { padding: 0; overflow: hidden; }
.inv-table :deep(tr) { cursor: pointer; }
.inv-table :deep(.row-cancelled) { opacity: .55; }
.inv-table :deep(td.num), .inv-table :deep(th.num) { text-align: right; }
.small { font-size: .8rem; }
.actions { display: flex; justify-content: flex-end; gap: .25rem; align-items: center; }
.empty { text-align: center; padding: 2rem; color: var(--iba-text-muted); display: grid; gap: .5rem; justify-items: center; }
.empty i { font-size: 2rem; color: var(--iba-gold); }
.more { text-align: center; padding: .75rem; font-size: .85rem; }
@media (max-width: 760px) { .inv-table :deep(.col-md) { display: none; } }
</style>
