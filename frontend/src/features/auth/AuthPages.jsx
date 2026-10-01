import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth, homeFor } from '../../lib/auth'
import { apiError, fieldErrors } from '../../lib/api'
import { Alert, Button, Field, inputClass } from '../../components/ui'

export function LoginPage() {
  const login = useAuth((s) => s.login)
  const navigate = useNavigate()
  const location = useLocation()
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  async function onSubmit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setFields({})
    const form = new FormData(event.currentTarget)
    try {
      const user = await login({
        email: form.get('email'),
        password: form.get('password'),
      })
      navigate(location.state?.from || homeFor(user), { replace: true })
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Gagal masuk.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthFrame title="Masuk" subtitle="Lanjutkan ke pesanan dan unduhan Anda.">
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error ? <Alert>{fields.email || error}</Alert> : null}
        <Field label="Email">
          <input name="email" type="email" required autoComplete="email" className={inputClass} />
        </Field>
        <Field label="Password">
          <input name="password" type="password" required autoComplete="current-password" className={inputClass} />
        </Field>
        <Button type="submit" tone="accent" className="w-full" disabled={busy}>
          {busy ? 'Memeriksa…' : 'Masuk'}
        </Button>
      </form>
      <p className="mt-6 text-sm text-muted">
        Belum punya akun? <Link to="/daftar" className="text-ink underline">Daftar</Link>
      </p>
    </AuthFrame>
  )
}

export function RegisterPage() {
  const register = useAuth((s) => s.register)
  const navigate = useNavigate()
  const [role, setRole] = useState('buyer')
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  async function onSubmit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setFields({})
    const form = new FormData(event.currentTarget)
    try {
      const user = await register({
        name: form.get('name'),
        email: form.get('email'),
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
        phone: form.get('phone') || null,
        role,
        shop_name: role === 'seller' ? form.get('shop_name') : null,
      })
      navigate(homeFor(user), { replace: true })
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Pendaftaran gagal.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthFrame title="Daftar" subtitle="Pembeli bisa langsung belanja. Penjual menunggu persetujuan toko.">
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        {error && Object.keys(fields).length === 0 ? <Alert>{error}</Alert> : null}
        <div className="grid grid-cols-2 gap-2">
          {[
            ['buyer', 'Pembeli'],
            ['seller', 'Penjual'],
          ].map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setRole(value)}
              className={`rounded-sm border px-3 py-2 text-sm ${
                role === value ? 'border-ink bg-ink text-paper' : 'border-line bg-white text-ink-soft'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
        <Field label="Nama" error={fields.name}>
          <input name="name" required minLength={3} autoComplete="name" className={inputClass} />
        </Field>
        <Field label="Email" error={fields.email}>
          <input name="email" type="email" required autoComplete="email" className={inputClass} />
        </Field>
        <Field label="Telepon" error={fields.phone} hint="Opsional">
          <input name="phone" inputMode="tel" autoComplete="tel" className={inputClass} />
        </Field>
        {role === 'seller' ? (
          <Field label="Nama toko" error={fields.shop_name}>
            <input name="shop_name" required minLength={3} className={inputClass} />
          </Field>
        ) : null}
        <Field label="Password" error={fields.password}>
          <input name="password" type="password" required minLength={8} autoComplete="new-password" className={inputClass} />
        </Field>
        <Field label="Ulangi password">
          <input name="password_confirmation" type="password" required autoComplete="new-password" className={inputClass} />
        </Field>
        <Button type="submit" tone="accent" className="w-full" disabled={busy}>
          {busy ? 'Mendaftarkan…' : 'Buat akun'}
        </Button>
      </form>
      <p className="mt-6 text-sm text-muted">
        Sudah punya akun? <Link to="/masuk" className="text-ink underline">Masuk</Link>
      </p>
    </AuthFrame>
  )
}

function AuthFrame({ title, subtitle, children }) {
  return (
    <div className="mx-auto max-w-md">
      <h1 className="font-display text-3xl text-ink">{title}</h1>
      <p className="mt-2 text-sm text-muted">{subtitle}</p>
      <div className="mt-8 rounded-sm border border-line bg-white p-6 shadow-sm">{children}</div>
    </div>
  )
}
