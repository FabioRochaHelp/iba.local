import { http } from './http'

export const athletesApi = {
  list: (params) => http.get('/athletes', { params }).then((r) => r.data),
  get: (id) => http.get(`/athletes/${Number(id)}`).then((r) => r.data.data),
  create: (payload) => http.post('/athletes', payload).then((r) => r.data.data),
  update: (id, payload) => http.put(`/athletes/${Number(id)}`, payload).then((r) => r.data.data),
  remove: (id) => http.delete(`/athletes/${Number(id)}`),
  // Foto: enviada como data URL (JSON, como toda escrita da API) e servida só para usuários logados.
  uploadPhoto: (id, image) => http.put(`/athletes/${Number(id)}/photo`, { image }).then((r) => r.data.data),
  removePhoto: (id) => http.delete(`/athletes/${Number(id)}/photo`),
  photoUrl: (id, version) => (version ? `/api/athletes/${Number(id)}/photo?v=${encodeURIComponent(version)}` : null)
}
