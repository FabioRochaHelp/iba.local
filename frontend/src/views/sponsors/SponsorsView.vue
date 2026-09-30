<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Patrocínios</h1>
        <p>Entradas de patrocinadores — gerais ou destinadas a um atleta.</p>
      </div>
      <div class="header-actions">
        <Select v-model="year" :options="years" aria-label="Ano" />
        <Button label="Nova entrada" icon="pi pi-plus" @click="openEntry" />
      </div>
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="entradas">Entradas</Tab>
        <Tab value="patrocinadores">Patrocinadores</Tab>
      </TabList>
      <TabPanels class="panels">
        <!-- ENTRADAS -->
        <TabPanel value="entradas">
          <div class="top">
            <article class="iba-card kpi">
              <span class="kpi__label"><i class="pi pi-star ok" aria-hidden="true"></i> Recebido em {{ year }}</span>
              <strong class="kpi__value">{{ formatMoney(summary.total) }}</strong>
              <span class="kpi__hint">{{ total }} lançamento(s)</span>
            </article>
            <article class="iba-card ranking">
              <h2>Por patrocinador</h2>
              <p v-if="!summary.by_sponsor?.length" class="iba-muted small">Sem entradas no ano.</p>
              <ul>
                <li v-for="s in summary.by_sponsor" :key="s.sponsor">
                  <div class="ranking__row"><span>{{ s.sponsor }}</span><strong>{{ formatMoney(s.total) }}</strong></div>
                  <div class="meter" role="meter" :aria-valuenow="Number(s.total)" aria-valuemin="0" :aria-valuemax="Number(summary.total)" :aria-label="s.sponsor">
                    <span :style="{ width: `${share(s.total)}%` }"></span>
                  </div>
                </li>
              </ul>
            </article>
          </div>

          <section class="iba-card table-card">
            <DataTable :value="entries" :loading="loading" data-key="id" row-hover>
              <template #empty><div class="empty"><i class="pi pi-star" aria-hidden="true"></i><p>Nenhuma entrada em {{ year }}.</p></div></template>
              <Column header="Data"><template #body="{ data }">{{ formatDate(data.received_at) }}</template></Column>
              <Column header="Origem">
                <template #body="{ data }">
                  <strong>{{ data.sponsor_name || 'Sem patrocinador vinculado' }}</strong>
                  <div v-if="data.description" class="small iba-muted">{{ data.description }}</div>
                </template>
              </Column>
              <Column header="Destinado a" class="col-md">
                <template #body="{ data }">
                  <RouterLink v-if="data.athlete_id" :to="{ name: 'athlete-show', params: { id: data.athlete_id } }">{{ data.athlete_name }}</RouterLink>
                  <span v-else class="iba-muted">Escolinha (geral)</span>
                </template>
              </Column>
              <Column header="Forma" class="col-md"><template #body="{ data }">{{ methodLabel(data.method) }}</template></Column>
              <Column header="Valor" class="num"><template #body="{ data }"><strong>{{ formatMoney(data.amount) }}</strong></template></Column>
              <Column header="" class="col-actions">
                <template #body="{ data }">
                  <Button
                    v-tooltip.left="'Excluir lançamento'" icon="pi pi-trash" text rounded severity="danger"
                    :aria-label="`Excluir entrada de ${formatMoney(data.amount)}`" @click="askDelete(data)"
                  />
                </template>
              </Column>
            </DataTable>
          </section>
        </TabPanel>

        <!-- PATROCINADORES -->
        <TabPanel value="patrocinadores">
          <div class="items-head">
            <p class="iba-muted">Patrocinadores com entradas não podem ser excluídos — desative-os.</p>
            <Button label="Novo patrocinador" icon="pi pi-plus" outlined @click="openSponsor()" />
          </div>
          <section class="iba-card table-card">
            <DataTable :value="sponsors" data-key="id">
              <template #empty><p class="empty">Nenhum patrocinador cadastrado.</p></template>
              <Column header="Patrocinador">
                <template #body="{ data }">
                  <strong :class="{ 'iba-muted': !data.active }">{{ data.name }}</strong>
                  <Tag v-if="!data.active" value="Inativo" severity="secondary" rounded class="ml" />
                  <div v-if="data.contact" class="small iba-muted">{{ data.contact }}</div>
                </template>
              </Column>
              <Column header="Contato" class="col-md">
                <template #body="{ data }">
                  <PhoneLink v-if="data.phone" :phone="data.phone" class="small" />
                  <div v-if="data.email" class="small">{{ data.email }}</div>
                </template>
              </Column>
              <Column header="Total aportado" class="num">
                <template #body="{ data }">
                  <strong>{{ formatMoney(data.total_amount) }}</strong>
                  <div class="small iba-muted">{{ data.entries }} entrada(s)</div>
                </template>
              </Column>
              <Column header="" class="col-actions">
                <template #body="{ data }">
                  <div class="actions">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="`Editar ${data.name}`" @click="openSponsor(data)" />
                    <Button
                      :icon="data.active ? 'pi pi-eye-slash' : 'pi pi-eye'" text rounded severity="secondary"
                      :aria-label="data.active ? `Desativar ${data.name}` : `Ativar ${data.name}`" @click="toggleSponsor(data)"
                    />
                    <Button v-if="!data.entries" icon="pi pi-trash" text rounded severity="danger" :aria-label="`Excluir ${data.name}`" @click="deleteSponsor(data)" />
                  </div>
                </template>
              </Column>
            </DataTable>
          </section>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Nova entrada -->
    <Dialog v-model:visible="entryDialog" header="Nova entrada de patrocínio" modal :style="{ width: '500px' }" :breakpoints="{ '540px': '95vw' }">
      <form id="entry-form" class="eform" novalidate @submit.prevent="saveEntry">
        <div class="field">
          <label for="en-sponsor">Patrocinador</label>
          <Select
            v-model="entry.sponsor_id" input-id="en-sponsor" :options="activeSponsors" option-label="name" option-value="id"
            placeholder="Selecione (opcional)" show-clear filter :invalid="!!entryErrors.sponsor_id" fluid
          />
        </div>
        <div class="field">
          <label for="en-athlete">Destinado a um atleta?</label>
          <AthletePicker v-model="entryAthlete" input-id="en-athlete" :invalid="!!entryErrors.athlete_id" />
          <small class="iba-muted">Deixe em branco para patrocínio geral da escolinha.</small>
        </div>
        <div class="row">
          <div class="field">
            <label for="en-amount">Valor *</label>
            <InputNumber
              v-model="entry.amount" input-id="en-amount" mode="currency" currency="BRL" locale="pt-BR" :min="0.01"
              :invalid="!!entryErrors.amount" fluid
            />
            <small v-if="entryErrors.amount" class="field__error">{{ entryErrors.amount }}</small>
          </div>
          <div class="field">
            <label for="en-date">Data *</label>
            <InputText id="en-date" v-model="entry.received_at" type="date" :max="today" :invalid="!!entryErrors.received_at" fluid />
            <small v-if="entryErrors.received_at" class="field__error">{{ entryErrors.received_at }}</small>
          </div>
        </div>
        <div class="field">
          <label for="en-method">Forma</label>
          <Select v-model="entry.method" input-id="en-method" :options="sponsorshipMethods" option-label="label" option-value="value" fluid />
        </div>
        <div class="field">
          <label for="en-desc">Descrição {{ entry.sponsor_id ? '' : '*' }}</label>
          <InputText id="en-desc" v-model="entry.description" maxlength="255" placeholder="Ex.: rifa, doação, evento" :invalid="!!entryErrors.description" fluid />
          <small v-if="entryErrors.description" class="field__error">{{ entryErrors.description }}</small>
        </div>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="entryDialog = false" />
        <Button type="submit" form="entry-form" label="Registrar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>

    <!-- Patrocinador -->
    <Dialog v-model:visible="sponsorDialog" :header="editingSponsor ? 'Editar patrocinador' : 'Novo patrocinador'" modal :style="{ width: '500px' }" :breakpoints="{ '540px': '95vw' }">
      <form id="sponsor-form" class="eform" novalidate @submit.prevent="saveSponsor">
        <div class="field">
          <label for="sp-name">Nome *</label>
          <InputText id="sp-name" v-model="sponsorForm.name" maxlength="120" :invalid="!!sponsorErrors.name" fluid />
          <small v-if="sponsorErrors.name" class="field__error">{{ sponsorErrors.name }}</small>
        </div>
        <div class="row">
          <div class="field">
            <label for="sp-doc">CPF/CNPJ</label>
            <InputText id="sp-doc" v-model="sponsorForm.document" maxlength="20" :invalid="!!sponsorErrors.document" fluid />
            <small v-if="sponsorErrors.document" class="field__error">{{ sponsorErrors.document }}</small>
          </div>
          <div class="field">
            <label for="sp-contact">Pessoa de contato</label>
            <InputText id="sp-contact" v-model="sponsorForm.contact" maxlength="120" fluid />
          </div>
        </div>
        <div class="row">
          <div class="field">
            <label for="sp-phone">Telefone</label>
            <InputText id="sp-phone" v-model="sponsorForm.phone" maxlength="30" inputmode="tel" :invalid="!!sponsorErrors.phone" fluid />
            <small v-if="sponsorErrors.phone" class="field__error">{{ sponsorErrors.phone }}</small>
          </div>
          <div class="field">
            <label for="sp-email">E-mail</label>
            <InputText id="sp-email" v-model="sponsorForm.email" type="email" maxlength="190" :invalid="!!sponsorErrors.email" fluid />
          </div>
        </div>
        <div class="field">
          <label for="sp-notes">Observações</label>
          <Textarea id="sp-notes" v-model="sponsorForm.notes" rows="2" maxlength="2000" auto-resize fluid />
        </div>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="sponsorDialog = false" />
        <Button type="submit" form="sponsor-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>

    <!-- Exclusão com motivo -->
    <Dialog v-model:visible="deleteDialog" header="Excluir lançamento" modal :style="{ width: '420px' }" :breakpoints="{ '460px': '95vw' }">
      <p class="small">O valor e o motivo ficam registrados na auditoria.</p>
      <form id="del-form" @submit.prevent="confirmDelete">
        <label for="del-reason" class="lbl">Motivo *</label>
        <Textarea id="del-reason" v-model="deleteReason" rows="2" maxlength="255" auto-resize autofocus fluid :invalid="!!deleteError" />
        <small v-if="deleteError" class="field__error">{{ deleteError }}</small>
      </form>
      <template #footer>
        <Button label="Voltar" text severity="secondary" @click="deleteDialog = false" />
        <Button type="submit" form="del-form" label="Excluir" severity="danger" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import AthletePicker from '@/components/AthletePicker.vue'
