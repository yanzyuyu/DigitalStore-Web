import { Link } from 'react-router-dom'
import { useAuth } from '../../lib/auth'
import { Button } from '../../components/ui'

export default function AccountPage() {
  const user = useAuth((s) => s.user)

  return (
    <div className="max-w-xl">
      <h1 className="font-display text-3xl text-ink">{user?.name}</h1>
      <p className="mt-1 text-sm text-muted">{user?.email}</p>
      <p className="mt-1 text-xs uppercase tracking-[0.14em] text-muted">{user?.role_label ?? user?.role}</p>

      <ul className="mt-8 divide-y divide-line border-y border-line text-sm">
        <li>
          <Link to="/pesanan" className="block py-3 text-ink hover:text-accent">Pesanan</Link>
        </li>
        <li>
          <Link to="/akun/unduhan" className="block py-3 text-ink hover:text-accent">Unduhan</Link>
        </li>
        {user?.role === 'seller' ? (
          <li>
            <Link to="/seller" className="block py-3 text-ink hover:text-accent">Kelola toko</Link>
          </li>
        ) : null}
        {user?.role === 'admin' ? (
          <li>
            <Link to="/admin" className="block py-3 text-ink hover:text-accent">Panel admin</Link>
          </li>
        ) : null}
      </ul>

      {user?.role === 'buyer' ? (
        <p className="mt-6 text-sm text-ink-soft">
          Ingin menjual? Daftar akun penjual terpisah. Peran tidak bisa diubah sendiri.
        </p>
      ) : null}
      <Button as="link" to="/produk" tone="ghost" className="mt-6">
        Kembali ke katalog
      </Button>
    </div>
  )
}
