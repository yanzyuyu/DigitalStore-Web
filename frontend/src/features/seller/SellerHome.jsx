import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchSales } from './api'
import { useAuth } from '../../lib/auth'
import { money } from '../../lib/format'
import { apiError } from '../../lib/api'
import { StatusPill } from '../../components/ui'

export default function SellerHome() {
  const shop = useAuth((s) => s.user?.shop)
  const [sales, setSales] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchSales({ per_page: 5 })
      .then(setSales)
      .catch((err) => setError(apiError(err, 'Ringkasan gagal dimuat.')))
  }, [])

  const summary = sales?.meta?.summary

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-display text-3xl text-ink">{shop?.name ?? 'Toko saya'}</h1>
        {shop ? <StatusPill status={shop.status} label={shop.status_label} /> : null}
      </div>
      {shop && !shop.is_operational ? (
        <p className="mt-3 max-w-xl text-sm text-ink-soft">
          Toko belum aktif. Produk baru bisa dibuat setelah admin menyetujui toko.
        </p>
      ) : null}
      {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}

      <dl className="mt-8 grid gap-4 sm:grid-cols-3">
        <Stat label="Item terjual" value={summary?.items_sold ?? '—'} />
        <Stat label="Omzet" value={summary ? money(summary.gross) : '—'} />
        <Stat label="Pendapatan bersih" value={summary ? money(summary.net) : '—'} />
      </dl>

      <section className="mt-8 rounded-sm border border-line bg-white p-5 shadow-sm">
        <h2 className="text-sm font-medium text-ink">Saldo</h2>
        <dl className="mt-3 grid gap-3 sm:grid-cols-2">
          <Stat label="Saldo tertunda" value={money(shop?.pending_balance ?? 0)} plain />
          <Stat label="Saldo tersedia" value={money(shop?.balance ?? 0)} plain />
        </dl>
        <p className="mt-3 text-xs text-muted">
          Saldo tertunda bertambah saat pembayaran diverifikasi. Penarikan dana belum dibuka.
        </p>
      </section>

      <Link to="/seller/produk/baru" className="mt-6 inline-block text-sm text-ink underline">
        Tambah produk
      </Link>
    </div>
  )
}

function Stat({ label, value, plain = false }) {
  return (
    <div className={plain ? '' : 'rounded-sm border border-line bg-white p-4 shadow-sm'}>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-1 font-display text-2xl tabular-nums text-ink">{value}</dd>
    </div>
  )
}
