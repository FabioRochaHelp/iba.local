<template>
  <div>
    <div v-if="loading" class="iba-card"><ProgressSpinner style="width: 40px; height: 40px" /></div>

    <template v-else-if="athlete">
      <section class="iba-card hero">
        <span class="hero__avatar" aria-hidden="true">{{ initials(athlete.name) }}</span>
        <div class="hero__info">
          <h1>{{ athlete.name }}</h1>
          <div class="hero__meta">
            <StatusTag :status="athlete.status" />
            <span v-if="athlete.category"><i class="pi pi-flag" aria-hidden="true"></i> {{ athlete.category }}</span>
            <span v-if="athlete.age !== null"><i class="pi pi-calendar" aria-hidden="true"></i> {{ athlete.age }} anos</span>
            <span v-for="p in athlete.positions" :key="p.id" class="pos">{{ p.name }}</span>
          </div>
        </div>
        <div class="hero__actions">
          <RouterLink :to="{ name: 'athletes' }"><Button label="Voltar" icon="pi pi-arrow-left" text severity="secondary" /></RouterLink>
          <template v-if="auth.isAdmin">
            <RouterLink :to="{ name: 'athlete-edit', params: { id: athlete.id } }">
              <Button label="Editar" icon="pi pi-pencil" />
            </RouterLink>
            <Button v-tooltip.top="'Excluir'" icon="pi pi-trash" severity="danger" text aria-label="Excluir atleta" @click="confirmDelete" />
          </template>
        </div>
      </section>

      <Message v-if="athlete.has_health_condition" severity="warn" :closable="false" class="health-alert">
        <strong>Atenção — condição de saúde:</strong> {{ athlete.health_condition }}
      </Message>

      <Tabs value="dados" class="iba-card tabs">
        <TabList>
          <Tab value="dados">Dados</Tab>
          <Tab value="responsavel">Responsável</Tab>
          <Tab value="evolucao">Evolução</Tab>
          <Tab v-if="auth.isAdmin" value="financeiro">Financeiro</Tab>
          <Tab v-if="auth.isAdmin" value="uniformes">Uniformes</Tab>
          <Tab value="frequencia">Frequência</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="dados">
            <dl class="info">
              <div><dt>Nascimento</dt><dd>{{ formatDate(athlete.birth_date) }}</dd></div>
              <div><dt>Matrícula</dt><dd>{{ formatDate(athlete.enrollment_date) }}</dd></div>
              <div><dt>Plano</dt><dd>{{ athlete.plan ? athlete.plan.name : 'Sem plano' }}</dd></div>
              <template v-if="auth.isAdmin && athlete.plan">
                <div><dt>Mensalidade</dt><dd>{{ planFee }}</dd></div>
                <div><dt>Plano vigente desde</dt><dd>{{ formatDate(athlete.plan.start_date) }}</dd></div>
              </template>
              <div class="wide"><dt>Observações</dt><dd class="pre">{{ athlete.notes || '—' }}</dd></div>
            </dl>

            <template v-if="auth.isAdmin && athlete.plan_history?.length > 1">
              <h3 class="sub">Histórico de planos</h3>
              <DataTable :value="athlete.plan_history" size="small">
                <Column field="plan_name" header="Plano" />
                <Column header="Período">
                  <template #body="{ data }">{{ formatDate(data.start_date) }} até {{ data.end_date ? formatDate(data.end_date) : 'atual' }}</template>
                </Column>
                <Column header="Desconto">
                  <template #body="{ data }">{{ discountLabel(data) }}</template>
                </Column>
              </DataTable>
            </template>
          </TabPanel>

          <TabPanel value="responsavel">
            <dl class="info">
              <div class="wide"><dt>Nome</dt><dd>{{ athlete.guardian.name }}</dd></div>
              <div class="wide">
                <dt>Telefones</dt>
                <dd>
                  <ul class="phones">
                    <li v-for="p in athlete.guardian.phones" :key="p.phone">
                      <PhoneLink :phone="p.phone" :whatsapp="p.is_whatsapp" :message="`Olá! Aqui é da Irmãos da Bola Academy, sobre o(a) atleta ${athlete.name}.`" />
                      <span v-if="p.label" class="iba-muted"> · {{ p.label }}</span>
                    </li>
                    <li v-if="!athlete.guardian.phones.length" class="iba-muted">Nenhum telefone cadastrado</li>
                  </ul>
                </dd>
              </div>
              <template v-if="auth.isAdmin">
                <div><dt>CPF</dt><dd>{{ athlete.guardian.cpf_masked || '—' }}</dd></div>
                <div><dt>E-mail</dt><dd>{{ athlete.guardian.email || '—' }}</dd></div>
              </template>
              <div v-if="athlete.siblings.length" class="wide">
                <dt>Irmãos na escolinha</dt>
                <dd class="siblings">
                  <RouterLink v-for="s in athlete.siblings" :key="s.id" :to="{ name: 'athlete-show', params: { id: s.id } }">
                    <Chip :label="s.name" icon="pi pi-user" />
                  </RouterLink>
                </dd>
              </div>
            </dl>
          </TabPanel>

          <TabPanel v-if="auth.isAdmin" value="financeiro">
            <AthleteInvoices :athlete-id="athlete.id" :athlete-name="athlete.name" />
            <AthleteSponsorships :athlete-id="athlete.id" />
          </TabPanel>
          <TabPanel v-if="auth.isAdmin" value="uniformes"><AthleteUniforms :athlete-id="athlete.id" :athlete-name="athlete.name" /></TabPanel>
          <TabPanel value="evolucao"><AthleteEvolution :athlete-id="athlete.id" /></TabPanel>
          <TabPanel value="frequencia"><AthleteAttendance :athlete-id="athlete.id" /></TabPanel>
        </TabPanels>
      </Tabs>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Chip from 'primevue/chip'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ProgressSpinner from 'primevue/progressspinner'
