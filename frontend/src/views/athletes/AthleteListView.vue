<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Atletas</h1>
        <p>{{ total }} atleta(s) encontrados</p>
      </div>
      <RouterLink v-if="auth.isAdmin" :to="{ name: 'athlete-new' }">
        <Button label="Novo atleta" icon="pi pi-plus" />
      </RouterLink>
    </div>

    <section class="iba-card filters" aria-label="Filtros">
      <IconField class="filters__search">
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="filters.search" placeholder="Buscar por atleta, responsável ou telefone" maxlength="100"
          aria-label="Buscar" fluid
        />
      </IconField>
      <Select
        v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
        placeholder="Status" show-clear aria-label="Status"
      />
      <Select
        v-model="filters.birth_year" :options="categoryOptions" option-label="label" option-value="value"
        placeholder="Categoria" show-clear filter aria-label="Categoria"
      />
      <Select
        v-if="auth.isAdmin" v-model="filters.plan_id" :options="plans" option-label="name" option-value="id"
        placeholder="Plano" show-clear aria-label="Plano"
      />
      <label class="filters__toggle">
        <ToggleSwitch v-model="filters.health" input-id="f-health" />
        <span>Com condição de saúde</span>
      </label>
    </section>

    <section class="iba-card table-card">
      <DataTable
        :value="rows" lazy :loading="loading" :total-records="total" :rows="perPage" :first="first"
        paginator :rows-per-page-options="[10, 20, 50]" data-key="id" row-hover
        :sort-field="sort.field" :sort-order="sort.order" removable-sort
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        current-page-report-template="{first}–{last} de {totalRecords}"
        class="athletes-table" @page="onPage" @sort="onSort" @row-click="open"
      >
        <template #empty>
          <div class="empty">
            <i class="pi pi-users" aria-hidden="true"></i>
            <p>Nenhum atleta encontrado.</p>
          </div>
        </template>

        <Column field="name" header="Atleta" sortable>
          <template #body="{ data }">
            <div class="athlete-cell">
              <span class="avatar-sm" aria-hidden="true">{{ initials(data.name) }}</span>
              <div>
                <strong>{{ data.name }}</strong>
                <i
                  v-if="data.has_health_condition" v-tooltip.top="data.health_condition"
                  class="pi pi-heart-fill health" :aria-label="`Condição de saúde: ${data.health_condition}`"
                ></i>
                <div class="positions">
                  <span v-for="p in data.positions" :key="p.id" class="pos" :title="p.name">{{ p.short_name }}</span>
                </div>
              </div>
            </div>
          </template>
        </Column>

        <Column field="birth_date" header="Categoria" sortable>
          <template #body="{ data }">
            <template v-if="data.category">
              <strong>{{ data.category }}</strong>
              <div class="iba-muted small">{{ data.age }} anos</div>
            </template>
            <span v-else class="iba-muted">—</span>
          </template>
        </Column>

        <Column field="guardian" header="Responsável" sortable class="col-lg">
          <template #body="{ data }">
            <div>{{ data.guardian.name }}</div>
            <PhoneLink
              v-if="data.guardian.phones[0]" :phone="data.guardian.phones[0].phone"
              :whatsapp="data.guardian.phones[0].is_whatsapp" class="small"
            />
          </template>
        </Column>

        <Column field="plan" header="Plano" sortable class="col-md">
          <template #body="{ data }">
            <template v-if="data.plan">
              <div>{{ data.plan.name }}</div>
              <div v-if="auth.isAdmin" class="small iba-muted">
                <template v-if="data.plan.discount_type === 'cortesia'">Cortesia</template>
                <template v-else>{{ formatMoney(finalFee(data.plan.monthly_fee, data.plan.discount_type, data.plan.discount_value)) }}/mês</template>
              </div>
            </template>
            <span v-else class="iba-muted">Sem plano</span>
          </template>
        </Column>

        <Column field="status" header="Status" sortable class="col-md">
          <template #body="{ data }"><StatusTag :status="data.status" /></template>
        </Column>

        <Column header="" class="col-actions">
          <template #body="{ data }">
            <div class="actions">
              <Button
                v-tooltip.top="'Ver ficha'" icon="pi pi-eye" text rounded severity="secondary"
                :aria-label="`Ver ficha de ${data.name}`" @click.stop="goDetail(data.id)"
              />
              <Button
                v-if="auth.isAdmin" v-tooltip.top="'Editar'" icon="pi pi-pencil" text rounded severity="secondary"
                :aria-label="`Editar ${data.name}`" @click.stop="router.push({ name: 'athlete-edit', params: { id: data.id } })"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import StatusTag from '@/components/StatusTag.vue'
