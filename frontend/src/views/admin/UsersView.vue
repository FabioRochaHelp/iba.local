<template>
  <div>
    <div class="iba-page-header">
      <div>
        <h1>Usuários</h1>
        <p>Equipe (administradores e professores) e contas do portal (atletas e responsáveis, somente leitura).</p>
      </div>
      <Button label="Novo usuário" icon="pi pi-user-plus" @click="openForm()" />
    </div>

    <section class="iba-card table-card">
      <DataTable :value="users" :loading="loading" data-key="id">
        <Column header="Usuário">
          <template #body="{ data }">
            <strong :class="{ 'iba-muted': !data.active }">{{ data.name }}</strong>
            <span v-if="data.id === auth.user?.id" class="you">você</span>
            <div class="small iba-muted">{{ data.email }}</div>
          </template>
        </Column>
        <Column header="Perfil">
          <template #body="{ data }">
            <Tag :value="ROLE_LABELS[data.role]" :severity="roleSeverity[data.role]" rounded />
            <div v-if="data.link_name" class="small iba-muted link"><i class="pi pi-link" aria-hidden="true"></i> {{ data.link_name }}</div>
          </template>
        </Column>
        <Column header="Situação">
          <template #body="{ data }">
            <span v-if="!data.active" class="st st--off"><i class="pi pi-ban" aria-hidden="true"></i> Inativo</span>
            <span v-else-if="data.must_change_password" class="st st--pending"><i class="pi pi-key" aria-hidden="true"></i> Senha temporária</span>
            <span v-else class="st st--ok"><i class="pi pi-check-circle" aria-hidden="true"></i> Ativo</span>
          </template>
        </Column>
        <Column header="Último acesso" class="col-md">
          <template #body="{ data }">{{ data.last_login_at ? formatDateTime(data.last_login_at) : 'nunca' }}</template>
        </Column>
        <Column header="" class="col-actions">
          <template #body="{ data }">
            <div class="actions">
              <Button v-tooltip.top="'Editar'" icon="pi pi-pencil" text rounded severity="secondary" :aria-label="`Editar ${data.name}`" @click="openForm(data)" />
              <Button
                v-if="data.id !== auth.user?.id" v-tooltip.top="'Gerar nova senha temporária'" icon="pi pi-key" text rounded severity="secondary"
                :aria-label="`Resetar senha de ${data.name}`" @click="confirmReset(data)"
              />
              <Button
                v-if="data.id !== auth.user?.id" v-tooltip.top="data.active ? 'Desativar' : 'Reativar'" :icon="data.active ? 'pi pi-ban' : 'pi pi-replay'"
                text rounded :severity="data.active ? 'danger' : 'success'" :aria-label="`${data.active ? 'Desativar' : 'Reativar'} ${data.name}`"
                @click="toggle(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </section>

    <Dialog v-model:visible="dialog" :header="editing ? 'Editar usuário' : 'Novo usuário'" modal :style="{ width: '520px' }" :breakpoints="{ '560px': '95vw' }">
      <form id="user-form" class="uform" novalidate @submit.prevent="save">
        <div class="field">
          <label for="us-name">Nome *</label>
          <InputText id="us-name" v-model="form.name" maxlength="120" :invalid="!!errors.name" fluid />
          <small v-if="errors.name" class="field__error">{{ errors.name }}</small>
        </div>
        <div class="field">
          <label for="us-email">E-mail *</label>
          <InputText id="us-email" v-model="form.email" type="email" maxlength="190" autocomplete="off" :invalid="!!errors.email" fluid />
          <small v-if="errors.email" class="field__error">{{ errors.email }}</small>
        </div>
        <div class="field">
          <label id="us-role">Perfil *</label>
          <SelectButton
            v-model="form.role" :options="availableRoles" option-label="label" option-value="value" :allow-empty="false"
            :disabled="editing?.id === auth.user?.id || (editing && PORTAL_ROLES.includes(editing.role))" aria-labelledby="us-role"
          />
          <small class="iba-muted">{{ roleHelp[form.role] }}</small>
        </div>
        <div v-if="!editing && form.role === 'atleta'" class="field">
          <label for="us-athlete">Atleta desta conta *</label>
          <AthletePicker v-model="linkAthlete" input-id="us-athlete" :invalid="!!errors.athlete_id" />
          <small v-if="errors.athlete_id" class="field__error">{{ errors.athlete_id }}</small>
        </div>
        <div v-if="!editing && form.role === 'responsavel'" class="field">
          <label for="us-guardian">Responsável desta conta *</label>
          <AutoComplete
            v-model="linkGuardian" input-id="us-guardian" :suggestions="guardianSuggestions" option-label="name" :min-length="2" :delay="300"
            force-selection dropdown :invalid="!!errors.guardian_id" placeholder="Nome, telefone ou nome do atleta" fluid @complete="searchGuardians"
          >
            <template #option="{ option }">
              <div class="opt"><strong>{{ option.name }}</strong><small class="iba-muted">{{ option.athletes.map((a) => a.name).join(', ') || 'sem atletas' }}</small></div>
            </template>
          </AutoComplete>
          <small v-if="errors.guardian_id" class="field__error">{{ errors.guardian_id }}</small>
        </div>
        <p v-if="editing && PORTAL_ROLES.includes(editing.role)" class="iba-muted small">
          Vinculado a: <strong>{{ editing.link_name }}</strong>. Para trocar o vínculo, desative esta conta e crie outra.
        </p>
        <p v-if="!editing" class="iba-muted small">Uma senha temporária será gerada. O usuário deverá trocá-la no primeiro acesso.</p>
      </form>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialog = false" />
        <Button type="submit" form="user-form" label="Salvar" icon="pi pi-check" :loading="saving" />
      </template>
    </Dialog>

    <!-- Senha temporária: exibida uma única vez -->
    <Dialog v-model:visible="secretOpen" header="Senha temporária" modal :closable="false" :style="{ width: '440px' }" :breakpoints="{ '480px': '95vw' }">
      <p>Entregue ao usuário <strong>{{ secret.name }}</strong> ({{ secret.email }}) por um canal seguro:</p>
      <div class="secret">
        <code>{{ secret.password }}</code>
        <Button :icon="copied ? 'pi pi-check' : 'pi pi-copy'" :label="copied ? 'Copiado' : 'Copiar'" size="small" outlined @click="copy" />
      </div>
      <Message severity="warn" :closable="false">Esta senha não será exibida novamente. No primeiro acesso, o sistema exigirá a troca.</Message>
      <template #footer>
        <Button label="Anotei a senha" icon="pi pi-check" @click="closeSecret" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import AutoComplete from 'primevue/autocomplete'
