<template>
  <section class="login iba-card">
    <h1 class="login__title">Área restrita</h1>
    <p class="login__subtitle">Acesse com seu e-mail e senha.</p>

    <Message v-if="route.query.expired" severity="warn" :closable="false" class="login__msg">
      Sua sessão expirou. Entre novamente.
    </Message>
    <Message v-if="error" severity="error" :closable="false" class="login__msg">{{ error }}</Message>

    <form class="login__form" novalidate @submit.prevent="submit">
      <div class="field">
        <label for="email">E-mail</label>
        <InputText
          id="email" v-model.trim="email" type="email" autocomplete="username" maxlength="190"
          :invalid="!!fieldErrors.email" required autofocus fluid
        />
        <small v-if="fieldErrors.email" class="field__error">{{ fieldErrors.email }}</small>
      </div>

      <div class="field">
        <label for="password">Senha</label>
        <Password
          v-model="password" input-id="password" :feedback="false" toggle-mask
          autocomplete="current-password" :invalid="!!fieldErrors.password" required fluid
          :input-props="{ maxlength: 200 }"
        />
        <small v-if="fieldErrors.password" class="field__error">{{ fieldErrors.password }}</small>
      </div>

      <Button type="submit" label="Entrar" icon="pi pi-sign-in" :loading="loading" fluid />
    </form>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'
import { useAuthStore } from '@/stores/auth'
import { safeRedirect } from '@/router'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({})

async function submit() {
  error.value = ''
  fieldErrors.value = {}
  if (!email.value || !password.value) {
    fieldErrors.value = {
      ...(email.value ? {} : { email: 'Informe o e-mail.' }),
      ...(password.value ? {} : { password: 'Informe a senha.' })
    }
    return
  }

  loading.value = true
  try {
    const user = await auth.login(email.value, password.value)
    password.value = ''
    router.replace(user.must_change_password ? { name: 'change-password' } : safeRedirect(route.query.redirect))
  } catch (e) {
    error.value = e.userMessage
    fieldErrors.value = e.fields || {}
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login {
  width: 100%;
  border-top: 4px solid var(--iba-gold);
}

.login__title { text-align: center; }

.login__subtitle {
  text-align: center;
  color: var(--iba-text-muted);
  margin: .35rem 0 1.25rem;
}

.login__msg { margin-bottom: 1rem; }

.login__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: .35rem;
}

.field label { font-weight: 600; }

.field__error { color: var(--iba-danger); }
</style>
