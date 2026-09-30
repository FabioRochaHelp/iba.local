<template>
  <Drawer v-model:visible="visible" position="right" class="invoice-drawer" header="Pedido de uniforme">
    <div v-if="loading" class="center"><ProgressSpinner style="width: 36px; height: 36px" /></div>
    <template v-else-if="order">
      <div class="head">
        <RouterLink :to="{ name: 'athlete-show', params: { id: order.athlete_id } }" class="athlete">{{ order.athlete_name }}</RouterLink>
        <UniformStatusTag :status="order.status" />
      </div>
      <p class="iba-muted small">{{ order.item_name }}<template v-if="order.size"> · tam. {{ order.size }}</template> · {{ order.quantity }} un. · pedido em {{ formatDate(order.ordered_at) }}</p>

      <dl class="values">
        <div><dt>Total</dt><dd><strong>{{ formatMoney(order.total) }}</strong></dd></div>
        <div><dt>Pago</dt><dd>{{ formatMoney(order.paid_amount) }}</dd></div>
        <div><dt>Saldo</dt><dd :class="{ due: Number(order.remaining) > 0 && order.status !== 'cancelado' }">{{ formatMoney(order.remaining) }}</dd></div>
      </dl>

      <p class="delivery">
        <template v-if="order.delivered"><i class="pi pi-check-circle ok" aria-hidden="true"></i> Entregue em {{ formatDate(order.delivered_at) }}<template v-if="order.delivered_by"> por {{ order.delivered_by }}</template></template>
        <template v-else-if="order.status !== 'cancelado'"><i class="pi pi-box" aria-hidden="true"></i> Aguardando entrega</template>
      </p>
      <Message v-if="order.status === 'cancelado'" severity="secondary" :closable="false">Cancelado em {{ formatDate(order.cancelled_at) }} — {{ order.cancel_reason }}</Message>
      <p v-if="order.notes" class="small"><strong>Obs.:</strong> {{ order.notes }}</p>

      <div v-if="order.status !== 'cancelado'" class="actions">
        <Button v-if="['pendente', 'pago_parcial'].includes(order.status)" label="Receber" icon="pi pi-wallet" @click="payOpen = true" />
        <Button
          :label="order.delivered ? 'Desfazer entrega' : 'Marcar entregue'" :icon="order.delivered ? 'pi pi-undo' : 'pi pi-box'"
          severity="secondary" outlined :loading="saving" @click="toggleDelivery"
        />
        <Button v-if="!hasActivePayments && !order.delivered" label="Cancelar" icon="pi pi-ban" severity="danger" text @click="askReason('cancel')" />
      </div>

      <h3 class="sub">Pagamentos</h3>
      <p v-if="!order.payments.length" class="iba-muted small">Nenhum pagamento registrado.</p>
      <ul class="payments">
        <li v-for="p in order.payments" :key="p.id" :class="{ reversed: p.reversed_at }">
          <div>
            <strong>{{ formatMoney(p.amount) }}</strong>
            <span class="iba-muted"> · {{ paymentMethodLabel(p.method) }} · {{ formatDate(p.paid_at) }}</span>
            <div v-if="p.reversed_at" class="small reversed-info"><i class="pi pi-undo" aria-hidden="true"></i> Estornado — {{ p.reversal_reason }}</div>
          </div>
          <Button
            v-if="!p.reversed_at && order.status !== 'cancelado'" v-tooltip.left="'Estornar'" icon="pi pi-undo" text rounded severity="danger"
            :aria-label="`Estornar ${formatMoney(p.amount)}`" @click="askReason('reverse', p)"
          />
        </li>
      </ul>
    </template>

    <PaymentDialog v-model:visible="payOpen" :invoice="order" :pay-with="uniformsApi.pay" @paid="onChanged" />

    <Dialog
      v-model:visible="reasonOpen" :header="reasonMode === 'cancel' ? 'Cancelar pedido' : 'Estornar pagamento'" modal
      :style="{ width: '420px' }" :breakpoints="{ '460px': '95vw' }"
    >
      <form id="ureason-form" class="rform" @submit.prevent="confirmReason">
        <label for="ureason">Motivo *</label>
        <Textarea id="ureason" v-model="reason" rows="3" maxlength="255" auto-resize autofocus fluid :invalid="!!reasonError" />
        <small v-if="reasonError" class="field__error">{{ reasonError }}</small>
      </form>
      <template #footer>
        <Button label="Voltar" text severity="secondary" @click="reasonOpen = false" />
        <Button type="submit" form="ureason-form" :label="reasonMode === 'cancel' ? 'Cancelar pedido' : 'Estornar'" severity="danger" :loading="saving" />
      </template>
    </Dialog>
  </Drawer>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import ProgressSpinner from 'primevue/progressspinner'
import UniformStatusTag from './UniformStatusTag.vue'
import PaymentDialog from '@/components/finance/PaymentDialog.vue'
import { uniformsApi } from '@/api/uniforms'
import { invoicesApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatDate, formatMoney, paymentMethodLabel } from '@/utils/format'

const visible = defineModel('visible', { type: Boolean, default: false })
const props = defineProps({ orderId: { type: Number, default: null } })
const emit = defineEmits(['changed'])

const toast = useToast()
const { notify } = useApiError()
const order = ref(null)
const loading = ref(false)
const saving = ref(false)
const payOpen = ref(false)
const reasonOpen = ref(false)
const reasonMode = ref('reverse')
const reason = ref('')
const reasonError = ref('')
const reasonTarget = ref(null)

const hasActivePayments = computed(() => order.value?.payments.some((p) => !p.reversed_at))

async function load() {
  if (!props.orderId) return
  loading.value = true
  try {
    order.value = await uniformsApi.order(props.orderId)
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}
watch(() => [visible.value, props.orderId], ([open]) => open && load(), { immediate: true })

function onChanged(updated) {
  order.value = updated
  emit('changed', updated)
}

async function toggleDelivery() {
  saving.value = true
  try {
    onChanged(await uniformsApi.deliver(order.value.id, !order.value.delivered))
  } catch (e) {
    notify(e)
  } finally {
    saving.value = false
  }
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
      onChanged(await uniformsApi.cancel(order.value.id, reason.value.trim()))
    } else {
      // Estorno usa a rota geral de pagamentos (o backend encaminha ao módulo de uniformes).
      await invoicesApi.reversePayment(reasonTarget.value.id, reason.value.trim())
      await load()
      emit('changed', order.value)
    }
    toast.add({ severity: 'success', summary: reasonMode.value === 'cancel' ? 'Pedido cancelado' : 'Pagamento estornado', life: 3000 })
    reasonOpen.value = false
  } catch (e) {
    notify(e)
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
.delivery { display: flex; gap: .4rem; align-items: center; }
.ok { color: var(--iba-success); }
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
