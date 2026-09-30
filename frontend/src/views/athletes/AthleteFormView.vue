<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>{{ isEdit ? 'Editar atleta' : 'Novo atleta' }}</h1>
        <p v-if="isEdit && original">{{ original.name }}</p>
      </div>
      <RouterLink :to="isEdit ? { name: 'athlete-show', params: { id } } : { name: 'athletes' }">
        <Button label="Voltar" icon="pi pi-arrow-left" text severity="secondary" />
      </RouterLink>
    </div>

    <div v-if="loadingData" class="iba-card"><ProgressSpinner style="width: 40px; height: 40px" /></div>

    <form v-else class="form" novalidate @submit.prevent="submit">
      <Message v-if="Object.keys(errors).length" severity="error" :closable="false">
        Corrija os campos destacados.
      </Message>

      <!-- Dados do atleta -->
      <section class="iba-card iba-card--accent">
        <h2>Dados do atleta</h2>
        <div class="grid">
          <div class="field span-2">
            <label for="name">Nome completo *</label>
            <InputText id="name" v-model="form.name" maxlength="120" :invalid="!!errors.name" fluid />
            <small v-if="errors.name" class="field__error">{{ errors.name }}</small>
          </div>
          <div class="field">
            <label for="birth">Data de nascimento</label>
            <InputText id="birth" v-model="form.birth_date" type="date" :max="today" :invalid="!!errors.birth_date" fluid />
            <small v-if="errors.birth_date" class="field__error">{{ errors.birth_date }}</small>
            <small v-else-if="category" class="iba-muted">{{ category }}</small>
          </div>
          <div class="field">
            <label for="enroll">Data de matrícula</label>
            <InputText id="enroll" v-model="form.enrollment_date" type="date" :invalid="!!errors.enrollment_date" fluid />
          </div>
          <div class="field">
            <label for="status">Status</label>
            <Select v-model="form.status" input-id="status" :options="statusOptions" option-label="label" option-value="value" fluid />
          </div>
          <div class="field span-2">
            <label for="positions">Posições</label>
            <MultiSelect
              v-model="form.position_ids" input-id="positions" :options="positions" option-label="name"
              option-value="id" :selection-limit="5" display="chip" placeholder="Selecione"
              :invalid="!!errors.position_ids" fluid
            />
            <small v-if="errors.position_ids" class="field__error">{{ errors.position_ids }}</small>
          </div>
        </div>
      </section>

      <!-- Responsável -->
      <section class="iba-card">
        <div class="section-head">
          <h2>Responsável</h2>
          <SelectButton
            v-model="guardianMode" :options="guardianModes" option-label="label" option-value="value"
            :allow-empty="false" aria-label="Responsável existente ou novo"
          />
        </div>

        <div v-if="guardianMode === 'existing'" class="field">
          <label for="guardian">Buscar responsável (nome, telefone ou nome do atleta irmão)</label>
          <AutoComplete
            v-model="selectedGuardian" input-id="guardian" :suggestions="guardianSuggestions"
            option-label="name" :min-length="2" :delay="300" force-selection dropdown
            :invalid="!!errors.guardian_id" fluid @complete="searchGuardians"
          >
            <template #option="{ option }">
              <div class="guardian-option">
                <strong>{{ option.name }}</strong>
                <small>{{ option.phones.map((p) => formatPhone(p.phone)).join(' · ') || 'sem telefone' }}</small>
                <small v-if="option.athletes.length" class="iba-muted">Atletas: {{ option.athletes.map((a) => a.name).join(', ') }}</small>
              </div>
            </template>
          </AutoComplete>
          <small v-if="errors.guardian_id" class="field__error">{{ errors.guardian_id }}</small>
        </div>

        <div v-else class="grid">
          <div class="field span-2">
            <label for="g-name">Nome do responsável *</label>
            <InputText id="g-name" v-model="newGuardian.name" maxlength="120" :invalid="!!errors['guardian.name']" fluid />
            <small v-if="errors['guardian.name']" class="field__error">{{ errors['guardian.name'] }}</small>
          </div>
          <div class="field">
            <label for="g-cpf">CPF</label>
            <InputMask
              id="g-cpf" v-model="newGuardian.cpf" mask="999.999.999-99" :auto-clear="false"
              :invalid="!!errors['guardian.cpf']" fluid
            />
            <small v-if="errors['guardian.cpf']" class="field__error">{{ errors['guardian.cpf'] }}</small>
          </div>
          <div class="field">
            <label for="g-email">E-mail</label>
            <InputText id="g-email" v-model="newGuardian.email" type="email" maxlength="190" :invalid="!!errors['guardian.email']" fluid />
            <small v-if="errors['guardian.email']" class="field__error">{{ errors['guardian.email'] }}</small>
          </div>
          <div class="field span-2">
            <label>Telefones *</label>
            <PhonesInput v-model="newGuardian.phones" :errors="errors" prefix="guardian.phones" />
          </div>
        </div>
      </section>

      <!-- Plano -->
      <section class="iba-card">
        <h2>Plano de treinamento</h2>
        <div class="grid">
          <div class="field">
            <label for="plan">Plano</label>
            <Select
              v-model="form.plan.plan_id" input-id="plan" :options="activePlans" option-value="id"
              :option-label="(p) => `${p.name} — ${formatMoney(p.monthly_fee)}`" placeholder="Selecione"
              show-clear :invalid="!!errors['plan.plan_id']" fluid
            />
            <small v-if="errors['plan.plan_id']" class="field__error">{{ errors['plan.plan_id'] }}</small>
          </div>
          <div class="field">
            <label for="dtype">Desconto</label>
            <Select
              v-model="form.plan.discount_type" input-id="dtype" :options="discountTypes" option-label="label"
              option-value="value" :disabled="!form.plan.plan_id" fluid
            />
          </div>
          <div v-if="['percentual', 'valor'].includes(form.plan.discount_type)" class="field">
            <label for="dvalue">{{ form.plan.discount_type === 'percentual' ? 'Percentual' : 'Valor do desconto' }}</label>
            <InputNumber
              v-model="form.plan.discount_value" input-id="dvalue" :min="0"
              :max="form.plan.discount_type === 'percentual' ? 100 : 9999" :min-fraction-digits="0"
              :max-fraction-digits="2" :suffix="form.plan.discount_type === 'percentual' ? ' %' : ''"
              :mode="form.plan.discount_type === 'valor' ? 'currency' : 'decimal'" currency="BRL" locale="pt-BR"
              :invalid="!!errors['plan.discount_value']" fluid
            />
            <small v-if="errors['plan.discount_value']" class="field__error">{{ errors['plan.discount_value'] }}</small>
          </div>
          <div v-if="selectedPlan" class="fee-preview">
            <span class="iba-muted">Mensalidade</span>
            <strong>{{ formatMoney(monthly) }}</strong>
          </div>
        </div>
        <small v-if="isEdit && planChanged" class="iba-muted">
          A mudança de plano vale a partir de hoje; o plano anterior fica no histórico.
        </small>
      </section>

      <!-- Saúde e observações -->
      <section class="iba-card">
        <h2>Saúde e observações</h2>
        <label class="toggle">
          <ToggleSwitch v-model="form.has_health_condition" input-id="health" />
          <span>Possui alguma condição de saúde</span>
        </label>
        <div v-if="form.has_health_condition" class="field">
          <label for="hc">Descreva a condição (alergias, asma, medicação, restrições...) *</label>
          <Textarea
            id="hc" v-model="form.health_condition" rows="3" maxlength="1000" auto-resize
            :invalid="!!errors.health_condition" fluid
          />
          <small v-if="errors.health_condition" class="field__error">{{ errors.health_condition }}</small>
        </div>
        <div class="field">
          <label for="notes">Observações</label>
          <Textarea id="notes" v-model="form.notes" rows="3" maxlength="2000" auto-resize fluid />
        </div>
      </section>

      <div class="form__actions">
        <RouterLink :to="isEdit ? { name: 'athlete-show', params: { id } } : { name: 'athletes' }">
          <Button type="button" label="Cancelar" severity="secondary" text />
        </RouterLink>
        <Button type="submit" :label="isEdit ? 'Salvar alterações' : 'Cadastrar atleta'" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputMask from 'primevue/inputmask'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import SelectButton from 'primevue/selectbutton'
