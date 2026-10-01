import { useEffect, useState } from 'react'
import { fetchProducts, setProductStatus } from './api'
import { money } from '../../lib/format'
import { apiError, fieldErrors } from '../../lib/api'
import { Alert, Empty, Spinner, StatusPill } from '../../components/ui'

const filters = [
  ['pending', 'Review'],
  ['published', 'Terbit'],
  ['rejected', 'Ditolak'],
  ['draft', 'Draf'],
]

export default function ProductModeration() {
  const [status, setStatus] = useState('pending')
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busyId, setBusyId] = useState(null)
  const [rejecting, setRejecting] = useState(null)
  const [revision, setRevision] = useState(0)

  useEffect(() => {
    let active = true
    setPage(null)
    fetchProducts({ status, per_page: 30 })
      .then((result) => active && setPage(result))
      .catch((err) => active && setError(apiError(err, 'Produk gagal dimuat.')))
    return () => {
      active = false
    }
  }, [status, revision])

  async function publish(product) {
    setBusyId(product.id)
    setError('')
    try {
      await setProductStatus(product.id, 'published')
      setRevision((value) => value + 1)
    } catch (err) {
      setError(apiError(err, 'Gagal menerbitkan.'))
    } finally {
      setBusyId(null)
    }
  }

  async function reject(event) {
    event.preventDefault()
    const reason = new FormData(event.currentTarget).get('reason')
    setBusyId(rejecting)
    setError('')
    setFields({})
    try {
      await setProductStatus(rejecting, 'rejected', reason)
      setRejecting(null)
      setRevision((value) => value + 1)
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Gagal menolak.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Moderasi produk</h1>
      <p className="mt-1 text-sm text-muted">Hanya admin yang bisa menerbitkan.</p>
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
      {error ? <div className="mt-4"><Alert>{error}</Alert></div> : null}
      {!page ? <Spinner /> : page.items.length === 0 ? (
        <div className="mt-8"><Empty title="Tidak ada produk di antrian ini" /></div>
      ) : (
        <ul className="mt-6 space-y-3">
          {page.items.map((product) => (
            <li key={product.id} className="rounded-sm border border-line bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-ink">{product.name}</p>
                  <p className="text-xs text-muted">
                    {product.shop?.name ?? 'Toko'} · <span className="tabular-nums">{money(product.price)}</span>
                  </p>
                </div>
                <StatusPill status={product.status} label={product.status_label} />
              </div>
              {product.short_description ? (
                <p className="mt-2 line-clamp-2 text-sm text-ink-soft">{product.short_description}</p>
              ) : null}
              <div className="mt-3 flex flex-wrap gap-3">
                {product.status !== 'published' ? (
                  <button
                    type="button"
                    disabled={busyId === product.id}
                    onClick={() => publish(product)}
                    className="text-sm text-ink underline disabled:opacity-50"
                  >
                    Terbitkan
                  </button>
                ) : null}
                {product.status !== 'rejected' ? (
                  <button
                    type="button"
                    onClick={() => setRejecting(rejecting === product.id ? null : product.id)}
                    className="text-sm text-red-700"
                  >
                    Tolak
                  </button>
                ) : null}
              </div>
              {rejecting === product.id ? (
                <form onSubmit={reject} className="mt-3 flex flex-wrap items-start gap-2">
                  <label className="min-w-0 flex-1">
                    <span className="sr-only">Alasan penolakan</span>
                    <input
                      name="reason"
                      required
                      minLength={5}
                      maxLength={500}
                      placeholder="Alasan untuk penjual"
                      className="w-full rounded-sm border border-line px-3 py-2 text-sm"
                    />
                    {fields.reason ? <span className="mt-1 block text-xs text-red-700">{fields.reason}</span> : null}
                  </label>
                  <button type="submit" disabled={busyId === product.id} className="rounded-sm bg-red-700 px-3 py-2 text-sm text-white disabled:opacity-50">
                    Kirim
                  </button>
                </form>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
