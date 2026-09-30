import { http } from './http'

export const athletesApi = {
  list: (params) => http.get('/athletes', { params }).then((r) => r.data),
  get: (id) => http.get(`/athletes/${Number(id)}`).then((r) => r.data.data),
  create: (payload) => http.post('/athletes', payload).then((r) => r.data.data),
  update: (id, payload) => http.put(`/athletes/${Number(id)}`, payload).then((r) => r.data.data),
  remove: (id) => http.delete(`/athletes/${Number(id)}`)
}
