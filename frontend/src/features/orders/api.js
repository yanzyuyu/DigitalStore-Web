import { api, unwrap, unwrapPage } from '../../lib/api'

export async function fetchOrders(params) {
  const response = await api.get('/api/v1/orders', { params })
  return unwrapPage(response)
}

export async function fetchOrder(invoice) {
  const response = await api.get(`/api/v1/orders/${invoice}`)
  return unwrap(response)
}

export async function fetchInstructions(invoice) {
  const response = await api.get(`/api/v1/orders/${invoice}/payment-instructions`)
  return unwrap(response)
}

export async function uploadProof(invoice, file) {
  const body = new FormData()
  body.append('proof', file)
  const response = await api.post(`/api/v1/orders/${invoice}/payment/proof`, body)
  return unwrap(response)
}

export async function cancelOrder(invoice, reason) {
  const response = await api.post(`/api/v1/orders/${invoice}/cancel`, { reason })
  return unwrap(response)
}

export async function fetchDownloads() {
  const response = await api.get('/api/v1/downloads')
  return unwrapPage(response)
}
