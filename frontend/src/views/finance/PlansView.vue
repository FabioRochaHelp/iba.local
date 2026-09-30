<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Planos</h1>
        <p>Alterar o valor vale para as próximas mensalidades geradas; as já geradas não mudam.</p>
      </div>
      <Button label="Novo plano" icon="pi pi-plus" @click="openForm()" />
    </div>

    <div class="plans">
      <article v-for="p in plans" :key="p.id" :class="['iba-card plan', { 'plan--inactive': !p.active }]">
        <header>
          <h2>{{ p.name }}</h2>
          <Tag v-if="!p.active" value="Inativo" severity="secondary" rounded />
        </header>
        <p class="plan__fee">{{ formatMoney(p.monthly_fee) }}<span>/mês</span></p>
        <p class="iba-muted">{{ p.description || `${p.days_per_week} dia(s) por semana` }}</p>
        <p class="plan__count"><i class="pi pi-users" aria-hidden="true"></i> {{ p.athletes_count }} atleta(s) ativo(s)</p>
        <footer>
          <Button label="Editar" icon="pi pi-pencil" text @click="openForm(p)" />
          <Button
            :label="p.active ? 'Desativar' : 'Ativar'" :icon="p.active ? 'pi pi-eye-slash' : 'pi pi-eye'" text severity="secondary"
            @click="toggle(p)"
          />
        </footer>
      </article>
    </div>

    <Dialog v-model:visible="dialog" :header="editing ? 'Editar plano' : 'Novo plano'" modal :style="{ width: '440px' }" :breakpoints="{ '480px': '95vw' }">
      <form id="plan-form" class="pform" novalidate @submit.prevent="save">
        <div class="field">
          <label for="pl-name">Nome *</label>
          <InputText id="pl-name" v-model="form.name" maxlength="80" :invalid="!!errors.name" fluid />
          <small v-if="errors.name" class="field__error">{{ errors.name }}</small>
        </div>
        <div class="row">
          <div class="field">
            <label for="pl-days">Dias por semana *</label>
            <InputNumber v-model="form.days_per_week" input-id="pl-days" :min="1" :max="7" show-buttons :invalid="!!errors.days_per_week" fluid />
          </div>
          <div class="field">
            <label for="pl-fee">Mensalidade *</label>
            <InputNumber
              v-model="form.monthly_fee" input-id="pl-fee" mode="currency" currency="BRL" locale="pt-BR" :min="0"
              :invalid="!!errors.monthly_fee" fluid
            />
            <small v-if="errors.monthly_fee" class="field__error">{{ errors.monthly_fee }}</small>
          </div>
        </div>
        <div class="field">
          <label for="pl-desc">Descrição</label>
          <InputText id="pl-desc" v-model="form.description" maxlength="255" placeholder="Ex.: segunda e quarta" fluid />
        </div>
        <Message v-if="editing && Number(form.monthly_fee) !== Number(editing.monthly_fee) && editing.athletes_count" severity="info" :closable="false">
          {{ editing.athletes_count }} atleta(s) passarão a pagar {{ formatMoney(form.monthly_fee) }} a partir da próxima geração.
        </Message>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialog = false" />
        <Button type="submit" form="plan-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { plansApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatMoney } from '@/utils/format'

const toast = useToast()
const { notify } = useApiError()

const plans = ref([])
const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)
const errors = ref({})
const form = reactive({ name: '', days_per_week: 1, monthly_fee: 0, description: '' })

async function load() {
  try {
    plans.value = await plansApi.list()
  } catch (e) {
    notify(e)
  }
}

function openForm(plan = null) {
  editing.value = plan
  errors.value = {}
  Object.assign(form, plan
    ? { name: plan.name, days_per_week: plan.days_per_week, monthly_fee: Number(plan.monthly_fee), description: plan.description || '' }
    : { name: '', days_per_week: 1, monthly_fee: 0, description: '' })
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    days_per_week: form.days_per_week,
    monthly_fee: Number(form.monthly_fee || 0).toFixed(2),
    description: form.description || null
  }
  try {
    if (editing.value) await plansApi.update(editing.value.id, payload)
    else await plansApi.create(payload)
    toast.add({ severity: 'success', summary: 'Plano salvo', life: 3000 })
    dialog.value = false
    load()
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

async function toggle(plan) {
  try {
    await plansApi.update(plan.id, { active: !plan.active })
    load()
  } catch (e) {
    notify(e)
  }
}

onMounted(load)
</script>

<style scoped>
.plans { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
.plan { border-top: 4px solid var(--iba-gold); display: flex; flex-direction: column; }
.plan--inactive { opacity: .6; border-top-color: var(--iba-border); }
.plan header { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.plan__fee { font-family: var(--iba-font-title); font-weight: 800; font-size: 2rem; margin: .75rem 0 .25rem; color: var(--iba-gold-text); }
.plan__fee span { font-size: .9rem; color: var(--iba-text-muted); font-weight: 500; }
.plan p { margin: .25rem 0; }
.plan__count { font-size: .85rem; margin-top: .5rem !important; }
.plan footer { margin-top: auto; padding-top: .75rem; display: flex; gap: .25rem; }
.pform { display: grid; gap: 1rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
</style>
