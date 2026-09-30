import { http } from './http'

export const dashboardApi = {
  summary: () => http.get('/dashboard').then((r) => r.data.data)
}
