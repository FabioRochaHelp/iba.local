import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'
import { setCsrfToken } from '@/api/http'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    initialized: false
  }),

  getters: {
    isAuthenticated: (s) => s.user !== null,
    isAdmin: (s) => s.user?.role === 'admin',
    isStaff: (s) => ['admin', 'professor'].includes(s.user?.role),
    isPortal: (s) => ['atleta', 'responsavel'].includes(s.user?.role),
    mustChangePassword: (s) => Boolean(s.user?.must_change_password),
    hasRole: (s) => (roles) => !roles?.length || roles.includes(s.user?.role)
  },

  actions: {
    /** Busca o token CSRF e, se houver sessão válida, o usuário logado. */
    async init() {
      if (this.initialized) return
      try {
        const data = await authApi.csrf()
        setCsrfToken(data.csrf_token)
        this.user = data.user
      } catch {
        this.user = null
      } finally {
        this.initialized = true
      }
    },

    async login(email, password) {
      const data = await authApi.login(email, password)
      setCsrfToken(data.csrf_token)
      this.user = data.user
      return data.user
    },

    async logout() {
      try {
        const data = await authApi.logout()
        setCsrfToken(data.csrf_token)
      } finally {
        this.user = null
      }
    },

    async changePassword(currentPassword, newPassword) {
      const data = await authApi.changePassword({
        current_password: currentPassword,
        new_password: newPassword
      })
      setCsrfToken(data.csrf_token)
      this.user = { ...this.user, must_change_password: false }
    },

    /** Sessão expirou no servidor. */
    reset() {
      this.user = null
    }
  }
})
