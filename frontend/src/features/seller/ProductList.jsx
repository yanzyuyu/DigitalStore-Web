import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { deleteProduct, fetchMyProducts } from './api'
import { money } from '../../lib/format'
import { apiError } from '../../lib/api'
import { Button, Empty, Spinner, StatusPill } from '../../components/ui'

export default function ProductList() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')

  function load() {
    fetchMyProducts({ per_page: 50 })
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Produk gagal dimuat.')))
  }

  useEffect(load, [])

  async function onDelete(product) {
    if (!window.confirm(`Hapus "${product.name}"? Pesanan lama tetap bisa diunduh.`)) return
    try {
      await deleteProduct(product.id)
      load()
    } catch (err) {
      setError(apiError(err, 'Gagal menghapus.'))
    }
  }

  if (error && !page) return <p className="text-sm text-red-700">{error}</p>
  if (!page) return <Spinner />

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-3xl text-ink">Produk</h1>
        <Button as="link" to="/seller/produk/baru" tone="accent">Produk baru</Button>
      </div>
      {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}
      {page.items.length === 0 ? (
        <div className="mt-8">
          <Empty
            title="Belum ada produk"
            body="Produk baru berstatus draf. Admin yang menerbitkan."
          />
        </div>
      ) : (
        <ul className="mt-6 divide-y divide-line border-y border-line">
          {page.items.map((product) => (
            <li key={product.id} className="flex flex-wrap items-center gap-3 py-3">
              <div className="min-w-0 flex-1">
                <Link to={`/seller/produk/${product.id}`} className="text-sm font-medium text-ink hover:text-accent">
                  {product.name}
                </Link>
                <p className="text-xs tabular-nums text-muted">{money(product.price)}</p>
              </div>
              <StatusPill status={product.status} label={product.status_label} />
              <button
                type="button"
                onClick={() => onDelete(product)}
                className="text-xs text-muted hover:text-red-700"
              >
                Hapus
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
