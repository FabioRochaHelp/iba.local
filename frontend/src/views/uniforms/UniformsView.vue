<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Uniformes</h1>
        <p>Pedidos, pagamentos e entregas.</p>
      </div>
      <Button label="Novo pedido" icon="pi pi-plus" @click="orderDialog = true" />
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="pedidos">Pedidos</Tab>
        <Tab value="itens">Itens e preços</Tab>
      </TabList>
      <TabPanels class="panels">
        <!-- PEDIDOS -->
        <TabPanel value="pedidos">
          <div class="kpis">
            <article class="iba-card kpi">
              <span class="kpi__label"><i class="pi pi-clock warn" aria-hidden="true"></i> A receber</span>
              <strong class="kpi__value">{{ formatMoney(summary.receivable) }}</strong>
              <span class="kpi__hint">{{ summary.receivable_count || 0 }} pedido(s)</span>
            </article>
            <article class="iba-card kpi">
              <span class="kpi__label"><i class="pi pi-box" aria-hidden="true"></i> A entregar</span>
              <strong class="kpi__value">{{ summary.to_deliver_count || 0 }}</strong>
              <span class="kpi__hint">pedido(s) aguardando</span>
            </article>
            <article class="iba-card kpi">
              <span class="kpi__label"><i class="pi pi-check-circle ok" aria-hidden="true"></i> Recebido (total)</span>
              <strong class="kpi__value">{{ formatMoney(summary.received) }}</strong>
              <span class="kpi__hint">de {{ formatMoney(summary.total) }} em pedidos</span>
            </article>
          </div>

          <section class="iba-card filters">
            <SelectButton v-model="status" :options="statusFilters" option-label="label" option-value="value" :allow-empty="false" aria-label="Situação" />
            <Select v-model="delivery" :options="deliveryFilters" option-label="label" option-value="value" placeholder="Entrega" show-clear aria-label="Entrega" />
            <IconField class="filters__search">
              <InputIcon class="pi pi-search" />
              <InputText v-model="search" placeholder="Buscar atleta ou responsável" maxlength="100" aria-label="Buscar" fluid />
            </IconField>
          </section>

          <section class="iba-card table-card">
            <DataTable :value="orders" :loading="loading" data-key="id" row-hover class="orders-table" @row-click="openDrawer($event.data.id)">
              <template #empty><div class="empty"><i class="pi pi-shopping-bag" aria-hidden="true"></i><p>Nenhum pedido encontrado.</p></div></template>
              <Column header="Atleta">
                <template #body="{ data }">
                  <strong>{{ data.athlete_name }}</strong>
                  <div class="small iba-muted">{{ data.guardian_name }}</div>
                </template>
              </Column>
              <Column header="Item">
                <template #body="{ data }">
                  {{ data.item_name }}
                  <div class="small iba-muted"><template v-if="data.size">Tam. {{ data.size }} · </template>{{ data.quantity }} un.</div>
                </template>
              </Column>
              <Column header="Total" class="num">
                <template #body="{ data }">
                  {{ formatMoney(data.total) }}
                  <div v-if="Number(data.remaining) > 0 && data.status !== 'cancelado'" class="small due">falta {{ formatMoney(data.remaining) }}</div>
                </template>
              </Column>
              <Column header="Pedido" class="col-md">
                <template #body="{ data }">{{ formatDate(data.ordered_at) }}</template>
              </Column>
              <Column header="Pagamento">
                <template #body="{ data }"><UniformStatusTag :status="data.status" /></template>
              </Column>
              <Column header="Entrega" class="col-md">
                <template #body="{ data }">
                  <span v-if="data.delivered" class="delivered"><i class="pi pi-check" aria-hidden="true"></i> Entregue</span>
                  <span v-else-if="data.status !== 'cancelado'" class="iba-muted"><i class="pi pi-box" aria-hidden="true"></i> Pendente</span>
                </template>
              </Column>
              <Column header="" class="col-actions">
                <template #body="{ data }">
                  <div class="actions">
                    <Button
                      v-if="['pendente', 'pago_parcial'].includes(data.status)" label="Receber" icon="pi pi-wallet" size="small"
                      :aria-label="`Receber pedido de ${data.athlete_name}`" @click.stop="openPay(data)"
                    />
                    <Button
                      icon="pi pi-angle-right" text rounded severity="secondary" :aria-label="`Detalhes do pedido de ${data.athlete_name}`"
                      @click.stop="openDrawer(data.id)"
                    />
                  </div>
                </template>
              </Column>
            </DataTable>
          </section>
        </TabPanel>

        <!-- ITENS -->
        <TabPanel value="itens">
          <div class="items-head">
            <p class="iba-muted">O preço do item é sugerido no pedido e pode ser ajustado em cada venda.</p>
            <Button label="Novo item" icon="pi pi-plus" outlined @click="openItem()" />
          </div>
          <div class="items">
            <article v-for="i in items" :key="i.id" :class="['iba-card item', { 'item--inactive': !i.active }]">
              <header><h2>{{ i.name }}</h2><Tag v-if="!i.active" value="Inativo" severity="secondary" rounded /></header>
              <p class="item__price">{{ formatMoney(i.price) }}</p>
              <p class="small iba-muted">Tamanhos: {{ i.sizes.length ? i.sizes.join(', ') : 'padrão' }}</p>
              <p class="small">{{ i.orders_count }} pedido(s)</p>
              <footer>
                <Button label="Editar" icon="pi pi-pencil" text @click="openItem(i)" />
                <Button :label="i.active ? 'Desativar' : 'Ativar'" text severity="secondary" @click="toggleItem(i)" />
              </footer>
            </article>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <UniformOrderDialog v-model:visible="orderDialog" @created="refresh" />
    <UniformOrderDrawer v-model:visible="drawerOpen" :order-id="drawerId" @changed="refresh" />
    <PaymentDialog v-model:visible="payOpen" :invoice="payTarget" :pay-with="uniformsApi.pay" @paid="refresh" />

    <Dialog v-model:visible="itemDialog" :header="editingItem ? 'Editar item' : 'Novo item'" modal :style="{ width: '440px' }" :breakpoints="{ '480px': '95vw' }">
      <form id="item-form" class="iform" novalidate @submit.prevent="saveItem">
        <div class="field">
          <label for="it-name">Nome *</label>
          <InputText id="it-name" v-model="itemForm.name" maxlength="80" :invalid="!!itemErrors.name" fluid />
          <small v-if="itemErrors.name" class="field__error">{{ itemErrors.name }}</small>
        </div>
        <div class="field">
          <label for="it-price">Preço *</label>
          <InputNumber v-model="itemForm.price" input-id="it-price" mode="currency" currency="BRL" locale="pt-BR" :min="0" fluid />
        </div>
        <div class="field">
          <label for="it-sizes">Tamanhos disponíveis</label>
          <MultiSelect v-model="itemForm.sizes" input-id="it-sizes" :options="DEFAULT_SIZES" display="chip" placeholder="Todos os tamanhos padrão" fluid />
        </div>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="itemDialog = false" />
        <Button type="submit" form="item-form" label="Salvar" icon="pi pi-check" :loading="savingItem" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
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
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import UniformStatusTag from '@/components/uniforms/UniformStatusTag.vue'
import UniformOrderDialog from '@/components/uniforms/UniformOrderDialog.vue'
import UniformOrderDrawer from '@/components/uniforms/UniformOrderDrawer.vue'
import PaymentDialog from '@/components/finance/PaymentDialog.vue'
import { uniformsApi } from '@/api/uniforms'
import { useApiError } from '@/composables/useApiError'
import { DEFAULT_SIZES, debounce, formatDate, formatMoney } from '@/utils/format'

