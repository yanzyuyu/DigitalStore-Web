import { useEffect, useState } from 'react'
import { fetchPendingPayments, rejectPayment, verifyPayment } from './api'
import { money, dateTime } from '../../lib/format'
import { apiError, fieldErrors } from '../../lib/api'
import { Alert, Empty, Spinner } from '../../components/ui'

export default function PaymentQueue() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busyId, setBusyId] = useState(null)
  const [rejecting, setRejecting] = useState(null)

  function load() {
    fetchPendingPayments({ per_page: 30 })
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Antrian pembayaran gagal dimuat.')))
  }

  useEffect(load, [])

  async function verify(payment) {
    if (!window.confirm(`Verifikasi ${payment.reference}? Akses unduhan langsung terbuka.`)) return
    setBusyId(payment.id)
    setError('')
    try {
      await verifyPayment(payment.id)
      load()
    } catch (err) {
      setError(apiError(err, 'Verifikasi gagal.'))
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
      await rejectPayment(rejecting, reason)
      setRejecting(null)
      load()
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Penolakan gagal.'))
    } finally {
      setBusyId(null)
    }
  }

  if (!page && !error) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Verifikasi pembayaran</h1>
      <p className="mt-1 max-w-xl text-sm text-muted">
        Cek nominal dan rekening sebelum menyetujui. Penolakan membatalkan pesanan dan wajib beralasan.
      </p>
      {error ? <div className="mt-4"><Alert>{error}</Alert></div> : null}
      {page && page.items.length === 0 ? (
        <div className="mt-8"><Empty title="Tidak ada bukti yang menunggu" /></div>
      ) : (
        <ul className="mt-6 space-y-3">
          {(page?.items ?? []).map((payment) => (
            <li key={payment.id} className="rounded-sm border border-line bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-sm font-medium text-ink">{payment.reference}</p>
                <p className="tabular-nums text-sm text-ink">{money(payment.amount)}</p>
              </div>
              <p className="mt-1 text-xs text-muted">
                {payment.method_label} · {dateTime(payment.created_at)}
                {payment.has_proof ? ' · bukti terunggah' : ' · tanpa bukti'}
              </p>
              <div className="mt-3 flex flex-wrap gap-3">
                <button
                  type="button"
                  disabled={busyId === payment.id}
                  onClick={() => verify(payment)}
                  className="text-sm text-ink underline disabled:opacity-50"
                >
                  Verifikasi
                </button>
                <button
                  type="button"
                  onClick={() => setRejecting(rejecting === payment.id ? null : payment.id)}
                  className="text-sm text-red-700"
                >
                  Tolak
                </button>
              </div>
              {rejecting === payment.id ? (
                <form onSubmit={reject} className="mt-3 flex flex-wrap items-start gap-2">
                  <label className="min-w-0 flex-1">
                    <span className="sr-only">Alasan penolakan</span>
                    <input
                      name="reason"
                      required
                      minLength={5}
                      maxLength={500}
                      placeholder="Alasan, minimal 5 karakter"
                      className="w-full rounded-sm border border-line px-3 py-2 text-sm"
                    />
                    {fields.reason ? <span className="mt-1 block text-xs text-red-700">{fields.reason}</span> : null}
                  </label>
                  <button type="submit" disabled={busyId === payment.id} className="rounded-sm bg-red-700 px-3 py-2 text-sm text-white disabled:opacity-50">
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
