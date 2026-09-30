import { http } from './http'

const id = (v) => Number(v)

export const uniformsApi = {
  items: (onlyActive = false) => http.get('/uniform-items', { params: onlyActive ? { active: 1 } : {} }).then((r) => r.data.data),
  createItem: (payload) => http.post('/uniform-items', payload).then((r) => r.data.data),
  updateItem: (itemId, payload) => http.put(`/uniform-items/${id(itemId)}`, payload).then((r) => r.data.data),
  orders: (params) => http.get('/uniform-orders', { params }).then((r) => r.data),
  order: (orderId) => http.get(`/uniform-orders/${id(orderId)}`).then((r) => r.data.data),
  createOrder: (payload) => http.post('/uniform-orders', payload).then((r) => r.data.data),
  pay: (orderId, payload) => http.post(`/uniform-orders/${id(orderId)}/payments`, payload).then((r) => r.data.data),
  deliver: (orderId, delivered) => http.put(`/uniform-orders/${id(orderId)}/delivery`, { delivered }).then((r) => r.data.data),
  cancel: (orderId, reason) => http.post(`/uniform-orders/${id(orderId)}/cancel`, { reason }).then((r) => r.data.data)
}
