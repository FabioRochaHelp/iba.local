<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Responsáveis</h1>
        <p>{{ total }} responsável(is)</p>
      </div>
      <Button label="Novo responsável" icon="pi pi-plus" @click="openForm()" />
    </div>

    <section class="iba-card filters">
      <IconField class="filters__search">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar por nome, telefone ou atleta" maxlength="100" aria-label="Buscar" fluid />
      </IconField>
    </section>

    <section class="iba-card table-card">
      <DataTable
        :value="rows" lazy :loading="loading" :total-records="total" :rows="perPage" :first="first" paginator
        :rows-per-page-options="[10, 20, 50]" data-key="id"
        current-page-report-template="{first}–{last} de {totalRecords}"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        @page="onPage"
      >
        <template #empty><p class="empty">Nenhum responsável encontrado.</p></template>
        <Column field="name" header="Nome">
          <template #body="{ data }">
            <strong>{{ data.name }}</strong>
            <div v-if="data.cpf" class="small iba-muted">CPF {{ data.cpf }}</div>
          </template>
        </Column>
        <Column header="Telefones">
          <template #body="{ data }">
            <div v-for="p in data.phones" :key="p.phone" class="small">
              <PhoneLink :phone="p.phone" :whatsapp="p.is_whatsapp" />
              <span v-if="p.label" class="iba-muted"> · {{ p.label }}</span>
            </div>
            <span v-if="!data.phones.length" class="missing"><i class="pi pi-exclamation-circle" aria-hidden="true"></i> sem telefone</span>
          </template>
        </Column>
        <Column header="Atletas" class="col-md">
          <template #body="{ data }">
            <div class="athletes">
              <RouterLink v-for="a in data.athletes" :key="a.id" :to="{ name: 'athlete-show', params: { id: a.id } }">
                <Chip :label="a.name" />
              </RouterLink>
            </div>
          </template>
        </Column>
        <Column header="" class="col-actions">
          <template #body="{ data }">
            <div class="actions">
              <Button
                v-tooltip.top="'Editar'" icon="pi pi-pencil" text rounded severity="secondary"
                :aria-label="`Editar ${data.name}`" @click="openForm(data.id)"
              />
              <Button
                v-tooltip.top="data.athletes_count ? 'Há atletas vinculados' : 'Excluir'" icon="pi pi-trash" text rounded
                severity="danger" :disabled="data.athletes_count > 0" :aria-label="`Excluir ${data.name}`"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="dialog" :header="editingId ? 'Editar responsável' : 'Novo responsável'" modal
      :style="{ width: '640px' }" :breakpoints="{ '680px': '95vw' }"
    >
      <form id="guardian-form" class="gform" novalidate @submit.prevent="save">
        <div class="field">
          <label for="gf-name">Nome *</label>
          <InputText id="gf-name" v-model="form.name" maxlength="120" :invalid="!!errors.name" fluid />
          <small v-if="errors.name" class="field__error">{{ errors.name }}</small>
        </div>
        <div class="row">
          <div class="field">
            <label for="gf-cpf">CPF</label>
            <InputMask id="gf-cpf" v-model="form.cpf" mask="999.999.999-99" :auto-clear="false" :invalid="!!errors.cpf" fluid />
            <small v-if="errors.cpf" class="field__error">{{ errors.cpf }}</small>
          </div>
          <div class="field">
            <label for="gf-email">E-mail</label>
            <InputText id="gf-email" v-model="form.email" type="email" maxlength="190" :invalid="!!errors.email" fluid />
            <small v-if="errors.email" class="field__error">{{ errors.email }}</small>
          </div>
        </div>
        <div class="field">
          <label>Telefones *</label>
          <PhonesInput v-model="form.phones" :errors="errors" prefix="phones" />
        </div>
        <div class="field">
          <label for="gf-notes">Observações</label>
          <Textarea id="gf-notes" v-model="form.notes" rows="2" maxlength="2000" auto-resize fluid />
        </div>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialog = false" />
        <Button type="submit" form="guardian-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputMask from 'primevue/inputmask'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Chip from 'primevue/chip'
import PhoneLink from '@/components/PhoneLink.vue'
import PhonesInput from '@/components/PhonesInput.vue'
import { guardiansApi } from '@/api/guardians'
import { useApiError } from '@/composables/useApiError'
import { debounce } from '@/utils/format'

const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()

const rows = ref([])
const total = ref(0)
const loading = ref(false)
const perPage = ref(20)
const first = ref(0)
const search = ref('')

const dialog = ref(false)
const saving = ref(false)
const editingId = ref(null)
const errors = ref({})
const emptyForm = () => ({ name: '', cpf: '', email: '', notes: '', phones: [{ phone: '', label: '', is_whatsapp: true }] })
const form = reactive(emptyForm())

async function load() {
  loading.value = true
  try {
    const res = await guardiansApi.list({
      page: Math.floor(first.value / perPage.value) + 1,
      per_page: perPage.value,
      search: search.value || undefined
    })
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

watch(search, debounce(() => {
  first.value = 0
  load()
}))

function onPage(e) {
  first.value = e.first
  perPage.value = e.rows
  load()
}

async function openForm(id = null) {
  errors.value = {}
  editingId.value = id
  Object.assign(form, emptyForm())
  if (id) {
    try {
      const g = await guardiansApi.get(id)
      Object.assign(form, {
        name: g.name,
        cpf: g.cpf || '',
        email: g.email || '',
        notes: g.notes || '',
        phones: g.phones.length ? g.phones.map((p) => ({ ...p, label: p.label || '' })) : emptyForm().phones
      })
    } catch (e) {
      notify(e)
      return
    }
  }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    cpf: form.cpf || null,
    email: form.email || null,
    notes: form.notes || null,
    phones: form.phones.filter((p) => p.phone?.trim())
  }
  try {
    if (editingId.value) await guardiansApi.update(editingId.value, payload)
    else await guardiansApi.create(payload)
    toast.add({ severity: 'success', summary: 'Responsável salvo', life: 3000 })
    dialog.value = false
    load()
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

function confirmDelete(g) {
  confirm.require({
    header: 'Excluir responsável',
    message: `Excluir ${g.name}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Excluir', severity: 'danger' },
    accept: async () => {
      try {
        await guardiansApi.remove(g.id)
        toast.add({ severity: 'success', summary: 'Responsável excluído', life: 3000 })
        load()
      } catch (e) {
        notify(e)
      }
    }
  })
}

onMounted(load)
</script>

<style scoped>
.filters { margin-bottom: 1rem; padding: 1rem; }
.filters__search { max-width: 480px; }
.table-card { padding: 0; overflow: hidden; }
.small { font-size: .85rem; }
.athletes { display: flex; flex-wrap: wrap; gap: .35rem; }
.athletes a { text-decoration: none; }
.actions { display: flex; justify-content: flex-end; }
.missing { color: var(--iba-danger); font-size: .8rem; }
.empty { text-align: center; color: var(--iba-text-muted); padding: 1.5rem; }
.gform { display: grid; gap: 1rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
@media (max-width: 640px) {
  .row { grid-template-columns: 1fr; }
  :deep(.col-md) { display: none; }
}
</style>
