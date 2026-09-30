<template>
  <section :class="['change iba-card', { 'change--standalone': standalone }]">
    <h1>{{ standalone ? 'Defina sua senha' : 'Minha conta' }}</h1>
    <p class="iba-muted change__intro">
      <template v-if="standalone">Por segurança, troque a senha temporária antes de continuar.</template>
      <template v-else>{{ auth.user?.name }} · {{ auth.user?.email }}</template>
    </p>

    <form class="change__form" novalidate @submit.prevent="submit">
      <div class="field">
        <label for="current">Senha atual</label>
        <Password
          v-model="current" input-id="current" :feedback="false" toggle-mask autocomplete="current-password"
          :invalid="!!errors.current_password" fluid :input-props="{ maxlength: 200 }"
        />
        <small v-if="errors.current_password" class="field__error">{{ errors.current_password }}</small>
      </div>

      <div class="field">
        <label for="new">Nova senha</label>
        <Password
          v-model="next" input-id="new" toggle-mask autocomplete="new-password"
          :invalid="!!errors.new_password" fluid :input-props="{ maxlength: 200 }"
          prompt-label="Mínimo 10 caracteres, com letras e números"
          weak-label="Fraca" medium-label="Média" strong-label="Forte"
        />
        <small v-if="errors.new_password" class="field__error">{{ errors.new_password }}</small>
      </div>

      <div class="field">
        <label for="confirm">Confirme a nova senha</label>
        <Password
          v-model="confirm" input-id="confirm" :feedback="false" toggle-mask autocomplete="new-password"
          :invalid="!!errors.confirm" fluid :input-props="{ maxlength: 200 }"
        />
        <small v-if="errors.confirm" class="field__error">{{ errors.confirm }}</small>
      </div>

      <div class="change__actions">
        <Button v-if="standalone" type="button" label="Sair" severity="secondary" text @click="logout" />
        <Button type="submit" label="Salvar nova senha" icon="pi pi-check" :loading="loading" />
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Password from 'primevue/password'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const standalone = computed(() => route.name === 'change-password')
const current = ref('')
const next = ref('')
const confirm = ref('')
const errors = ref({})
const loading = ref(false)

function validateLocally() {
  const e = {}
  if (!current.value) e.current_password = 'Informe a senha atual.'
  if (next.value.length < 10 || !/[A-Za-z]/.test(next.value) || !/\d/.test(next.value)) {
    e.new_password = 'Mínimo de 10 caracteres, com letras e números.'
  }
  if (next.value !== confirm.value) e.confirm = 'As senhas não conferem.'
  errors.value = e
  return Object.keys(e).length === 0
}

async function submit() {
  if (!validateLocally()) return
  loading.value = true
  try {
    await auth.changePassword(current.value, next.value)
    current.value = next.value = confirm.value = ''
    toast.add({ severity: 'success', summary: 'Senha alterada', detail: 'Outras sessões abertas foram encerradas.', life: 4000 })
    if (standalone.value) router.replace({ name: auth.isPortal ? 'portal' : 'dashboard' })
  } catch (e) {
    errors.value = e.fields || {}
    if (!Object.keys(errors.value).length) {
      toast.add({ severity: 'error', summary: 'Erro', detail: e.userMessage, life: 5000 })
    }
  } finally {
    loading.value = false
  }
}

async function logout() {
  await auth.logout()
  router.replace({ name: 'login' })
}
</script>

<style scoped>
.change { width: 100%; max-width: 480px; }
.change--standalone { border-top: 4px solid var(--iba-gold); }
.change__intro { margin: .35rem 0 1.25rem; }
.change__form { display: flex; flex-direction: column; gap: 1rem; }
.change__actions { display: flex; justify-content: flex-end; gap: .5rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; }
.field__error { color: var(--iba-danger); }
</style>
