import { api, unwrap, unwrapPage } from '../../lib/api'

export async function fetchDashboard() {
  const response = await api.get('/api/v1/admin/dashboard')
  return unwrap(response)
}

export async function fetchShops(params) {
  const response = await api.get('/api/v1/admin/shops', { params })
  return unwrapPage(response)
}

export async function approveShop(id) {
  const response = await api.post(`/api/v1/admin/shops/${id}/approve`)
  return unwrap(response)
}

export async function suspendShop(id) {
  const response = await api.post(`/api/v1/admin/shops/${id}/suspend`)
  return unwrap(response)
}

export async function fetchProducts(params) {
  const response = await api.get('/api/v1/admin/products', { params })
  return unwrapPage(response)
}

export async function setProductStatus(id, status, reason) {
  const response = await api.patch(`/api/v1/admin/products/${id}/status`, {
    status,
    reason: reason || null,
  })
  return unwrap(response)
}

export async function fetchPendingPayments(params) {
  const response = await api.get('/api/v1/admin/payments/pending', { params })
  return unwrapPage(response)
}

export async function verifyPayment(id, note) {
  const response = await api.post(`/api/v1/admin/payments/${id}/verify`, { note: note || null })
  return unwrap(response)
}

export async function rejectPayment(id, reason) {
  const response = await api.post(`/api/v1/admin/payments/${id}/reject`, { reason })
  return unwrap(response)
}
