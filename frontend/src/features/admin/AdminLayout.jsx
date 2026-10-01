import { NavLink, Outlet } from 'react-router-dom'

const item = ({ isActive }) =>
  `rounded-sm px-3 py-2 text-sm ${isActive ? 'bg-ink text-paper' : 'text-ink-soft hover:bg-paper-2'}`

export default function AdminLayout() {
  return (
    <div className="grid gap-8 lg:grid-cols-[200px_1fr]">
      <aside>
        <p className="px-3 text-xs font-medium uppercase tracking-[0.16em] text-muted">Admin</p>
        <nav className="mt-4 flex flex-row flex-wrap gap-1 lg:flex-col">
          <NavLink to="/admin" end className={item}>Ringkasan</NavLink>
          <NavLink to="/admin/toko" className={item}>Toko</NavLink>
          <NavLink to="/admin/produk" className={item}>Produk</NavLink>
          <NavLink to="/admin/pembayaran" className={item}>Pembayaran</NavLink>
        </nav>
      </aside>
      <div>
        <Outlet />
      </div>
    </div>
  )
}
