import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <div className="py-16">
      <p className="text-xs uppercase tracking-[0.16em] text-muted">404</p>
      <h1 className="mt-2 font-display text-4xl text-ink">Halaman tidak ada</h1>
      <Link to="/" className="mt-6 inline-block text-sm text-ink underline">
        Kembali ke beranda
      </Link>
    </div>
  )
}