import AthletePicker from '@/components/AthletePicker.vue'
import { usersApi } from '@/api/admin'
import { guardiansApi } from '@/api/guardians'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'
import { PORTAL_ROLES, ROLE_LABELS, STAFF_ROLES, formatDate } from '@/utils/format'

const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()
const { notify } = useApiError()

const users = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)
const errors = ref({})
const form = reactive({ name: '', email: '', role: 'professor' })
const roles = [
  { value: 'professor', label: 'Professor' },
  { value: 'admin', label: 'Administrador' },
  { value: 'responsavel', label: 'Responsável' },
  { value: 'atleta', label: 'Atleta' }
]
const roleSeverity = { admin: 'warn', professor: 'info', responsavel: 'secondary', atleta: 'success' }
const roleHelp = {
  admin: 'Acesso total ao sistema.',
  professor: 'Atletas (sem valores), turmas, chamada e evolução dos atletas das suas turmas.',
  responsavel: 'Somente leitura: evolução, frequência e turmas de todos os filhos vinculados.',
  atleta: 'Somente leitura: a própria evolução, frequência e turmas.'
}
// Contas do portal não mudam de perfil; contas da equipe só alternam entre admin e professor.
const availableRoles = computed(() => (editing.value ? roles.filter((r) => PORTAL_ROLES.includes(editing.value.role) ? r.value === editing.value.role : STAFF_ROLES.includes(r.value)) : roles))
const linkAthlete = ref(null)
const linkGuardian = ref(null)
const guardianSuggestions = ref([])

async function searchGuardians(e) {
  try {
    guardianSuggestions.value = (await guardiansApi.list({ search: e.query, per_page: 10 })).data
  } catch (err) {
    notify(err)
  }
}
const secretOpen = ref(false)
const secret = reactive({ name: '', email: '', password: '' })
const copied = ref(false)

