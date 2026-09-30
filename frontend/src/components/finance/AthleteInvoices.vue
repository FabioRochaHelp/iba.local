<template>
  <div>
    <div class="totals">
      <div><span class="iba-muted">Em aberto</span><strong :class="{ due: Number(openTotal) > 0 }">{{ formatMoney(openTotal) }}</strong></div>
      <div><span class="iba-muted">Pago (total)</span><strong>{{ formatMoney(paidTotal) }}</strong></div>
    </div>

    <DataTable :value="invoices" :loading="loading" size="small" data-key="id" row-hover class="ath-inv" @row-click="open($event.data.id)">
      <template #empty><p class="iba-muted empty">Nenhuma mensalidade registrada.</p></template>
      <Column header="Referência">
        <template #body="{ data }">{{ formatMonth(data.reference_month) }}</template>
      </Column>
      <Column header="Total" class="num">
        <template #body="{ data }">{{ formatMoney(data.final_amount) }}</template>
      </Column>
      <Column header="Pago" class="num">
        <template #body="{ data }">{{ formatMoney(data.paid_amount) }}</template>
      </Column>
      <Column header="Situação">
        <template #body="{ data }"><InvoiceStatusTag :status="data.status" :overdue="data.overdue" /></template>
      </Column>
      <Column header="">
        <template #body="{ data }">
          <Button
            v-if="['aberta', 'parcial'].includes(data.status)" label="Receber" size="small" text icon="pi pi-wallet"
            @click.stop="pay(data)"
          />
        </template>
      </Column>
    </DataTable>

    <PaymentDialog v-model:visible="payOpen" :invoice="payTarget" @paid="load" />
    <InvoiceDrawer v-model:visible="drawerOpen" :invoice-id="drawerId" @changed="load" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InvoiceStatusTag from './InvoiceStatusTag.vue'
import PaymentDialog from './PaymentDialog.vue'
import InvoiceDrawer from './InvoiceDrawer.vue'
import { invoicesApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatMoney, formatMonth } from '@/utils/format'

const props = defineProps({ athleteId: { type: Number, required: true }, athleteName: { type: String, default: '' } })
const { notify } = useApiError()

const invoices = ref([])
const loading = ref(false)
const payOpen = ref(false)
const payTarget = ref(null)
const drawerOpen = ref(false)
const drawerId = ref(null)

const sum = (list, field) => list.reduce((acc, i) => acc + Number(i[field] || 0), 0)
const openTotal = computed(() => sum(invoices.value.filter((i) => ['aberta', 'parcial'].includes(i.status)), 'remaining'))
const paidTotal = computed(() => sum(invoices.value.filter((i) => i.status !== 'cancelada'), 'paid_amount'))

async function load() {
  loading.value = true
  try {
    invoices.value = await invoicesApi.forAthlete(props.athleteId)
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

function pay(inv) {
  payTarget.value = { ...inv, athlete_name: props.athleteName || inv.athlete_name }
  payOpen.value = true
}

function open(id) {
  drawerId.value = id
  drawerOpen.value = true
}

onMounted(load)
</script>

<style scoped>
.totals { display: flex; gap: 2rem; margin-bottom: 1rem; }
.totals div { display: flex; flex-direction: column; }
.totals span { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.totals strong { font-family: var(--iba-font-title); font-size: 1.25rem; }
.due { color: var(--iba-danger); }
.ath-inv :deep(tr) { cursor: pointer; }
.ath-inv :deep(td.num), .ath-inv :deep(th.num) { text-align: right; }
.empty { text-align: center; padding: 1rem; }
</style>
