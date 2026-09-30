<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Inadimplência</h1>
        <p>Mensalidades vencidas e não quitadas, agrupadas por responsável.</p>
      </div>
      <Button label="Atualizar" icon="pi pi-refresh" text severity="secondary" :loading="loading" @click="load" />
    </div>

    <div class="kpis">
      <article class="iba-card kpi">
        <span class="kpi__label"><i class="pi pi-exclamation-triangle danger" aria-hidden="true"></i> Total em atraso</span>
        <strong class="kpi__value">{{ formatMoney(report.total) }}</strong>
      </article>
      <article class="iba-card kpi">
        <span class="kpi__label">Responsáveis</span>
        <strong class="kpi__value">{{ report.groups.length }}</strong>
      </article>
      <article class="iba-card kpi">
        <span class="kpi__label">Mensalidades vencidas</span>
        <strong class="kpi__value">{{ report.invoices }}</strong>
      </article>
    </div>

    <section v-if="!loading && !report.groups.length" class="iba-card empty">
      <i class="pi pi-check-circle" aria-hidden="true"></i>
      <p><strong>Nenhuma mensalidade em atraso.</strong></p>
    </section>

    <div class="groups">
      <article v-for="g in report.groups" :key="g.guardian_id" class="iba-card group">
        <header class="group__head">
          <div>
            <h2>{{ g.guardian_name }}</h2>
            <p class="iba-muted small">
              <template v-if="g.guardian_phone">{{ formatPhone(g.guardian_phone) }} · </template>
              atraso de {{ g.days_overdue }} dia(s)
            </p>
          </div>
          <div class="group__total">
            <span class="iba-muted small">Em aberto</span>
            <strong>{{ formatMoney(g.total) }}</strong>
          </div>
        </header>

        <table class="group__table">
          <thead><tr><th scope="col">Atleta</th><th scope="col">Referência</th><th scope="col">Vencimento</th><th scope="col" class="num">Saldo</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
          <tbody>
            <tr v-for="inv in g.invoices" :key="inv.id">
              <td><RouterLink :to="{ name: 'athlete-show', params: { id: inv.athlete_id } }">{{ inv.athlete_name }}</RouterLink></td>
              <td>{{ formatMonth(inv.reference_month) }}</td>
              <td>{{ formatDate(inv.due_date) }}</td>
              <td class="num">{{ formatMoney(inv.remaining) }}</td>
              <td class="num">
                <Button label="Receber" size="small" text icon="pi pi-wallet" @click="openPay(inv, g)" />
              </td>
            </tr>
          </tbody>
        </table>

        <footer class="group__foot">
          <a v-if="whatsappLink(g)" :href="whatsappLink(g)" target="_blank" rel="noopener noreferrer" class="wa-btn">
            <i class="pi pi-whatsapp" aria-hidden="true"></i> Cobrar pelo WhatsApp
          </a>
          <span v-else class="iba-muted small"><i class="pi pi-exclamation-circle" aria-hidden="true"></i> Responsável sem telefone cadastrado</span>
        </footer>
      </article>
    </div>

    <PaymentDialog v-model:visible="payOpen" :invoice="payTarget" @paid="load" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import PaymentDialog from '@/components/finance/PaymentDialog.vue'
import { reportsApi } from '@/api/finance'
import { useApiError } from '@/composables/useApiError'
import { formatDate, formatMoney, formatMonth, formatPhone, whatsappUrl } from '@/utils/format'

const { notify } = useApiError()
const report = ref({ groups: [], total: '0.00', invoices: 0 })
const loading = ref(false)
const payOpen = ref(false)
const payTarget = ref(null)

async function load() {
  loading.value = true
  try {
    report.value = await reportsApi.delinquency()
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

/** Mensagem montada como texto puro e codificada na URL (sem HTML). */
function whatsappLink(g) {
  const first = g.guardian_name.split(' ')[0]
  const lines = g.invoices.map((i) => `• ${i.athlete_name} — ${formatMonth(i.reference_month)}: ${formatMoney(i.remaining)}`)
  const msg = [
    `Olá, ${first}! Tudo bem?`,
    'Aqui é da Irmãos da Bola Academy. Consta em aberto:',
    ...lines,
    `Total: ${formatMoney(g.total)}.`,
    'Se já pagou, por favor desconsidere e nos envie o comprovante. Obrigado! ⚽'
  ].join('\n')
  return whatsappUrl(g.guardian_phone, msg)
}

function openPay(inv, g) {
  payTarget.value = { ...inv, guardian_name: g.guardian_name }
  payOpen.value = true
}

onMounted(load)
</script>

<style scoped>
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: .2rem; }
.kpi__label { font-size: .8rem; color: var(--iba-text-muted); font-weight: 500; display: flex; gap: .35rem; align-items: center; }
.kpi__value { font-family: var(--iba-font-title); font-weight: 800; font-size: 1.5rem; }
.danger { color: var(--iba-danger); }
.small { font-size: .8rem; }
.empty { text-align: center; padding: 2rem; }
.empty i { font-size: 2rem; color: var(--iba-success); }
.groups { display: grid; gap: 1rem; }
.group { border-left: 4px solid var(--iba-danger); }
.group__head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.group__head h2 { font-size: 1rem; }
.group__head p { margin: .25rem 0 0; }
.group__total { text-align: right; display: flex; flex-direction: column; }
.group__total strong { font-family: var(--iba-font-title); font-size: 1.3rem; color: var(--iba-danger); }
.group__table { width: 100%; border-collapse: collapse; margin: .75rem 0; font-size: .9rem; }
.group__table th, .group__table td { padding: .45rem .5rem; border-bottom: 1px solid var(--iba-border); text-align: left; }
.group__table thead th { font-size: .72rem; text-transform: uppercase; color: var(--iba-text-muted); }
.group__table .num { text-align: right; }
.group__table a { color: var(--iba-text); }
.group__foot { display: flex; justify-content: flex-end; }
.wa-btn {
  display: inline-flex; align-items: center; gap: .5rem; padding: .5rem 1rem; border-radius: 8px;
  background: #1f8f3f; color: #fff; text-decoration: none; font-weight: 600;
}
.wa-btn:hover { background: #197534; }
@media (max-width: 640px) {
  .group__table th:nth-child(3), .group__table td:nth-child(3) { display: none; }
}
</style>
