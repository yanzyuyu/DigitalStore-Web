import { create } from 'zustand'
import { api, tokenStore, unwrap } from './api'

export const useAuth = create((set, get) => ({
  user: null,
  ready: false,

  setUser: (user) => set({ user, ready: true }),

  async bootstrap() {
    if (!tokenStore.get()) {
      set({ user: null, ready: true })
      return
    }
    try {
      const { data } = await api.get('/api/v1/auth/me')
      set({ user: unwrap({ data }), ready: true })
    } catch {
      tokenStore.clear()
      set({ user: null, ready: true })
    }
  },

  async login(credentials) {
    const { data } = await api.post('/api/v1/auth/login', credentials)
    const session = unwrap({ data })
    tokenStore.set(session.token)
    set({ user: session.user, ready: true })
    return session.user
  },

  async register(payload) {
    const { data } = await api.post('/api/v1/auth/register', payload)
    const session = unwrap({ data })
    tokenStore.set(session.token)
    set({ user: session.user, ready: true })
    return session.user
  },

  async logout() {
    try {
      await api.post('/api/v1/auth/logout')
    } catch {
      // Token may already be dead; local state still has to clear.
    }
    tokenStore.clear()
    set({ user: null })
  },

  async refresh() {
    if (!tokenStore.get()) return null
    const { data } = await api.get('/api/v1/auth/me')
    const user = unwrap({ data })
    set({ user })
    return user
  },

  isRole: (role) => get().user?.role === role,
}))

export function homeFor(user) {
  if (!user) return '/'
  if (user.role === 'admin') return '/admin'
  if (user.role === 'seller') return '/seller'
  return '/akun'
}
