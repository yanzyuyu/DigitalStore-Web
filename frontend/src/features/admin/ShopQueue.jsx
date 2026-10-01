import { useEffect, useState } from 'react'
import { approveShop, fetchShops, suspendShop } from './api'
import { apiError } from '../../lib/api'
import { Empty, Spinner, StatusPill } from '../../components/ui'

const filters = [
  ['pending', 'Menunggu'],
  ['active', 'Aktif'],
  ['suspended', 'Ditangguhkan'],
]

export default function ShopQueue() {
  const [status, setStatus] = useState('pending')
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')
  const [busyId, setBusyId] = useState(null)
  const [revision, setRevision] = useState(0)

  useEffect(() => {
    let active = true
    setPage(null)
    fetchShops({ status, per_page: 30 })
      .then((result) => active && setPage(result))
      .catch((err) => active && setError(apiError(err, 'Daftar toko gagal dimuat.')))
    return () => {
      active = false
    }
  }, [status, revision])

  async function act(id, action) {
    setBusyId(id)
    setError('')
    try {
      await action(id)
      setRevision((value) => value + 1)
    } catch (err) {
      setError(apiError(err, 'Aksi gagal.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Toko</h1>
      <div className="mt-4 flex flex-wrap gap-2">
        {filters.map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => setStatus(value)}
            className={`rounded-sm px-3 py-1.5 text-sm ${status === value ? 'bg-ink text-paper' : 'text-ink-soft hover:bg-paper-2'}`}
          >
            {label}
          </button>
        ))}
      </div>
      {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}
      {!page ? <Spinner /> : page.items.length === 0 ? (
        <div className="mt-8"><Empty title="Tidak ada toko di antrian ini" /></div>
      ) : (
        <ul className="mt-6 divide-y divide-line border-y border-line">
          {page.items.map((shop) => (
            <li key={shop.id} className="flex flex-wrap items-center gap-3 py-3">
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-ink">{shop.name}</p>
                <p className="text-xs text-muted">
                  {shop.owner?.name ?? 'Pemilik'} · {shop.products_count ?? 0} produk
                </p>
              </div>
              <StatusPill status={shop.status} label={shop.status_label} />
              {shop.status !== 'active' ? (
                <button
                  type="button"
                  disabled={busyId === shop.id}
                  onClick={() => act(shop.id, approveShop)}
                  className="text-sm text-ink underline disabled:opacity-50"
                >
                  Setujui
                </button>
              ) : null}
              {shop.status !== 'suspended' ? (
                <button
                  type="button"
                  disabled={busyId === shop.id}
                  onClick={() => act(shop.id, suspendShop)}
                  className="text-sm text-red-700 disabled:opacity-50"
                >
                  Tangguhkan
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
