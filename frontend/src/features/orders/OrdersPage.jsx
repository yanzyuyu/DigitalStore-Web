import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchOrders } from './api'
import { money, dateTime } from '../../lib/format'
import { apiError } from '../../lib/api'
import { Empty, Spinner, StatusPill } from '../../components/ui'

export default function OrdersPage() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchOrders({ per_page: 20 })
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Pesanan gagal dimuat.')))
  }, [])

  if (error) return <p className="text-sm text-red-700">{error}</p>
  if (!page) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Pesanan saya</h1>
      {page.items.length === 0 ? (
        <div className="mt-8">
          <Empty title="Belum ada pesanan" action={null} />
        </div>
      ) : (
        <ul className="mt-6 divide-y divide-line border-y border-line">
          {page.items.map((order) => (
            <li key={order.invoice_number}>
              <Link
                to={`/pesanan/${order.invoice_number}`}
                className="flex flex-wrap items-center justify-between gap-3 py-4 hover:bg-paper-2"
              >
                <div>
                  <p className="font-medium text-ink">{order.invoice_number}</p>
                  <p className="text-xs text-muted">{dateTime(order.created_at)}</p>
                </div>
                <StatusPill status={order.status} label={order.status_label} />
                <p className="text-sm tabular-nums">{order.formatted_grand_total ?? money(order.grand_total)}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
