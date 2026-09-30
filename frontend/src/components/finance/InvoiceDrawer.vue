<template>
  <Drawer v-model:visible="visible" position="right" class="invoice-drawer" :header="invoice ? `Mensalidade · ${formatMonth(invoice.reference_month)}` : 'Mensalidade'">
    <div v-if="loading" class="center"><ProgressSpinner style="width: 36px; height: 36px" /></div>

    <template v-else-if="invoice">
      <div class="head">
        <RouterLink :to="{ name: 'athlete-show', params: { id: invoice.athlete_id } }" class="athlete">{{ invoice.athlete_name }}</RouterLink>
        <InvoiceStatusTag :status="invoice.status" :overdue="invoice.overdue" />
      </div>
      <p class="iba-muted small">Responsável: {{ invoice.guardian_name }}<template v-if="invoice.plan_name"> · {{ invoice.plan_name }}</template></p>

      <dl class="values">
        <div><dt>Valor</dt><dd>{{ formatMoney(invoice.amount) }}</dd></div>
        <div><dt>Desconto</dt><dd>{{ formatMoney(invoice.discount) }}</dd></div>
        <div><dt>Total</dt><dd><strong>{{ formatMoney(invoice.final_amount) }}</strong></dd></div>
        <div><dt>Pago</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div>
        <div><dt>Saldo</dt><dd :class="{ due: Number(invoice.remaining) > 0 && invoice.status !== 'cancelada' }">{{ formatMoney(invoice.remaining) }}</dd></div>
        <div><dt>Vencimento</dt><dd>{{ formatDate(invoice.due_date) }}</dd></div>
      </dl>

      <Message v-if="invoice.status === 'cancelada'" severity="secondary" :closable="false">
        Cancelada em {{ formatDate(invoice.cancelled_at) }} — {{ invoice.cancel_reason }}
      </Message>
      <p v-if="invoice.notes" class="small"><strong>Obs.:</strong> {{ invoice.notes }}</p>

      <div v-if="invoice.status !== 'cancelada'" class="actions">
        <Button v-if="['aberta', 'parcial'].includes(invoice.status)" label="Receber" icon="pi pi-wallet" @click="payOpen = true" />
        <Button label="Ajustar" icon="pi pi-pencil" severity="secondary" outlined @click="openEdit" />
        <Button v-if="!hasActivePayments" label="Cancelar" icon="pi pi-ban" severity="danger" text @click="askReason('cancel')" />
      </div>

      <h3 class="sub">Pagamentos</h3>
      <p v-if="!invoice.payments.length" class="iba-muted small">Nenhum pagamento registrado.</p>
      <ul class="payments">
        <li v-for="p in invoice.payments" :key="p.id" :class="{ reversed: p.reversed_at }">
          <div>
            <strong>{{ formatMoney(p.amount) }}</strong>
            <span class="iba-muted"> · {{ paymentMethodLabel(p.method) }} · {{ formatDate(p.paid_at) }}</span>
            <div class="small iba-muted">
              <template v-if="p.received_by">Recebido por {{ p.received_by }}</template>
              <template v-if="p.notes"> · {{ p.notes }}</template>
            </div>
            <div v-if="p.reversed_at" class="small reversed-info">
              <i class="pi pi-undo" aria-hidden="true"></i> Estornado em {{ formatDate(p.reversed_at) }} por {{ p.reversed_by }} — {{ p.reversal_reason }}
            </div>
          </div>
          <Button
            v-if="!p.reversed_at && invoice.status !== 'cancelada'" v-tooltip.left="'Estornar'" icon="pi pi-undo" text rounded
            severity="danger" :aria-label="`Estornar pagamento de ${formatMoney(p.amount)}`" @click="askReason('reverse', p)"
          />
        </li>
      </ul>
    </template>

    <PaymentDialog v-model:visible="payOpen" :invoice="invoice" @paid="onChanged" />

    <!-- Motivo (estorno/cancelamento) -->
    <Dialog
      v-model:visible="reasonOpen" :header="reasonMode === 'cancel' ? 'Cancelar mensalidade' : 'Estornar pagamento'" modal
      :style="{ width: '420px' }" :breakpoints="{ '460px': '95vw' }"
    >
      <form id="reason-form" class="rform" @submit.prevent="confirmReason">
        <label for="reason">Motivo *</label>
        <Textarea id="reason" v-model="reason" rows="3" maxlength="255" auto-resize autofocus fluid :invalid="!!reasonError" />
        <small v-if="reasonError" class="field__error">{{ reasonError }}</small>
      </form>
      <template #footer>
        <Button label="Voltar" text severity="secondary" @click="reasonOpen = false" />
        <Button type="submit" form="reason-form" :label="reasonMode === 'cancel' ? 'Cancelar mensalidade' : 'Estornar'" severity="danger" :loading="saving" />
      </template>
    </Dialog>

    <!-- Ajuste -->
    <Dialog v-model:visible="editOpen" header="Ajustar mensalidade" modal :style="{ width: '420px' }" :breakpoints="{ '460px': '95vw' }">
      <form id="edit-form" class="rform" @submit.prevent="saveEdit">
        <label for="e-due">Vencimento</label>
        <InputText id="e-due" v-model="edit.due_date" type="date" fluid />
        <label for="e-disc">Desconto (R$)</label>
        <InputNumber
          v-model="edit.discount" input-id="e-disc" mode="currency" currency="BRL" locale="pt-BR" :min="0"
          :max="Number(invoice?.amount || 0)" :invalid="!!editErrors.discount" fluid
        />
        <small v-if="editErrors.discount" class="field__error">{{ editErrors.discount }}</small>
        <label for="e-notes">Observação</label>
        <InputText id="e-notes" v-model="edit.notes" maxlength="255" fluid />
      </form>
      <template #footer>
        <Button label="Voltar" text severity="secondary" @click="editOpen = false" />
        <Button type="submit" form="edit-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>
  </Drawer>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ProgressSpinner from 'primevue/progressspinner'
