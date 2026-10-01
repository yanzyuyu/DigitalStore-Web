import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export const useCart = create(
  persist(
    (set, get) => ({
      items: [],

      add(product, quantity = 1) {
        const items = get().items
        const existing = items.find((item) => item.product_id === product.id)
        if (existing) {
          set({
            items: items.map((item) =>
              item.product_id === product.id
                ? { ...item, quantity: Math.min(item.quantity + quantity, 100) }
                : item,
            ),
          })
          return
        }
        set({
          items: [
            ...items,
            {
              product_id: product.id,
              name: product.name,
              slug: product.slug,
              price: Number(product.price),
              thumbnail_url: product.thumbnail_url,
              shop_name: product.shop?.name ?? '',
              quantity,
            },
          ],
        })
      },

      setQuantity(productId, quantity) {
        const next = Math.max(1, Math.min(100, quantity))
        set({
          items: get().items.map((item) =>
            item.product_id === productId ? { ...item, quantity: next } : item,
          ),
        })
      },

      remove(productId) {
        set({ items: get().items.filter((item) => item.product_id !== productId) })
      },

      clear() {
        set({ items: [] })
      },

      count: () => get().items.reduce((sum, item) => sum + item.quantity, 0),
      subtotal: () => get().items.reduce((sum, item) => sum + item.price * item.quantity, 0),
    }),
    { name: 'ds.cart' },
  ),
)
