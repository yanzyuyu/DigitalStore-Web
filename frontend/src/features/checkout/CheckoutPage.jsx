import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useCart } from '../../lib/cart'
import { useAuth } from '../../lib/auth'
import { api, apiError, fieldErrors, unwrap } from '../../lib/api'
import { money } from '../../lib/format'
import { Alert, Button, Empty, Field, inputClass } from '../../components/ui'

export default function CheckoutPage() {
  const items = useCart((s) => s.items)
  const clear = useCart((s) => s.clear)
  const user = useAuth((s) => s.user)
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  if (!user) {
    return (
      <Empty
        title="Masuk untuk checkout"
        body="Pesanan selalu terikat ke akun, supaya file bisa diunduh ulang."
        action={<Button as="link" to="/masuk" state={{ from: '/checkout' }} tone="accent">Masuk</Button>}
      />
    )
  }

  if (items.length === 0) {
    return (
      <Empty
        title="Tidak ada yang dibeli"
        action={<Button as="link" to="/produk" tone="accent">Lihat katalog</Button>}
      />
    )
  }

  async function onSubmit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setFields({})
    const form = new FormData(event.currentTarget)
    try {
      const response = await api.post('/api/v1/checkout', {
        items: items.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
        coupon_code: form.get('coupon_code') || null,
        customer_name: form.get('customer_name') || null,
        customer_phone: form.get('customer_phone') || null,
        notes: form.get('notes') || null,
      })
      const order = unwrap(response)
      clear()
      navigate(`/pesanan/${order.invoice_number}`, { replace: true })
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Checkout gagal.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr]">
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <h1 className="font-display text-3xl text-ink">Checkout</h1>
        <p className="text-sm text-muted">
          Dipesan sebagai {user.email}. <Link to="/akun" className="underline">Bukan Anda?</Link>
        </p>
        {error ? <Alert>{error}</Alert> : null}
        <Field label="Nama penerima" error={fields.customer_name} hint="Kosongkan untuk memakai nama akun">
          <input name="customer_name" defaultValue={user.name} className={inputClass} />
        </Field>
        <Field label="Telepon" error={fields.customer_phone}>
          <input name="customer_phone" defaultValue={user.phone ?? ''} inputMode="tel" className={inputClass} />
        </Field>
        <Field label="Kode kupon" error={fields.coupon_code}>
          <input name="coupon_code" className={inputClass} placeholder="Opsional" />
        </Field>
        <Field label="Catatan" error={fields.notes}>
          <textarea name="notes" rows={3} className={inputClass} />
        </Field>
        <Button type="submit" tone="accent" disabled={busy}>
          {busy ? 'Memproses…' : 'Buat pesanan'}
        </Button>
      </form>

      <aside className="h-fit rounded-sm border border-line bg-white p-5 shadow-sm">
        <h2 className="text-sm font-medium text-ink">Ringkasan</h2>
        <ul className="mt-3 space-y-2 text-sm">
          {items.map((item) => (
            <li key={item.product_id} className="flex justify-between gap-4">
              <span className="text-ink-soft">
                {item.name} <span className="text-muted">× {item.quantity}</span>
              </span>
              <span className="tabular-nums">{money(item.price * item.quantity)}</span>
            </li>
          ))}
        </ul>
        <p className="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-muted">
          Total akhir, diskon, dan komisi dihitung server. Harga di sini bisa berubah bila
          penjual mengubah harga sebelum pesanan dibuat.
        </p>
      </aside>
    </div>
  )
}