import InvoiceStatusTag from './InvoiceStatusTag.vue'
import PaymentDialog from './PaymentDialog.vue'
import { invoicesApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatDate, formatMoney, formatMonth, paymentMethodLabel } from '@/utils/format'

const visible = defineModel('visible', { type: Boolean, default: false })
const props = defineProps({ invoiceId: { type: Number, default: null } })
const emit = defineEmits(['changed'])

const toast = useToast()
const { notify } = useApiError()

const invoice = ref(null)
const loading = ref(false)
const saving = ref(false)
const payOpen = ref(false)

const reasonOpen = ref(false)
const reasonMode = ref('reverse')
const reason = ref('')
const reasonError = ref('')
const reasonTarget = ref(null)

const editOpen = ref(false)
const edit = reactive({ due_date: '', discount: 0, notes: '' })
const editErrors = ref({})

const hasActivePayments = computed(() => invoice.value?.payments.some((p) => !p.reversed_at))

async function load() {
  if (!props.invoiceId) return
  loading.value = true
  try {
    invoice.value = await invoicesApi.get(props.invoiceId)
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

watch(() => [visible.value, props.invoiceId], ([open]) => open && load(), { immediate: true })

function onChanged(updated) {
  invoice.value = updated
  emit('changed', updated)
}

function askReason(mode, payment = null) {
  reasonMode.value = mode
  reasonTarget.value = payment
  reason.value = ''
  reasonError.value = ''
  reasonOpen.value = true
}

async function confirmReason() {
  if (reason.value.trim().length < 3) {
    reasonError.value = 'Descreva o motivo (mínimo 3 caracteres).'
    return
  }
  saving.value = true
  try {
    if (reasonMode.value === 'cancel') {
      onChanged(await invoicesApi.cancel(invoice.value.id, reason.value.trim()))
      toast.add({ severity: 'success', summary: 'Mensalidade cancelada', life: 3000 })
    } else {
      await invoicesApi.reversePayment(reasonTarget.value.id, reason.value.trim())
      await load()
      emit('changed', invoice.value)
      toast.add({ severity: 'success', summary: 'Pagamento estornado', life: 3000 })
    }
    reasonOpen.value = false
  } catch (e) {
    notify(e)
  } finally {
    saving.value = false
  }
}

function openEdit() {
  Object.assign(edit, {
    due_date: invoice.value.due_date,
    discount: Number(invoice.value.discount),
    notes: invoice.value.notes || ''
  })
  editErrors.value = {}
  editOpen.value = true
}

async function saveEdit() {
  saving.value = true
  try {
    onChanged(await invoicesApi.update(invoice.value.id, {
      due_date: edit.due_date,
      discount: (edit.discount || 0).toFixed(2),
      notes: edit.notes || null
    }))
    editOpen.value = false
    toast.add({ severity: 'success', summary: 'Mensalidade ajustada', life: 3000 })
  } catch (e) {
    editErrors.value = e.fields || {}
    if (!Object.keys(editErrors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.center { display: grid; place-items: center; padding: 2rem; }
.head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.athlete { font-weight: 700; font-size: 1.05rem; color: var(--iba-text); }
.small { font-size: .82rem; }
.values { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin: 1rem 0; padding: .75rem; border-radius: 10px; background: var(--iba-bg); }
.values dt { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--iba-text-muted); font-weight: 600; }
.values dd { margin: .15rem 0 0; }
.due { color: var(--iba-danger); font-weight: 700; }
.actions { display: flex; flex-wrap: wrap; gap: .5rem; margin: 1rem 0; }
.sub { margin: 1.25rem 0 .5rem; font-size: .9rem; }
.payments { list-style: none; padding: 0; margin: 0; display: grid; gap: .5rem; }
.payments li { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; padding: .6rem .75rem; border: 1px solid var(--iba-border); border-radius: 8px; }
.payments li.reversed > div > strong { text-decoration: line-through; color: var(--iba-text-muted); }
.reversed-info { color: var(--iba-danger); margin-top: .2rem; }
.rform { display: grid; gap: .5rem; }
.rform label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
</style>

<style>
.invoice-drawer { width: min(460px, 100vw) !important; }
</style>
