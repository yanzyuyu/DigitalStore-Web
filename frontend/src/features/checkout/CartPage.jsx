import { Link } from 'react-router-dom'
import { useCart } from '../../lib/cart'
import { money } from '../../lib/format'
import { Button, Empty } from '../../components/ui'

export default function CartPage() {
  const items = useCart((s) => s.items)
  const setQuantity = useCart((s) => s.setQuantity)
  const remove = useCart((s) => s.remove)
  const subtotal = useCart((s) => s.subtotal())

  if (items.length === 0) {
    return (
      <Empty
        title="Keranjang kosong"
        body="Harga final dihitung server saat checkout, bukan dari halaman ini."
        action={<Button as="link" to="/produk" tone="accent">Lihat katalog</Button>}
      />
    )
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[1.4fr_0.6fr]">
      <div>
        <h1 className="font-display text-3xl text-ink">Keranjang</h1>
        <ul className="mt-6 divide-y divide-line border-y border-line">
          {items.map((item) => (
            <li key={item.product_id} className="flex flex-wrap items-center gap-4 py-4">
              <div className="min-w-0 flex-1">
                <Link to={`/produk/${item.slug}`} className="text-sm font-medium text-ink hover:text-accent">
                  {item.name}
                </Link>
                <p className="text-xs text-muted">{item.shop_name}</p>
              </div>
              <label className="text-xs text-muted">
                Jumlah
                <input
                  type="number"
                  min={1}
                  max={100}
                  value={item.quantity}
                  onChange={(event) => setQuantity(item.product_id, Number(event.target.value))}
                  className="ml-2 w-16 rounded-sm border border-line px-2 py-1 text-sm tabular-nums"
                />
              </label>
              <p className="w-28 text-right text-sm tabular-nums">{money(item.price * item.quantity)}</p>
              <button
                type="button"
                onClick={() => remove(item.product_id)}
                className="text-xs text-muted hover:text-red-700"
              >
                Hapus
              </button>
            </li>
          ))}
        </ul>
      </div>
      <aside className="h-fit rounded-sm border border-line bg-white p-5 shadow-sm">
        <p className="text-sm text-muted">Perkiraan subtotal</p>
        <p className="mt-1 font-display text-3xl tabular-nums text-ink">{money(subtotal)}</p>
        <p className="mt-2 text-xs leading-relaxed text-muted">
          Angka ini hanya perkiraan. Total yang ditagih dihitung ulang dari harga di database.
        </p>
        <Button as="link" to="/checkout" tone="accent" className="mt-5 w-full">
          Lanjut ke checkout
        </Button>
      </aside>
    </div>
  )
}
