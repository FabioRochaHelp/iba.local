import { http } from './http'

const id = (v) => Number(v)

export const usersApi = {
  list: () => http.get('/users').then((r) => r.data.data),
  create: (payload) => http.post('/users', payload).then((r) => r.data.data),
  update: (userId, payload) => http.put(`/users/${id(userId)}`, payload).then((r) => r.data.data),
  resetPassword: (userId) => http.post(`/users/${id(userId)}/reset-password`).then((r) => r.data.data)
}

export const auditApi = {
  list: (params) => http.get('/audit-logs', { params }).then((r) => r.data)
}