const formatDateTime = (v) => `${formatDate(v)} ${String(v).slice(11, 16)}`

async function load() {
  loading.value = true
  try {
    users.value = await usersApi.list()
  } catch (e) {
    notify(e)
  } finally {
    loading.value = false
  }
}

function openForm(u = null) {
  editing.value = u
  errors.value = {}
  Object.assign(form, u ? { name: u.name, email: u.email, role: u.role } : { name: '', email: '', role: 'professor' })
  linkAthlete.value = null
  linkGuardian.value = null
  dialog.value = true
}

function showSecret(user, password) {
  Object.assign(secret, { name: user.name, email: user.email, password })
  copied.value = false
  secretOpen.value = true
}

function closeSecret() {
  secret.password = ''
  secretOpen.value = false
}

async function copy() {
  try {
    await navigator.clipboard.writeText(secret.password)
    copied.value = true
  } catch {
    toast.add({ severity: 'info', summary: 'Copie manualmente', detail: 'A área de transferência não está disponível.', life: 3000 })
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (editing.value) {
      const payload = { name: form.name, email: form.email }
      if (editing.value.id !== auth.user?.id && STAFF_ROLES.includes(editing.value.role)) payload.role = form.role
      await usersApi.update(editing.value.id, payload)
      toast.add({ severity: 'success', summary: 'Usuário atualizado', life: 3000 })
    } else {
      const res = await usersApi.create({
        ...form,
        athlete_id: form.role === 'atleta' ? linkAthlete.value?.id || null : null,
        guardian_id: form.role === 'responsavel' ? linkGuardian.value?.id || null : null
      })
      showSecret(res.user, res.temporary_password)
    }
    dialog.value = false
    load()
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) notify(e)
  } finally {
    saving.value = false
  }
}

function confirmReset(u) {
  confirm.require({
    header: 'Gerar nova senha',
    message: `Gerar uma nova senha temporária para ${u.name}? A senha atual deixa de funcionar e as sessões abertas serão encerradas.`,
    icon: 'pi pi-key',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: 'Gerar senha' },
    accept: async () => {
      try {
        const res = await usersApi.resetPassword(u.id)
        showSecret(u, res.temporary_password)
        load()
      } catch (e) {
        notify(e)
      }
    }
  })
}

function toggle(u) {
  const deactivate = u.active
  confirm.require({
    header: deactivate ? 'Desativar usuário' : 'Reativar usuário',
    message: deactivate ? `${u.name} perderá o acesso imediatamente (sessões abertas serão encerradas).` : `Reativar o acesso de ${u.name}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Cancelar', severity: 'secondary', text: true },
    acceptProps: { label: deactivate ? 'Desativar' : 'Reativar', severity: deactivate ? 'danger' : 'success' },
    accept: async () => {
      try {
        await usersApi.update(u.id, { active: !u.active })
        load()
      } catch (e) {
        notify(e)
      }
    }
  })
}

onMounted(load)
</script>

<style scoped>
.table-card { padding: 0; overflow: hidden; }
.small { font-size: .8rem; }
.you { margin-left: .4rem; font-size: .68rem; font-weight: 700; padding: .05rem .4rem; border-radius: 999px; background: var(--iba-gold); color: #111; }
.st { display: inline-flex; gap: .35rem; align-items: center; font-size: .85rem; font-weight: 500; }
.st--ok { color: var(--iba-success); }
.st--pending { color: var(--iba-gold-text); }
.st--off { color: var(--iba-text-muted); }
.actions { display: flex; justify-content: flex-end; }
.uform { display: grid; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; font-size: .85rem; }
.field__error { color: var(--iba-danger); }
.link { margin-top: .2rem; }
.opt { display: flex; flex-direction: column; line-height: 1.3; }
.secret { display: flex; gap: .75rem; align-items: center; justify-content: space-between; padding: .75rem 1rem; margin: 1rem 0; border-radius: 10px; background: var(--iba-black); }
.secret code { font-size: 1.3rem; letter-spacing: .08em; color: var(--iba-gold-light); font-weight: 700; user-select: all; }
@media (max-width: 640px) { :deep(.col-md) { display: none; } }
</style>
