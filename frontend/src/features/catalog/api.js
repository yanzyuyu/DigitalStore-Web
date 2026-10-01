import { api, unwrap, unwrapPage } from '../../lib/api'

export async function fetchProducts(params) {
  const response = await api.get('/api/v1/products', { params })
  return unwrapPage(response)
}

export async function fetchProduct(slug) {
  const response = await api.get(`/api/v1/products/${slug}`)
  return unwrap(response)
}

export async function fetchCategories() {
  const response = await api.get('/api/v1/categories')
  return unwrap(response) ?? []
}

export async function fetchShops(params) {
  const response = await api.get('/api/v1/shops', { params })
  return unwrapPage(response)
}

export async function fetchShop(slug) {
  const response = await api.get(`/api/v1/shops/${slug}`)
  return unwrap(response)
}
