import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { cancelOrder, fetchInstructions, fetchOrder, uploadProof } from './api'
import { apiError } from '../../lib/api'
import { money, dateTime } from '../../lib/format'
import { Alert, Button, Spinner, StatusPill } from '../../components/ui'

export default function OrderDetailPage() {
  const { invoice } = useParams()
  const [order, setOrder] = useState(null)
  const [instructions, setInstructions] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    setOrder(null)
    setError('')
    fetchOrder(invoice)
      .then((found) => active && setOrder(found))
      .catch((err) => active && setError(apiError(err, 'Pesanan tidak ditemukan.')))
    return () => {
      active = false
    }
  }, [invoice])

  useEffect(() => {
    if (!order?.is_awaiting_payment) return
    fetchInstructions(invoice).then(setInstructions).catch(() => setInstructions(null))
  }, [order?.is_awaiting_payment, invoice])

  if (!order && error) return <p className="text-sm text-red-700">{error}</p>
  if (!order) return <Spinner />

  const bank = instructions?.instructions?.bank

  async function onProof(event) {
    const file = event.target.files?.[0]
    if (!file) return
    setBusy(true)
    setError('')
    try {
      await uploadProof(invoice, file)
      setNotice('Bukti terunggah. Menunggu verifikasi admin.')
      setOrder(await fetchOrder(invoice))
    } catch (err) {
      setError(apiError(err, 'Gagal mengunggah bukti.'))
    } finally {
      setBusy(false)
      event.target.value = ''
    }
  }

  async function onCancel() {
    const reason = window.prompt('Alasan pembatalan (opsional)') ?? ''
    setBusy(true)
    try {
      await cancelOrder(invoice, reason)
      setOrder(await fetchOrder(invoice))
    } catch (err) {
      setError(apiError(err, 'Gagal membatalkan.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[1.3fr_0.7fr]">
      <div>
        <p className="text-xs text-muted">{dateTime(order.created_at)}</p>
        <div className="mt-1 flex flex-wrap items-center gap-3">
          <h1 className="font-display text-3xl text-ink">{order.invoice_number}</h1>
          <StatusPill status={order.status} label={order.status_label} />
        </div>
        {notice ? <div className="mt-4"><Alert tone="ok">{notice}</Alert></div> : null}
        {error ? <div className="mt-4"><Alert>{error}</Alert></div> : null}

        <ul className="mt-6 divide-y divide-line border-y border-line">
          {(order.items ?? []).map((item) => (
            <li key={item.id} className="flex items-center justify-between gap-4 py-3 text-sm">
              <div>
                <Link to={`/produk/${item.product_slug}`} className="font-medium text-ink hover:text-accent">
                  {item.product_name}
                </Link>
                <p className="text-xs text-muted">
                  {item.shop?.name} · {item.quantity} × {money(item.unit_price)}
                </p>
              </div>
              <span className="tabular-nums">{money(item.line_total)}</span>
            </li>
          ))}
        </ul>

        {order.can_download ? (
          <p className="mt-6 text-sm text-ink-soft">
            Pembayaran diterima. File ada di{' '}
            <Link to="/akun/unduhan" className="underline">halaman unduhan</Link>.
          </p>
        ) : null}
      </div>

      <aside className="space-y-4">
        <section className="rounded-sm border border-line bg-white p-5 shadow-sm">
          <Row label="Subtotal" value={money(order.subtotal)} />
          {Number(order.discount_total) > 0 ? (
            <Row label="Diskon" value={`− ${money(order.discount_total)}`} />
          ) : null}
          <div className="mt-3 flex items-baseline justify-between border-t border-line pt-3">
            <span className="text-sm text-ink-soft">Total</span>
            <span className="font-display text-2xl tabular-nums">
              {order.formatted_grand_total ?? money(order.grand_total)}
            </span>
          </div>
        </section>

        {order.is_awaiting_payment ? (
          <section className="rounded-sm border border-line bg-white p-5 shadow-sm">
            <h2 className="text-sm font-medium text-ink">Pembayaran</h2>
            {bank ? (
              <dl className="mt-3 space-y-1 text-sm">
                <Row label="Bank" value={bank.name} />
                <Row label="No. rekening" value={bank.account_number} />
                <Row label="Atas nama" value={bank.account_holder} />
                <Row label="Referensi" value={instructions.reference} />
              </dl>
            ) : (
              <p className="mt-2 text-sm text-muted">Instruksi pembayaran belum tersedia.</p>
            )}
            <p className="mt-3 text-xs leading-relaxed text-muted">
              Transfer sesuai nominal, lalu unggah bukti. Admin yang menandai pesanan lunas.
            </p>
            <label className="mt-4 block">
              <span className="sr-only">Unggah bukti transfer</span>
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                disabled={busy}
                onChange={onProof}
                className="block w-full text-sm text-ink-soft file:mr-3 file:rounded-sm file:border-0 file:bg-ink file:px-3 file:py-2 file:text-sm file:text-paper"
              />
            </label>
            <Button tone="ghost" className="mt-3" onClick={onCancel} disabled={busy}>
              Batalkan pesanan
            </Button>
          </section>
        ) : null}
      </aside>
    </div>
  )
}

function Row({ label, value }) {
  return (
    <div className="flex items-baseline justify-between gap-4 py-0.5 text-sm">
      <span className="text-muted">{label}</span>
      <span className="text-right tabular-nums text-ink">{value}</span>
    </div>
  )
}
