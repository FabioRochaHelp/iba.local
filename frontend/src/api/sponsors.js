import { http } from './http'

const id = (v) => Number(v)

export const sponsorsApi = {
  list: (search) => http.get('/sponsors', { params: search ? { search } : {} }).then((r) => r.data.data),
  create: (payload) => http.post('/sponsors', payload).then((r) => r.data.data),
  update: (sponsorId, payload) => http.put(`/sponsors/${id(sponsorId)}`, payload).then((r) => r.data.data),
  remove: (sponsorId) => http.delete(`/sponsors/${id(sponsorId)}`),
  entries: (params) => http.get('/sponsorships', { params }).then((r) => r.data),
  addEntry: (payload) => http.post('/sponsorships', payload).then((r) => r.data.data),
  removeEntry: (entryId, reason) => http.post(`/sponsorships/${id(entryId)}/delete`, { reason }),
  summary: (year) => http.get('/sponsorships/summary', { params: { year } }).then((r) => r.data.data)
}
