import { useEffect, useState } from 'react'
import { fetchSellerOrders } from './api'
import { money, dateTime } from '../../lib/format'
import { apiError } from '../../lib/api'
import { Empty, Spinner, StatusPill } from '../../components/ui'

export default function SellerOrders() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchSellerOrders({ per_page: 30 })
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Pesanan gagal dimuat.')))
  }, [])

  if (error) return <p className="text-sm text-red-700">{error}</p>
  if (!page) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Pesanan masuk</h1>
      <p className="mt-1 text-sm text-muted">
        Hanya item dari toko Anda. Item toko lain dalam pesanan yang sama tidak ditampilkan.
      </p>
      {page.items.length === 0 ? (
        <div className="mt-8"><Empty title="Belum ada pesanan" /></div>
      ) : (
        <ul className="mt-6 space-y-3">
          {page.items.map((order) => (
            <li key={order.invoice_number} className="rounded-sm border border-line bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium text-ink">{order.invoice_number}</p>
                <StatusPill status={order.status} label={order.status_label} />
              </div>
              <p className="text-xs text-muted">{dateTime(order.created_at)}</p>
              <ul className="mt-3 space-y-1 text-sm">
                {(order.items ?? []).map((item) => (
                  <li key={item.id} className="flex justify-between gap-4">
                    <span className="text-ink-soft">
                      {item.product_name} × {item.quantity}
                    </span>
                    <span className="tabular-nums">
                      {item.seller_earning != null ? money(item.seller_earning) : money(item.line_total)}
                    </span>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
