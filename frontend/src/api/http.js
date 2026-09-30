/**
 * Cliente HTTP da API.
 *
 * Segurança:
 *  - Autenticação por cookie de sessão HttpOnly (o JS nunca vê o token).
 *  - Token CSRF mantido só em memória e enviado no header X-CSRF-Token
 *    em toda requisição de escrita.
 *  - Em 419 (CSRF expirado) busca novo token e repete a requisição uma vez.
 */
import axios from 'axios'

const UNSAFE = ['post', 'put', 'patch', 'delete']

let csrfToken = null
let unauthorizedHandler = () => {}
let passwordChangeHandler = () => {}

export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  timeout: 20000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json'
  }
})

export function setCsrfToken(token) {
  csrfToken = token || null
}

export function onUnauthorized(handler) {
  unauthorizedHandler = handler
}

export function onPasswordChangeRequired(handler) {
  passwordChangeHandler = handler
}

export async function refreshCsrf() {
  const { data } = await http.get('/auth/csrf')
  setCsrfToken(data.data.csrf_token)
  return data.data
}

http.interceptors.request.use((config) => {
  if (UNSAFE.includes((config.method || 'get').toLowerCase()) && csrfToken) {
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error
    if (!response) {
      error.userMessage = 'Sem conexão com o servidor. Verifique sua internet.'
      return Promise.reject(error)
    }

    if (response.status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      await refreshCsrf()
      config.headers['X-CSRF-Token'] = csrfToken
      return http(config)
    }

    const isAuthCall = config?.url?.startsWith('/auth/login') || config?.url?.startsWith('/auth/csrf')
    if (response.status === 401 && !isAuthCall) {
      unauthorizedHandler()
    }
    if (response.status === 403 && /senha temporária/i.test(response.data?.error?.message || '')) {
      passwordChangeHandler()
    }

    error.userMessage = response.data?.error?.message || 'Não foi possível concluir a operação.'
    error.fields = response.data?.error?.fields || {}
    return Promise.reject(error)
  }
)
