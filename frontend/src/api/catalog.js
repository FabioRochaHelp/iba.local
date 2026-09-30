import { http } from './http'

let positionsCache = null

export const catalogApi = {
  async positions() {
    positionsCache ??= http.get('/positions').then((r) => r.data.data)
    return positionsCache
  },
  plans: (onlyActive = false) => http.get('/plans', { params: onlyActive ? { active: 1 } : {} }).then((r) => r.data.data)
}
