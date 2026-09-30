<template>
  <Dialog v-model:visible="visible" header="Novo pedido de uniforme" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '95vw' }">
    <form id="uorder-form" class="oform" novalidate @submit.prevent="submit">
      <div v-if="!fixedAthlete" class="field">
        <label for="uo-athlete">Atleta *</label>
        <AthletePicker v-model="athlete" input-id="uo-athlete" :invalid="!!errors.athlete_id" />
        <small v-if="errors.athlete_id" class="field__error">{{ errors.athlete_id }}</small>
      </div>
      <p v-else class="fixed"><i class="pi pi-user" aria-hidden="true"></i> {{ fixedAthlete.name }}</p>

      <div class="row">
        <div class="field">
          <label for="uo-item">Item *</label>
          <Select
            v-model="form.item_id" input-id="uo-item" :options="items" option-value="id"
            :option-label="(i) => `${i.name} — ${formatMoney(i.price)}`" placeholder="Selecione" :invalid="!!errors.item_id" fluid
          />
          <small v-if="errors.item_id" class="field__error">{{ errors.item_id }}</small>
        </div>
        <div class="field">
          <label for="uo-size">Tamanho</label>
          <Select v-model="form.size" input-id="uo-size" :options="sizes" placeholder="—" show-clear :invalid="!!errors.size" fluid />
          <small v-if="errors.size" class="field__error">{{ errors.size }}</small>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label for="uo-qty">Quantidade</label>
          <InputNumber v-model="form.quantity" input-id="uo-qty" :min="1" :max="20" show-buttons fluid />
        </div>
        <div class="field">
          <label for="uo-price">Preço unitário</label>
          <InputNumber v-model="form.unit_price" input-id="uo-price" mode="currency" currency="BRL" locale="pt-BR" :min="0" fluid />
        </div>
      </div>
      <div class="total">Total: <strong>{{ formatMoney(total) }}</strong></div>

      <label class="toggle">
        <ToggleSwitch v-model="payNow" input-id="uo-paynow" />
        <span>Registrar pagamento agora</span>
      </label>
      <div v-if="payNow" class="row">
        <div class="field">
          <label for="uo-pay">Valor pago</label>
          <InputNumber
            v-model="payment.amount" input-id="uo-pay" mode="currency" currency="BRL" locale="pt-BR" :min="0.01" :max="total"
            :invalid="!!errors['payment.amount']" fluid
          />
          <small v-if="errors['payment.amount']" class="field__error">{{ errors['payment.amount'] }}</small>
        </div>
        <div class="field">
          <label for="uo-method">Forma</label>
          <Select v-model="payment.method" input-id="uo-method" :options="paymentMethods" option-label="label" option-value="value" fluid />
        </div>
      </div>
      <div class="field">
        <label for="uo-notes">Observação</label>
        <InputText id="uo-notes" v-model="form.notes" maxlength="255" fluid />
      </div>
    </form>
    <template #footer>
      <Button label="Cancelar" text severity="secondary" @click="visible = false" />
      <Button type="submit" form="uorder-form" label="Criar pedido" icon="pi pi-check" :loading="saving" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import AthletePicker from '@/components/AthletePicker.vue'
import { uniformsApi } from '@/api/uniforms'
import { useApiError } from '@/composables/useApiError'
import { DEFAULT_SIZES, formatMoney, paymentMethods, todayIso } from '@/utils/format'

const visible = defineModel('visible', { type: Boolean, default: false })
const props = defineProps({ fixedAthlete: { type: Object, default: null } })
const emit = defineEmits(['created'])

const toast = useToast()
const { notify } = useApiError()
const items = ref([])
const athlete = ref(null)
const saving = ref(false)
const errors = ref({})
const payNow = ref(false)
const form = reactive({ item_id: null, size: null, quantity: 1, unit_price: 0, notes: '' })
const payment = reactive({ amount: 0, method: 'pix' })

const selectedItem = computed(() => items.value.find((i) => i.id === form.item_id))
const sizes = computed(() => (selectedItem.value?.sizes?.length ? selectedItem.value.sizes : DEFAULT_SIZES))
const total = computed(() => Math.round((form.unit_price || 0) * (form.quantity || 1) * 100) / 100)

watch(() => form.item_id, () => {
  if (selectedItem.value) form.unit_price = Number(selectedItem.value.price)
  if (form.size && !sizes.value.includes(form.size)) form.size = null
})
watch(total, (t) => { payment.amount = t })

watch(visible, async (open) => {
  if (!open) return
  errors.value = {}
  payNow.value = false
  athlete.value = null
  Object.assign(form, { item_id: null, size: null, quantity: 1, unit_price: 0, notes: '' })
  try {
    items.value = await uniformsApi.items(true)
  } catch (e) {
    notify(e)
  }
})

async function submit() {
  const athleteId = props.fixedAthlete?.id || athlete.value?.id
  errors.value = {}
  if (!athleteId) errors.value.athlete_id = 'Selecione o atleta.'
  if (!form.item_id) errors.value.item_id = 'Selecione o item.'
  if (Object.keys(errors.value).length) return

  saving.value = true
  try {
    const order = await uniformsApi.createOrder({
      athlete_id: athleteId,
      item_id: form.item_id,
      size: form.size,
      quantity: form.quantity,
      unit_price: Number(form.unit_price || 0).toFixed(2),
      notes: form.notes || null,
      payment: payNow.value && payment.amount > 0
        ? { amount: Number(payment.amount).toFixed(2), paid_at: todayIso(), method: payment.method }
        : null
    })
    toast.add({ severity: 'success', summary: 'Pedido criado', detail: `${order.item_name} · ${order.athlete_name}`, life: 3000 })
    visible.value = false
    emit('created', order)
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.oform { display: grid; gap: 1rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
.fixed { margin: 0; font-weight: 600; }
.total { text-align: right; font-size: 1rem; }
.total strong { font-family: var(--iba-font-title); font-size: 1.2rem; color: var(--iba-gold-text); }
.toggle { display: flex; align-items: center; gap: .6rem; font-weight: 500; }
</style>
