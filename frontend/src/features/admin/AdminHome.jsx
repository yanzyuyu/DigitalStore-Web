import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchDashboard } from './api'
import { money } from '../../lib/format'
import { apiError } from '../../lib/api'
import { Spinner } from '../../components/ui'

export default function AdminHome() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchDashboard()
      .then(setData)
      .catch((err) => setError(apiError(err, 'Ringkasan gagal dimuat.')))
  }, [])

  if (error) return <p className="text-sm text-red-700">{error}</p>
  if (!data) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Ringkasan</h1>
      <dl className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Pengguna" value={data.users?.total ?? 0} hint={`${data.users?.sellers ?? 0} penjual`} />
        <Stat label="Toko aktif" value={data.shops?.active ?? 0} hint={`${data.shops?.pending ?? 0} menunggu`} />
        <Stat label="Produk terbit" value={data.products?.published ?? 0} hint={`${data.products?.pending_review ?? 0} review`} />
        <Stat label="Komisi" value={money(data.revenue?.commission ?? 0)} hint={`Omzet ${money(data.revenue?.gross ?? 0)}`} />
      </dl>

      <h2 className="mt-10 text-sm font-medium text-ink">Antrian kerja</h2>
      <ul className="mt-3 divide-y divide-line border-y border-line">
        <Queue to="/admin/toko" label="Toko menunggu persetujuan" count={data.queues?.shops_awaiting_approval} />
        <Queue to="/admin/produk" label="Produk menunggu review" count={data.queues?.products_awaiting_review} />
        <Queue to="/admin/pembayaran" label="Bukti pembayaran menunggu verifikasi" count={data.queues?.payments_awaiting_verification} />
      </ul>
    </div>
  )
}

function Stat({ label, value, hint }) {
  return (
    <div className="rounded-sm border border-line bg-white p-4 shadow-sm">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-1 font-display text-2xl tabular-nums text-ink">{value}</dd>
      {hint ? <p className="mt-1 text-xs text-muted">{hint}</p> : null}
    </div>
  )
}

function Queue({ to, label, count }) {
  return (
    <li>
      <Link to={to} className="flex items-center justify-between gap-4 py-3 text-sm hover:text-accent">
        <span className="text-ink">{label}</span>
        <span className="tabular-nums text-ink-soft">{count ?? 0}</span>
      </Link>
    </li>
  )
}
