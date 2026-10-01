import { api, unwrap, unwrapPage } from '../../lib/api'

export async function fetchMyShop() {
  const response = await api.get('/api/v1/seller/shop')
  return unwrap(response)
}

export async function updateShop(payload) {
  const response = await api.put('/api/v1/seller/shop', payload)
  return unwrap(response)
}

export async function fetchProduct(id) {
  const response = await api.get(`/api/v1/seller/products/${id}`)
  return unwrap(response)
}

export async function fetchMyProducts(params) {
  const response = await api.get('/api/v1/seller/products', { params })
  return unwrapPage(response)
}

export async function createProduct(payload) {
  const response = await api.post('/api/v1/seller/products', payload)
  return unwrap(response)
}

export async function updateProduct(id, payload) {
  const response = await api.put(`/api/v1/seller/products/${id}`, payload)
  return unwrap(response)
}

export async function deleteProduct(id) {
  await api.delete(`/api/v1/seller/products/${id}`)
}

export async function fetchProductFiles(productId) {
  const response = await api.get(`/api/v1/seller/products/${productId}/files`)
  return unwrap(response) ?? []
}

export async function uploadProductFile(productId, file, meta) {
  const body = new FormData()
  body.append('file', file)
  if (meta.name) body.append('name', meta.name)
  if (meta.version) body.append('version', meta.version)
  const response = await api.post(`/api/v1/seller/products/${productId}/files`, body, {
    timeout: 120000,
  })
  return unwrap(response)
}

export async function deleteProductFile(productId, fileId) {
  await api.delete(`/api/v1/seller/products/${productId}/files/${fileId}`)
}

export async function fetchSellerOrders(params) {
  const response = await api.get('/api/v1/seller/orders', { params })
  return unwrapPage(response)
}

export async function fetchSales(params) {
  const response = await api.get('/api/v1/seller/sales', { params })
  return response.data
}