import PhoneLink from '@/components/PhoneLink.vue'
import { sponsorsApi } from '@/api/sponsors'
import { useApiError } from '@/composables/useApiError'
import { formatDate, formatMoney, sponsorshipMethods, todayIso } from '@/utils/format'

const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()
const today = todayIso()

const tab = ref('entradas')
const thisYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => thisYear - i)
const year = ref(thisYear)

const entries = ref([])
const total = ref(0)
const summary = ref({ total: '0.00', by_sponsor: [] })
const sponsors = ref([])
const loading = ref(false)
const saving = ref(false)

const entryDialog = ref(false)
const entryAthlete = ref(null)
const entryErrors = ref({})
const entry = reactive({ sponsor_id: null, amount: 0, received_at: today, method: 'pix', description: '' })

const sponsorDialog = ref(false)
const editingSponsor = ref(null)
const sponsorErrors = ref({})
const sponsorForm = reactive({ name: '', document: '', contact: '', phone: '', email: '', notes: '' })

const deleteDialog = ref(false)
const deleteTarget = ref(null)
const deleteReason = ref('')
const deleteError = ref('')

const activeSponsors = computed(() => sponsors.value.filter((s) => s.active))
const methodLabel = (v) => sponsorshipMethods.find((m) => m.value === v)?.label || v
const share = (v) => (Number(summary.value.total) > 0 ? Math.max(2, (Number(v) / Number(summary.value.total)) * 100) : 0)