import AutoComplete from 'primevue/autocomplete'
import ToggleSwitch from 'primevue/toggleswitch'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import PhonesInput from '@/components/PhonesInput.vue'
import { athletesApi } from '@/api/athletes'
import { guardiansApi } from '@/api/guardians'
import { catalogApi } from '@/api/catalog'
import { useApiError } from '@/composables/useApiError'
import { discountTypes, finalFee, formatMoney, formatPhone, todayIso } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { notify } = useApiError()

const id = computed(() => route.params.id)
const isEdit = computed(() => Boolean(id.value))
const today = todayIso()

const loadingData = ref(true)
const saving = ref(false)
const errors = ref({})
const original = ref(null)
const positions = ref([])
const plans = ref([])

const form = reactive({
  name: '',
  birth_date: '',
  enrollment_date: today,
  status: 'ativo',
  position_ids: [],
  has_health_condition: false,
  health_condition: '',
  notes: '',
  plan: { plan_id: null, discount_type: 'nenhum', discount_value: 0 }
})

const guardianModes = [
  { value: 'existing', label: 'Já cadastrado' },
  { value: 'new', label: 'Novo' }
]
const guardianMode = ref('new')
const selectedGuardian = ref(null)
const guardianSuggestions = ref([])
const newGuardian = reactive({ name: '', cpf: '', email: '', phones: [{ phone: '', label: '', is_whatsapp: true }] })

const statusOptions = [
  { value: 'ativo', label: 'Ativo' },
  { value: 'trancado', label: 'Trancado' },
  { value: 'inativo', label: 'Inativo' }
]

