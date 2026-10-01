import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../../lib/auth'

const item = ({ isActive }) =>
  `rounded-sm px-3 py-2 text-sm ${isActive ? 'bg-ink text-paper' : 'text-ink-soft hover:bg-paper-2'}`

export default function SellerLayout() {
  const shop = useAuth((s) => s.user?.shop)

  return (
    <div className="grid gap-8 lg:grid-cols-[200px_1fr]">
      <aside>
        <p className="px-3 text-xs font-medium uppercase tracking-[0.16em] text-muted">Penjual</p>
        <p className="mt-1 truncate px-3 text-sm text-ink">{shop?.name ?? 'Toko'}</p>
        <nav className="mt-4 flex flex-row flex-wrap gap-1 lg:flex-col">
          <NavLink to="/seller" end className={item}>Ringkasan</NavLink>
          <NavLink to="/seller/produk" className={item}>Produk</NavLink>
          <NavLink to="/seller/pesanan" className={item}>Pesanan</NavLink>
          <NavLink to="/seller/toko" className={item}>Pengaturan toko</NavLink>
        </nav>
      </aside>
      <div>
        <Outlet />
      </div>
    </div>
  )
}
