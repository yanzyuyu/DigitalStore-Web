import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchShops } from './api'
import { Empty, Spinner } from '../../components/ui'
import { apiError } from '../../lib/api'

export default function ShopsPage() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchShops({ per_page: 24 })
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Daftar toko gagal dimuat.')))
  }, [])

  if (error) return <p className="text-sm text-red-700">{error}</p>
  if (!page) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Toko</h1>
      <p className="mt-1 text-sm text-muted">{page.total} toko aktif</p>
      {page.items.length === 0 ? (
        <div className="mt-8">
          <Empty title="Belum ada toko" body="Toko muncul di sini setelah disetujui admin." />
        </div>
      ) : (
        <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {page.items.map((shop) => (
            <li key={shop.id}>
              <Link
                to={`/toko/${shop.slug}`}
                className="block rounded-sm border border-line bg-white p-5 shadow-sm transition hover:shadow-md"
              >
                <p className="font-display text-xl text-ink">{shop.name}</p>
                <p className="mt-2 line-clamp-2 text-sm text-ink-soft">
                  {shop.description || 'Belum ada deskripsi.'}
                </p>
                {shop.products_count != null ? (
                  <p className="mt-3 text-xs text-muted">{shop.products_count} produk</p>
                ) : null}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