const activePlans = computed(() => plans.value.filter((p) => p.active || p.id === form.plan.plan_id))
const selectedPlan = computed(() => plans.value.find((p) => p.id === form.plan.plan_id))
const monthly = computed(() =>
  selectedPlan.value ? finalFee(selectedPlan.value.monthly_fee, form.plan.discount_type, form.plan.discount_value) : 0
)
const planChanged = computed(() => {
  const cur = original.value?.plan
  return cur && (cur.id !== form.plan.plan_id || cur.discount_type !== form.plan.discount_type ||
    Number(cur.discount_value) !== Number(form.plan.discount_value || 0))
})
const category = computed(() => {
  if (!form.birth_date) return ''
  const sub = new Date().getFullYear() - Number(form.birth_date.slice(0, 4)) + 1
  return sub > 0 ? `Categoria Sub-${sub}` : ''
})

async function searchGuardians(e) {
  try {
    const res = await guardiansApi.list({ search: e.query, per_page: 10 })
    guardianSuggestions.value = res.data
  } catch (err) {
    notify(err)
  }
}

function buildPayload() {
  const payload = {
    name: form.name,
    birth_date: form.birth_date || null,
    enrollment_date: form.enrollment_date || undefined,
    status: form.status,
    position_ids: form.position_ids,
    has_health_condition: form.has_health_condition,
    health_condition: form.has_health_condition ? form.health_condition : null,
    notes: form.notes || null
  }

  if (guardianMode.value === 'existing') {
    payload.guardian_id = selectedGuardian.value?.id || null
  } else {
    payload.guardian = {
      name: newGuardian.name,
      cpf: newGuardian.cpf || null,
      email: newGuardian.email || null,
      phones: newGuardian.phones.filter((p) => p.phone?.trim())
    }
  }

  if (form.plan.plan_id) {
    payload.plan = {
      plan_id: form.plan.plan_id,
      discount_type: form.plan.discount_type,
      discount_value: ['percentual', 'valor'].includes(form.plan.discount_type) ? String(form.plan.discount_value ?? 0) : null
    }
  }
  return payload
}

function validateLocally() {
  const e = {}
  if (!form.name || form.name.trim().length < 3) e.name = 'Informe o nome completo.'
  if (form.has_health_condition && !form.health_condition?.trim()) e.health_condition = 'Descreva a condição de saúde.'
  if (guardianMode.value === 'existing' && !selectedGuardian.value?.id) e.guardian_id = 'Selecione o responsável.'
  if (guardianMode.value === 'new') {
    if (!newGuardian.name || newGuardian.name.trim().length < 3) e['guardian.name'] = 'Informe o nome do responsável.'
    if (!newGuardian.phones.some((p) => p.phone?.trim())) e['guardian.phones'] = 'Informe ao menos um telefone.'
  }
  errors.value = e
  return Object.keys(e).length === 0
}

async function submit() {
  if (!validateLocally()) return
  saving.value = true
  try {
    const athlete = isEdit.value
      ? await athletesApi.update(id.value, buildPayload())
      : await athletesApi.create(buildPayload())
    toast.add({ severity: 'success', summary: isEdit.value ? 'Atleta atualizado' : 'Atleta cadastrado', detail: athlete.name, life: 3000 })
    router.push({ name: 'athlete-show', params: { id: athlete.id } })
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [pos, pl] = await Promise.all([catalogApi.positions(), catalogApi.plans()])
    positions.value = pos
    plans.value = pl

    if (isEdit.value) {
      const a = await athletesApi.get(id.value)
      original.value = a
      Object.assign(form, {
        name: a.name,
        birth_date: a.birth_date || '',
        enrollment_date: a.enrollment_date,
        status: a.status,
        position_ids: a.positions.map((p) => p.id),
        has_health_condition: a.has_health_condition,
        health_condition: a.health_condition || '',
        notes: a.notes || '',
        plan: a.plan
          ? { plan_id: a.plan.id, discount_type: a.plan.discount_type, discount_value: Number(a.plan.discount_value) }
          : { plan_id: null, discount_type: 'nenhum', discount_value: 0 }
      })
      guardianMode.value = 'existing'
      selectedGuardian.value = { id: a.guardian.id, name: a.guardian.name, phones: a.guardian.phones, athletes: [] }
    }
  } catch (e) {
    notify(e)
    if (e.response?.status === 404) router.replace({ name: 'athletes' })
  } finally {
    loadingData.value = false
  }
})
</script>

<style scoped>
.form { display: grid; gap: 1rem; max-width: 960px; }
.form h2 { margin-bottom: 1rem; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: start; }
.span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
.section-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.section-head h2 { margin: 0 !important; }
.toggle { display: flex; align-items: center; gap: .6rem; margin-bottom: 1rem; font-weight: 500; }
.fee-preview {
  display: flex; flex-direction: column; justify-content: center; padding: .5rem 1rem; border-radius: 8px;
  background: color-mix(in srgb, var(--iba-gold) 12%, transparent); border: 1px dashed var(--iba-gold);
}
.fee-preview strong { font-family: var(--iba-font-title); font-size: 1.3rem; color: var(--iba-gold-text); }
.guardian-option { display: flex; flex-direction: column; line-height: 1.3; }
.form__actions { display: flex; justify-content: flex-end; gap: .5rem; padding-bottom: 2rem; }
@media (max-width: 600px) { .span-2 { grid-column: auto; } }
</style>
