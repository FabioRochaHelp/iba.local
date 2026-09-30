<template>
  <Dialog v-model:visible="visible" header="Registrar pagamento" modal :style="{ width: '440px' }" :breakpoints="{ '480px': '95vw' }">
    <div v-if="invoice" class="summary">
      <strong>{{ invoice.athlete_name }}</strong>
      <span class="iba-muted">{{ subtitle }} · saldo {{ formatMoney(invoice.remaining) }}</span>
    </div>
    <form id="payment-form" class="pform" novalidate @submit.prevent="submit">
      <div class="field">
        <label for="p-amount">Valor recebido</label>
        <InputNumber
          v-model="form.amount" input-id="p-amount" mode="currency" currency="BRL" locale="pt-BR" :min="0.01"
          :max="Number(invoice?.remaining || 0)" :invalid="!!errors.amount" fluid autofocus
        />
        <small v-if="errors.amount" class="field__error">{{ errors.amount }}</small>
      </div>
      <div class="row">
        <div class="field">
          <label for="p-date">Data</label>
          <InputText id="p-date" v-model="form.paid_at" type="date" :max="today" :invalid="!!errors.paid_at" fluid />
          <small v-if="errors.paid_at" class="field__error">{{ errors.paid_at }}</small>
        </div>
        <div class="field">
          <label for="p-method">Forma</label>
          <Select v-model="form.method" input-id="p-method" :options="paymentMethods" option-label="label" option-value="value" fluid />
        </div>
      </div>
      <div class="field">
        <label for="p-notes">Observação</label>
        <InputText id="p-notes" v-model="form.notes" maxlength="255" fluid />
      </div>
    </form>
    <template #footer>
      <Button label="Cancelar" text severity="secondary" @click="visible = false" />
      <Button type="submit" form="payment-form" label="Confirmar recebimento" icon="pi pi-check" :loading="saving" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { invoicesApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatMoney, formatMonth, paymentMethods, todayIso } from '@/utils/format'

const visible = defineModel('visible', { type: Boolean, default: false })
// `invoice` é qualquer cobrança com saldo (mensalidade ou pedido de uniforme);
// `payWith` define para onde o pagamento vai (padrão: mensalidade).
const props = defineProps({
  invoice: { type: Object, default: null },
  payWith: { type: Function, default: (id, payload) => invoicesApi.pay(id, payload) }
})
const subtitle = computed(() =>
  props.invoice?.reference_month ? formatMonth(props.invoice.reference_month) : props.invoice?.item_name || ''
)
const emit = defineEmits(['paid'])

const toast = useToast()
const { notify } = useApiError()
const today = todayIso()
const saving = ref(false)
const errors = ref({})
const form = reactive({ amount: 0, paid_at: today, method: 'pix', notes: '' })

watch(visible, (open) => {
  if (open && props.invoice) {
    Object.assign(form, { amount: Number(props.invoice.remaining), paid_at: todayIso(), method: 'pix', notes: '' })
    errors.value = {}
  }
})

async function submit() {
  errors.value = {}
  if (!form.amount || form.amount <= 0) {
    errors.value.amount = 'Informe o valor.'
    return
  }
  saving.value = true
  try {
    const updated = await props.payWith(props.invoice.id, {
      amount: form.amount.toFixed(2),
      paid_at: form.paid_at,
      method: form.method,
      notes: form.notes || null
    })
    toast.add({ severity: 'success', summary: 'Pagamento registrado', detail: `${props.invoice.athlete_name} · ${formatMoney(form.amount)}`, life: 3000 })
    visible.value = false
    emit('paid', updated)
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.summary { display: flex; flex-direction: column; margin-bottom: 1rem; padding: .75rem; border-radius: 8px; background: color-mix(in srgb, var(--iba-gold) 10%, transparent); }
.pform { display: grid; gap: 1rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
</style>