async function loadEntries() {
  loading.value = true
  try {
    const [res, sum] = await Promise.all([
      sponsorsApi.entries({ from: `${year.value}-01-01`, to: `${year.value}-12-31`, per_page: 100 }),
      sponsorsApi.summary(year.value)
    ])
    entries.value = res.data
    total.value = res.meta.total
    summary.value = sum
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

async function loadSponsors() {
  try {
    sponsors.value = await sponsorsApi.list()
  } catch (e) {
    notify(e)
  }
}

watch(year, loadEntries)

function openEntry() {
  entryErrors.value = {}
  entryAthlete.value = null
  Object.assign(entry, { sponsor_id: null, amount: 0, received_at: todayIso(), method: 'pix', description: '' })
  entryDialog.value = true
}

async function saveEntry() {
  saving.value = true
  entryErrors.value = {}
  try {
    await sponsorsApi.addEntry({
      sponsor_id: entry.sponsor_id,
      athlete_id: entryAthlete.value?.id || null,
      amount: Number(entry.amount || 0).toFixed(2),
      received_at: entry.received_at,
      method: entry.method,
      description: entry.description || null
    })
    toast.add({ severity: 'success', summary: 'Entrada registrada', detail: formatMoney(entry.amount), life: 3000 })
    entryDialog.value = false
    loadEntries()
    loadSponsors()
  } catch (e) {
    entryErrors.value = e.fields || {}
    if (!Object.keys(entryErrors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

function askDelete(e) {
  deleteTarget.value = e
  deleteReason.value = ''
  deleteError.value = ''
  deleteDialog.value = true
}

async function confirmDelete() {
  if (deleteReason.value.trim().length < 3) {
    deleteError.value = 'Descreva o motivo.'
    return
  }
  saving.value = true
  try {
    await sponsorsApi.removeEntry(deleteTarget.value.id, deleteReason.value.trim())
    toast.add({ severity: 'success', summary: 'Lançamento excluído', life: 3000 })
    deleteDialog.value = false
    loadEntries()
    loadSponsors()
  } catch (e) {
    notify(e)
  } finally {
    saving.value = false
  }
}

function openSponsor(s = null) {
  editingSponsor.value = s
  sponsorErrors.value = {}
  Object.assign(sponsorForm, s
    ? { name: s.name, document: s.document || '', contact: s.contact || '', phone: s.phone || '', email: s.email || '', notes: s.notes || '' }
    : { name: '', document: '', contact: '', phone: '', email: '', notes: '' })
  sponsorDialog.value = true
}

async function saveSponsor() {
  saving.value = true
  sponsorErrors.value = {}
  const payload = Object.fromEntries(Object.entries(sponsorForm).map(([k, v]) => [k, v === '' ? null : v]))
  try {
    if (editingSponsor.value) await sponsorsApi.update(editingSponsor.value.id, payload)
    else await sponsorsApi.create(payload)
    toast.add({ severity: 'success', summary: 'Patrocinador salvo', life: 3000 })
    sponsorDialog.value = false
    loadSponsors()
  } catch (e) {
    sponsorErrors.value = e.fields || {}
    if (!Object.keys(sponsorErrors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

async function toggleSponsor(s) {
  try {
    await sponsorsApi.update(s.id, { name: s.name, active: !s.active })
    loadSponsors()
  } catch (e) {
    notify(e)
  }
}

function deleteSponsor(s) {
  confirm.require({
    header: 'Excluir patrocinador',
    message: `Excluir ${s.name}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Excluir', severity: 'danger' },
    accept: async () => {
      try {
        await sponsorsApi.remove(s.id)
        loadSponsors()
      } catch (e) {
        notify(e)
      }
    }
  })
}

onMounted(() => {
  loadEntries()
  loadSponsors()
})
</script>

<style scoped>
.header-actions { display: flex; gap: .75rem; align-items: center; }
.panels { padding: 1rem 0 0 !important; background: transparent !important; }
.top { display: grid; grid-template-columns: minmax(220px, 1fr) 2fr; gap: 1rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: .2rem; justify-content: center; }
.kpi__label { font-size: .8rem; color: var(--iba-text-muted); font-weight: 500; display: flex; gap: .35rem; align-items: center; }
.kpi__value { font-family: var(--iba-font-title); font-weight: 800; font-size: 1.8rem; }
.kpi__hint { font-size: .75rem; color: var(--iba-text-muted); }
.ok { color: var(--iba-gold-text); }
.ranking h2 { font-size: .9rem; margin-bottom: .75rem; }
.ranking ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
.ranking__row { display: flex; justify-content: space-between; gap: 1rem; font-size: .88rem; }
/* medidor: trilho e preenchimento na mesma rampa (dourado) */
.meter { height: 6px; border-radius: 3px; background: color-mix(in srgb, var(--c-received) 18%, transparent); margin-top: .25rem; overflow: hidden; }
.meter span { display: block; height: 100%; border-radius: 3px; background: var(--c-received); }
.table-card { padding: 0; overflow: hidden; }
.table-card :deep(td.num), .table-card :deep(th.num) { text-align: right; }
.table-card a { color: var(--iba-text); }
.small { font-size: .8rem; }
.ml { margin-left: .4rem; }
.actions { display: flex; justify-content: flex-end; }
.empty { text-align: center; padding: 2rem; color: var(--iba-text-muted); }
.empty i { font-size: 2rem; color: var(--iba-gold); }
.items-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.items-head p { margin: 0; }
.eform { display: grid; gap: 1rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label, .lbl { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
@media (max-width: 760px) {
  .top { grid-template-columns: 1fr; }
  .table-card :deep(.col-md) { display: none; }
  .row { grid-template-columns: 1fr; }
}
</style>