import StatusTag from '@/components/StatusTag.vue'
import PhoneLink from '@/components/PhoneLink.vue'
import AthleteInvoices from '@/components/finance/AthleteInvoices.vue'
import AthleteAttendance from '@/components/classes/AthleteAttendance.vue'
import AthleteEvolution from '@/components/evolution/AthleteEvolution.vue'
import AthleteUniforms from '@/components/uniforms/AthleteUniforms.vue'
import AthleteSponsorships from '@/components/sponsors/AthleteSponsorships.vue'
import { athletesApi } from '@/api/athletes'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { finalFee, formatDate, formatMoney, initials } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()

const athlete = ref(null)
const loading = ref(true)

const planFee = computed(() => {
  const p = athlete.value?.plan
  if (!p) return '—'
  if (p.discount_type === 'cortesia') return 'Cortesia'
  const value = formatMoney(finalFee(p.monthly_fee, p.discount_type, p.discount_value))
  return p.discount_type === 'nenhum' ? value : `${value} (${discountLabel(p)})`
})

function discountLabel(p) {
  return {
    nenhum: 'Sem desconto',
    cortesia: 'Cortesia',
    percentual: `${Number(p.discount_value)}% de desconto`,
    valor: `${formatMoney(p.discount_value)} de desconto`
  }[p.discount_type]
}

async function load() {
  loading.value = true
  try {
    athlete.value = await athletesApi.get(route.params.id)
  } catch (e) {
    notify(e)
    if (e.response?.status === 404) router.replace({ name: 'athletes' })
  } finally {
    loading.value = false
  }
}

function confirmDelete() {
  confirm.require({
    header: 'Excluir atleta',
    message: `Excluir ${athlete.value.name}? O cadastro fica inativo e deixa de aparecer nas listas.`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Excluir', severity: 'danger' },
    accept: async () => {
      try {
        await athletesApi.remove(athlete.value.id)
        toast.add({ severity: 'success', summary: 'Atleta excluído', life: 3000 })
        router.replace({ name: 'athletes' })
      } catch (e) {
        notify(e)
      }
    }
  })
}

watch(() => route.params.id, (v) => v && load())
onMounted(load)
</script>

<style scoped>
.hero { display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap; border-top: 3px solid var(--iba-gold); }
.hero__avatar {
  width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0;
  background: var(--iba-black); color: var(--iba-gold-light); border: 3px solid var(--iba-gold);
  font-family: var(--iba-font-title); font-weight: 800; font-size: 1.4rem;
}
.hero__info { flex: 1; min-width: 220px; }
.hero__meta { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-top: .5rem; color: var(--iba-text-muted); font-size: .85rem; }
.hero__actions { display: flex; gap: .25rem; align-items: center; }
.pos {
  font-size: .72rem; font-weight: 600; padding: .1rem .5rem; border-radius: 4px;
  background: color-mix(in srgb, var(--iba-blue) 14%, transparent); color: var(--iba-blue);
}
.health-alert { margin: 1rem 0 0; }
.tabs { margin-top: 1rem; padding: 0 1rem 1rem; }
.info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 2rem; margin: 0; }
.info .wide { grid-column: 1 / -1; }
.info dt { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--iba-text-muted); font-weight: 600; }
.info dd { margin: .2rem 0 0; font-size: .95rem; }
.pre { white-space: pre-line; }
.phones { list-style: none; padding: 0; margin: 0; display: grid; gap: .35rem; }
.siblings { display: flex; gap: .5rem; flex-wrap: wrap; }
.siblings a { text-decoration: none; }
.sub { margin: 1.5rem 0 .75rem; }
</style>
