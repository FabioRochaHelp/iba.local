<template>
  <div>
    <div class="head">
      <span class="iba-muted small">{{ orders.length }} pedido(s)</span>
      <Button label="Novo pedido" icon="pi pi-plus" size="small" outlined @click="dialog = true" />
    </div>
    <DataTable :value="orders" :loading="loading" size="small" data-key="id" row-hover class="ath-uni" @row-click="open($event.data.id)">
      <template #empty><p class="iba-muted empty">Nenhum pedido de uniforme.</p></template>
      <Column header="Item">
        <template #body="{ data }">{{ data.item_name }}<span v-if="data.size" class="iba-muted"> · {{ data.size }}</span></template>
      </Column>
      <Column header="Data"><template #body="{ data }">{{ formatDate(data.ordered_at) }}</template></Column>
      <Column header="Total" class="num"><template #body="{ data }">{{ formatMoney(data.total) }}</template></Column>
      <Column header="Pagamento"><template #body="{ data }"><UniformStatusTag :status="data.status" /></template></Column>
      <Column header="Entrega">
        <template #body="{ data }">
          <span v-if="data.delivered" class="ok"><i class="pi pi-check" aria-hidden="true"></i> Entregue</span>
          <span v-else-if="data.status !== 'cancelado'" class="iba-muted">Pendente</span>
        </template>
      </Column>
    </DataTable>
    <UniformOrderDialog v-model:visible="dialog" :fixed-athlete="{ id: athleteId, name: athleteName }" @created="load" />
    <UniformOrderDrawer v-model:visible="drawerOpen" :order-id="drawerId" @changed="load" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import UniformStatusTag from './UniformStatusTag.vue'
import UniformOrderDialog from './UniformOrderDialog.vue'
import UniformOrderDrawer from './UniformOrderDrawer.vue'
import { uniformsApi } from '@/api/uniforms'
import { useApiError } from '@/composables/useApiError'
import { formatDate, formatMoney } from '@/utils/format'

const props = defineProps({ athleteId: { type: Number, required: true }, athleteName: { type: String, default: '' } })
const { notify } = useApiError()
const orders = ref([])
const loading = ref(false)
const dialog = ref(false)
const drawerOpen = ref(false)
const drawerId = ref(null)

async function load() {
  loading.value = true
  try {
    orders.value = (await uniformsApi.orders({ athlete_id: props.athleteId, status: undefined, per_page: 100 })).data
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

function open(id) {
  drawerId.value = id
  drawerOpen.value = true
}

onMounted(load)
</script>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .75rem; }
.small { font-size: .82rem; }
.ok { color: var(--iba-success); }
.ath-uni :deep(tr) { cursor: pointer; }
.ath-uni :deep(td.num), .ath-uni :deep(th.num) { text-align: right; }
.empty { text-align: center; padding: 1rem; }
</style>