import PhoneLink from '@/components/PhoneLink.vue'
import { athletesApi } from '@/api/athletes'
import { catalogApi } from '@/api/catalog'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { debounce, finalFee, formatMoney, initials } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { notify } = useApiError()

const rows = ref([])
const total = ref(0)
const loading = ref(false)
const perPage = ref(20)
const first = ref(0)
const sort = reactive({ field: 'name', order: 1 })
const plans = ref([])

const filters = reactive({
  search: String(route.query.search || ''),
  status: route.query.status || 'ativo',
  birth_year: route.query.birth_year ? Number(route.query.birth_year) : null,
  plan_id: null,
  health: false
})

const statusOptions = [
  { value: 'ativo', label: 'Ativos' },
  { value: 'inativo', label: 'Inativos' },
  { value: 'trancado', label: 'Trancados' }
]

const year = new Date().getFullYear()
const categoryOptions = Array.from({ length: 16 }, (_, i) => {
  const birth = year - 4 - i
  return { value: birth, label: `Sub-${year - birth + 1} (${birth})` }
})

async function load() {
  loading.value = true
  try {
    const params = {
      page: Math.floor(first.value / perPage.value) + 1,
      per_page: perPage.value,
      search: filters.search || undefined,
      status: filters.status || undefined,
      birth_year: filters.birth_year || undefined,
      plan_id: filters.plan_id || undefined,
      health: filters.health ? 1 : undefined,
      sort: sort.field || undefined,
      order: sort.order === -1 ? 'desc' : 'asc'
    }
    const res = await athletesApi.list(params)
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

const reload = () => {
  first.value = 0
  load()
}
const debouncedReload = debounce(reload, 350)

watch(() => filters.search, debouncedReload)
watch(() => [filters.status, filters.birth_year, filters.plan_id, filters.health], reload)

function onPage(e) {
  first.value = e.first
  perPage.value = e.rows
  load()
}

function onSort(e) {
  sort.field = e.sortField || 'name'
  sort.order = e.sortOrder || 1
  load()
}

function goDetail(id) {
  router.push({ name: 'athlete-show', params: { id } })
}

function open(e) {
  goDetail(e.data.id)
}

onMounted(async () => {
  load()
  if (auth.isAdmin) {
    try {
      plans.value = await catalogApi.plans()
    } catch {
      /* filtro de plano opcional */
    }
  }
})
</script>

<style scoped>
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: .75rem;
  align-items: center;
  margin-bottom: 1rem;
  padding: 1rem;
}
.filters__search { flex: 1 1 280px; }
.filters > .p-select { min-width: 150px; }
.filters__toggle { display: flex; align-items: center; gap: .5rem; font-size: .85rem; }

.table-card { padding: 0; overflow: hidden; }
.athletes-table :deep(tr) { cursor: pointer; }

.athlete-cell { display: flex; gap: .75rem; align-items: center; }
.avatar-sm {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  display: grid; place-items: center; font-size: .75rem; font-weight: 700;
  background: var(--iba-black); color: var(--iba-gold-light); border: 2px solid var(--iba-gold);
}
.health { color: var(--iba-danger); margin-left: .4rem; font-size: .8rem; }
.positions { display: flex; gap: .25rem; margin-top: .2rem; flex-wrap: wrap; }
.pos {
  font-size: .65rem; font-weight: 700; letter-spacing: .03em; padding: .05rem .4rem; border-radius: 4px;
  background: color-mix(in srgb, var(--iba-blue) 14%, transparent); color: var(--iba-blue);
}
.small { font-size: .8rem; }
.actions { display: flex; justify-content: flex-end; }

.empty { text-align: center; padding: 2rem; color: var(--iba-text-muted); }
.empty i { font-size: 2rem; color: var(--iba-gold); }

@media (max-width: 960px) {
  .athletes-table :deep(.col-lg) { display: none; }
}
@media (max-width: 640px) {
  .athletes-table :deep(.col-md) { display: none; }
}
</style>