const toast = useToast()
const { notify } = useApiError()

const tab = ref('pedidos')
const orders = ref([])
const summary = ref({})
const loading = ref(false)
const status = ref('todos')
const delivery = ref(null)
const search = ref('')
const orderDialog = ref(false)
const drawerOpen = ref(false)
const drawerId = ref(null)
const payOpen = ref(false)
const payTarget = ref(null)

const items = ref([])
const itemDialog = ref(false)
const editingItem = ref(null)
const savingItem = ref(false)
const itemErrors = ref({})
const itemForm = reactive({ name: '', price: 0, sizes: [] })

const statusFilters = [
  { value: 'todos', label: 'Todos' },
  { value: 'a_receber', label: 'A receber' },
  { value: 'pago', label: 'Pagos' },
  { value: 'cancelado', label: 'Cancelados' }
]
const deliveryFilters = [
  { value: 'pendente', label: 'A entregar' },
  { value: 'entregue', label: 'Entregues' }
]

async function loadOrders() {
  loading.value = true
  try {
    const res = await uniformsApi.orders({
      status: status.value === 'todos' ? undefined : status.value,
      delivery: delivery.value || undefined,
      search: search.value || undefined,
      per_page: 100
    })
    orders.value = res.data
    summary.value = res.meta.summary
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

async function loadItems() {
  try {
    items.value = await uniformsApi.items()
  } catch (e) {
    notify(e)
  }
}

function refresh() {
  loadOrders()
  loadItems()
}

watch([status, delivery], loadOrders)
watch(search, debounce(loadOrders))

function openDrawer(id) {
  drawerId.value = id
  drawerOpen.value = true
}

function openPay(order) {
  payTarget.value = order
  payOpen.value = true
}

function openItem(item = null) {
  editingItem.value = item
  itemErrors.value = {}
  Object.assign(itemForm, item ? { name: item.name, price: Number(item.price), sizes: [...item.sizes] } : { name: '', price: 0, sizes: [] })
  itemDialog.value = true
}

async function saveItem() {
  savingItem.value = true
  itemErrors.value = {}
  const payload = { name: itemForm.name, price: Number(itemForm.price || 0).toFixed(2), sizes: itemForm.sizes }
  try {
    if (editingItem.value) await uniformsApi.updateItem(editingItem.value.id, payload)
    else await uniformsApi.createItem(payload)
    toast.add({ severity: 'success', summary: 'Item salvo', life: 3000 })
    itemDialog.value = false
    loadItems()
  } catch (e) {
    itemErrors.value = e.fields || {}
    if (!Object.keys(itemErrors.value).length) notify(e)
  } finally {
    savingItem.value = false
  }
}

async function toggleItem(item) {
  try {
    await uniformsApi.updateItem(item.id, { active: !item.active })
    loadItems()
  } catch (e) {
    notify(e)
  }
}

onMounted(refresh)
</script>

<style scoped>
.panels { padding: 1rem 0 0 !important; background: transparent !important; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: .2rem; }
.kpi__label { font-size: .8rem; color: var(--iba-text-muted); font-weight: 500; display: flex; gap: .35rem; align-items: center; }
.kpi__value { font-family: var(--iba-font-title); font-weight: 800; font-size: 1.5rem; }
.kpi__hint { font-size: .75rem; color: var(--iba-text-muted); }
.ok { color: var(--iba-success); }
.warn { color: var(--iba-gold-text); }
.filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; padding: 1rem; margin-bottom: 1rem; }
.filters__search { flex: 1 1 220px; }
.table-card { padding: 0; overflow: hidden; }
.orders-table :deep(tr) { cursor: pointer; }
.orders-table :deep(td.num), .orders-table :deep(th.num) { text-align: right; }
.small { font-size: .8rem; }
.due { color: var(--iba-danger); }
.delivered { color: var(--iba-success); font-weight: 500; }
.actions { display: flex; justify-content: flex-end; gap: .25rem; align-items: center; }
.empty { text-align: center; padding: 2rem; color: var(--iba-text-muted); }
.empty i { font-size: 2rem; color: var(--iba-gold); }
.items-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.items-head p { margin: 0; }
.items { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.item { border-top: 4px solid var(--iba-gold); display: flex; flex-direction: column; }
.item--inactive { opacity: .6; border-top-color: var(--iba-border); }
.item header { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.item h2 { font-size: .95rem; }
.item p { margin: .25rem 0; }
.item__price { font-family: var(--iba-font-title); font-weight: 800; font-size: 1.5rem; color: var(--iba-gold-text); margin: .5rem 0 !important; }
.item footer { margin-top: auto; padding-top: .5rem; display: flex; }
.iform { display: grid; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
@media (max-width: 760px) { .orders-table :deep(.col-md) { display: none; } }
</style>
