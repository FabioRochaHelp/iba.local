<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Olá, {{ firstName }}!</h1>
        <p>Bem-vindo ao sistema de gestão da Irmãos da Bola Academy.</p>
      </div>
    </div>

    <div class="iba-grid">
      <component :is="card.to ? 'RouterLink' : 'article'" v-for="card in cards" :key="card.label" :to="card.to" class="stat iba-card">
        <span class="stat__icon" :style="{ '--c': card.color }"><i :class="card.icon" aria-hidden="true"></i></span>
        <div>
          <p class="stat__label">{{ card.label }}</p>
          <p class="stat__value">{{ card.value }}</p>
          <p class="stat__hint">{{ card.hint }}</p>
        </div>
      </component>
    </div>

    <section v-if="summary?.classes_today?.length" class="iba-card today-classes">
      <h2><i class="pi pi-calendar" aria-hidden="true"></i> Turmas de hoje</h2>
      <ul>
        <li v-for="c in summary.classes_today" :key="c.id">
          <div>
            <strong>{{ c.name }}</strong>
            <span class="iba-muted"> · {{ c.start_time }}–{{ c.end_time }} · {{ c.athletes_count }} atleta(s)<template v-if="auth.isAdmin && c.coach_name"> · {{ c.coach_name }}</template></span>
          </div>
          <RouterLink :to="{ name: 'attendance-class', params: { classId: c.id } }">
            <Button
              :label="c.last_session_date === todayIso ? 'Revisar chamada' : 'Fazer chamada'" icon="pi pi-check-square" size="small"
              :outlined="c.last_session_date === todayIso"
            />
          </RouterLink>
        </li>
      </ul>
    </section>

    <section v-if="summary?.finance" class="iba-card chart-card">
      <RevenueChart :series="summary.finance.series" />
    </section>

    <section v-if="summary?.birthdays?.length" class="iba-card birthdays">
      <h2><i class="pi pi-gift" aria-hidden="true"></i> Aniversariantes do mês</h2>
      <ul>
        <li v-for="b in summary.birthdays" :key="b.id" :class="{ today: b.day === todayDay }">
          <span class="day">{{ String(b.day).padStart(2, '0') }}</span>
          <RouterLink :to="{ name: 'athlete-show', params: { id: b.id } }">{{ b.name }}</RouterLink>
          <span class="iba-muted">faz {{ b.turning }} anos</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { dashboardApi } from '@/api/dashboard'
import Button from 'primevue/button'
import RevenueChart from '@/components/finance/RevenueChart.vue'
import { formatMoney, formatMonth, todayIso as today } from '@/utils/format'

const auth = useAuthStore()
const firstName = computed(() => (auth.user?.name || '').split(' ')[0])
const summary = ref(null)
const todayDay = new Date().getDate()
const todayIso = today()

onMounted(async () => {
  try {
    summary.value = await dashboardApi.summary()
  } catch {
    /* painel continua com placeholders */
  }
})

const f = computed(() => summary.value?.finance)

// Caixa do mês (pagamentos recebidos) comparado ao previsto das mensalidades do mês.
const receivedHint = computed(() => {
  if (!f.value) return ''
  const inv = f.value.current.invoices
  if (!inv.total_count) return `Mensalidades de ${formatMonth(f.value.current.month)} ainda não geradas`
  return `${formatMoney(inv.received)} de ${formatMoney(inv.expected)} das mensalidades do mês`
})

const cards = computed(() => {
  const base = [
    {
      label: 'Atletas ativos',
      value: summary.value ? summary.value.athletes.active : '—',
      hint: summary.value ? `${summary.value.athletes.total} cadastrados` : '',
      icon: 'pi pi-users',
      color: 'var(--iba-gold)'
    },
    {
      label: 'Frequência (30 dias)',
      value: summary.value?.attendance_rate_30d != null ? `${summary.value.attendance_rate_30d}%` : '—',
      hint: summary.value?.attendance_rate_30d != null ? (auth.isAdmin ? 'todas as turmas' : 'suas turmas') : 'sem chamadas no período',
      icon: 'pi pi-check-square',
      color: 'var(--iba-blue)',
      to: { name: 'classes' }
    }
  ]
  if (auth.isAdmin) {
    base.push(
      {
        label: 'Recebido no mês',
        value: f.value ? formatMoney(f.value.current.cash_received) : '—',
        hint: receivedHint.value,
        icon: 'pi pi-wallet',
        color: 'var(--iba-success)',
        to: { name: 'invoices' }
      },
      {
        label: 'Em atraso',
        value: f.value ? formatMoney(f.value.overdue_total) : '—',
        hint: f.value ? `${f.value.overdue_guardians} responsável(is)` : '',
        icon: 'pi pi-exclamation-triangle',
        color: 'var(--iba-danger)',
        to: { name: 'delinquency' }
      },
      {
        label: 'Uniformes a receber',
        value: f.value ? formatMoney(f.value.uniforms.receivable) : '—',
        hint: f.value ? `${f.value.uniforms.to_deliver_count} pedido(s) a entregar` : '',
        icon: 'pi pi-shopping-bag',
        color: 'var(--iba-blue)',
        to: { name: 'uniforms' }
      },
      {
        label: `Patrocínios em ${new Date().getFullYear()}`,
        value: f.value ? formatMoney(f.value.sponsorships_year) : '—',
        hint: 'total recebido no ano',
        icon: 'pi pi-star',
        color: 'var(--iba-gold)',
        to: { name: 'sponsors' }
      }
    )
  }
  return base
})
</script>

<style scoped>
.stat { display: flex; gap: 1rem; align-items: center; text-decoration: none; color: inherit; }
a.stat:hover { border-color: var(--iba-gold); }
.chart-card { margin-top: 1.5rem; }
.today-classes { margin-top: 1.5rem; border-left: 4px solid var(--iba-gold); }
.today-classes h2 { display: flex; gap: .5rem; align-items: center; }
.today-classes h2 i { color: var(--iba-gold); }
.today-classes ul { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: .6rem; }
.today-classes li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
.stat__icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: var(--c);
  background: color-mix(in srgb, var(--c) 14%, transparent);
  font-size: 1.25rem;
}
.stat__label { margin: 0; color: var(--iba-text-muted); font-size: .8rem; font-weight: 500; }
.stat__value { margin: .1rem 0; font-family: var(--iba-font-title); font-weight: 800; font-size: 1.6rem; }
.stat__hint { margin: 0; color: var(--iba-text-muted); font-size: .75rem; }

.birthdays { margin-top: 1.5rem; }
.birthdays h2 { display: flex; gap: .5rem; align-items: center; }
.birthdays h2 i { color: var(--iba-gold); }
.birthdays ul { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
.birthdays li { display: flex; gap: .6rem; align-items: center; }
.birthdays .day {
  width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; font-weight: 700;
  background: var(--iba-black); color: var(--iba-gold-light); font-size: .85rem;
}
.birthdays li.today .day { background: var(--iba-gold); color: #111; }
.birthdays a { color: var(--iba-text); font-weight: 500; }
</style>
