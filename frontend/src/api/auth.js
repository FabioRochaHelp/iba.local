import { http } from './http'

export const authApi = {
  csrf: () => http.get('/auth/csrf').then((r) => r.data.data),
  login: (email, password) => http.post('/auth/login', { email, password }).then((r) => r.data.data),
  me: () => http.get('/auth/me').then((r) => r.data.data),
  logout: () => http.post('/auth/logout').then((r) => r.data.data),
  changePassword: (payload) => http.put('/auth/password', payload).then((r) => r.data.data)
}
