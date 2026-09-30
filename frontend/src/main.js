import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'

import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/montserrat/700.css'
import '@fontsource/montserrat/800.css'
import 'primeicons/primeicons.css'
import './theme/tokens.css'

import App from './App.vue'
import { router } from './router'
import { IbaPreset, darkModeSelector } from './theme/ibaPreset'
import { onPasswordChangeRequired, onUnauthorized } from './api/http'
import { useAuthStore } from './stores/auth'
import { ptBR } from './theme/locale-pt-br'
import { applyStoredTheme } from './composables/useTheme'

applyStoredTheme()

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(PrimeVue, {
  theme: { preset: IbaPreset, options: { darkModeSelector, cssLayer: false } },
  locale: ptBR,
  ripple: false
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)

const auth = useAuthStore(pinia)
onUnauthorized(() => {
  auth.reset()
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { expired: '1' } })
  }
})
onPasswordChangeRequired(() => router.push({ name: 'change-password' }))

app.mount('#app')
