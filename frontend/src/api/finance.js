import { http } from './http'

const id = (v) => Number(v)

export const plansApi = {
  list: () => http.get('/plans').then((r) => r.data.data),
  create: (payload) => http.post('/plans', payload).then((r) => r.data.data),
  update: (planId, payload) => http.put(`/plans/${id(planId)}`, payload).then((r) => r.data.data)
}

export const invoicesApi = {
  list: (params) => http.get('/invoices', { params }).then((r) => r.data),
  get: (invoiceId) => http.get(`/invoices/${id(invoiceId)}`).then((r) => r.data.data),
  forAthlete: (athleteId) => http.get(`/athletes/${id(athleteId)}/invoices`).then((r) => r.data.data),
  generate: (month) => http.post('/invoices/generate', { month }).then((r) => r.data.data),
  update: (invoiceId, payload) => http.put(`/invoices/${id(invoiceId)}`, payload).then((r) => r.data.data),
  cancel: (invoiceId, reason) => http.post(`/invoices/${id(invoiceId)}/cancel`, { reason }).then((r) => r.data.data),
  pay: (invoiceId, payload) => http.post(`/invoices/${id(invoiceId)}/payments`, payload).then((r) => r.data.data),
  reversePayment: (paymentId, reason) => http.post(`/payments/${id(paymentId)}/reverse`, { reason })
}

export const reportsApi = {
  delinquency: () => http.get('/reports/delinquency').then((r) => r.data.data),
  monthly: (month) => http.get('/reports/monthly', { params: { month } }).then((r) => r.data.data)
}
