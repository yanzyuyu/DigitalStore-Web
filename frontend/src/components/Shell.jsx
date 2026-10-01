import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth, homeFor } from '../lib/auth'
import { useCart } from '../lib/cart'
import Icon from './Icon'

const linkClass = ({ isActive }) =>
  `text-sm ${isActive ? 'text-ink' : 'text-ink-soft hover:text-ink'}`

export default function Shell() {
  const user = useAuth((s) => s.user)
  const logout = useAuth((s) => s.logout)
  const count = useCart((s) => s.items.reduce((sum, item) => sum + item.quantity, 0))
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/')
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur">
        <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-4 sm:px-6">
          <Link to="/" className="font-display text-xl tracking-tight text-ink">
            Digital Shop
          </Link>
          <nav className="hidden items-center gap-5 md:flex">
            <NavLink to="/produk" className={linkClass}>
              Katalog
            </NavLink>
            <NavLink to="/toko" className={linkClass}>
              Toko
            </NavLink>
          </nav>
          <div className="ml-auto flex items-center gap-1 sm:gap-2">
            <Link
              to="/keranjang"
              aria-label="Keranjang"
              className="relative rounded-sm p-2 text-ink-soft hover:bg-paper-2 hover:text-ink"
            >
              <Icon name="cart" className="h-5 w-5" />
              {count > 0 ? (
                <span className="absolute right-0.5 top-0.5 min-w-4 rounded-sm bg-accent px-1 text-center text-[10px] leading-4 text-white tabular-nums">
                  {count}
                </span>
              ) : null}
            </Link>
            {user ? (
              <>
                <Link
                  to={homeFor(user)}
                  className="hidden rounded-sm px-3 py-2 text-sm text-ink-soft hover:bg-paper-2 hover:text-ink sm:inline"
                >
                  {user.name.split(' ')[0]}
                </Link>
                <button
                  type="button"
                  onClick={onLogout}
                  className="rounded-sm px-3 py-2 text-sm text-ink-soft hover:bg-paper-2 hover:text-ink"
                >
                  Keluar
                </button>
              </>
            ) : (
              <Link
                to="/masuk"
                className="rounded-sm bg-ink px-3 py-2 text-sm font-medium text-paper hover:bg-stone-800"
              >
                Masuk
              </Link>
            )}
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-12">
        <Outlet />
      </main>
      <footer className="border-t border-line">
        <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs text-muted sm:px-6">
          <span>Digital Shop · pasar produk digital multi-vendor</span>
          <span className="flex gap-4">
            <Link to="/produk" className="hover:text-ink">Katalog</Link>
            <Link to="/daftar" className="hover:text-ink">Jual produk</Link>
          </span>
        </div>
      </footer>
    </div>
  )
}
