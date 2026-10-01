import axios from 'axios'

const TOKEN_KEY = 'ds.token'

export const tokenStore = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  timeout: 20000,
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    if (status === 401 && tokenStore.get()) {
      tokenStore.clear()
      if (!window.location.pathname.startsWith('/masuk')) {
        window.location.assign('/masuk')
      }
    }
    return Promise.reject(error)
  },
)

export function apiError(error, fallback = 'Terjadi kesalahan. Coba lagi.') {
  const data = error?.response?.data
  if (!data) {
    if (error?.code === 'ECONNABORTED') return 'Permintaan terlalu lama. Periksa koneksi Anda.'
    if (!error?.response) return 'Tidak dapat terhubung ke server.'
    return fallback
  }
  if (typeof data === 'string') return data || fallback
  if (data instanceof Blob) return fallback
  return data.message || fallback
}

export async function downloadFile(url, filename) {
  try {
    const response = await api.get(url, { responseType: 'blob' })
    const type = response.headers?.['content-type'] || ''
    if (type.includes('application/json')) {
      const text = await response.data.text()
      let message = 'Unduhan ditolak.'
      try {
        message = JSON.parse(text).message || message
      } catch {
        message = text || message
      }
      const error = new Error(message)
      error.userMessage = message
      throw error
    }
    const objectUrl = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = filename || 'file'
    link.click()
    URL.revokeObjectURL(objectUrl)
  } catch (error) {
    if (error.response?.data instanceof Blob) {
      try {
        const text = await error.response.data.text()
        const parsed = JSON.parse(text)
        error.userMessage = parsed.message
      } catch {
        error.userMessage = 'Unduhan ditolak.'
      }
    }
    throw error
  }
}

export function fieldErrors(error) {
  const errors = error?.response?.data?.errors
  if (!errors || typeof errors !== 'object') return {}
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  )
}

export function unwrap(response) {
  return response.data?.data
}

export function unwrapPage(response) {
  const body = response.data ?? {}
  const meta = body.meta ?? {}
  return {
    items: Array.isArray(body.data) ? body.data : [],
    page: meta.current_page ?? 1,
    lastPage: meta.last_page ?? 1,
    total: meta.total ?? 0,
  }
}
