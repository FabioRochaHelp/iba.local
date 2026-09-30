import { http } from './http'

export const guardiansApi = {
  list: (params) => http.get('/guardians', { params }).then((r) => r.data),
  get: (id) => http.get(`/guardians/${Number(id)}`).then((r) => r.data.data),
  create: (payload) => http.post('/guardians', payload).then((r) => r.data.data),
  update: (id, payload) => http.put(`/guardians/${Number(id)}`, payload).then((r) => r.data.data),
  remove: (id) => http.delete(`/guardians/${Number(id)}`)
}
