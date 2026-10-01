import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { fetchCategories, fetchProducts } from './api'
import ProductCard from '../../components/ProductCard'
import { Empty, Spinner } from '../../components/ui'
import { apiError } from '../../lib/api'

const sorts = [
  ['newest', 'Terbaru'],
  ['popular', 'Terlaris'],
  ['rating', 'Rating'],
  ['price_asc', 'Harga terendah'],
  ['price_desc', 'Harga tertinggi'],
]

export default function CatalogPage() {
  const [params, setParams] = useSearchParams()
  const [page, setPage] = useState(null)
  const [categories, setCategories] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  const search = params.get('q') || undefined
  const categoryId = params.get('kategori') || undefined
  const sort = params.get('urut') || 'newest'
  const pageNumber = params.get('hal') || '1'

  useEffect(() => {
    fetchCategories().then(setCategories).catch(() => setCategories([]))
  }, [])

  useEffect(() => {
    let active = true
    setLoading(true)
    fetchProducts({
      search,
      category_id: categoryId,
      sort,
      page: pageNumber,
      per_page: 12,
    })
      .then((result) => active && setPage(result))
      .catch((err) => active && setError(apiError(err, 'Katalog gagal dimuat.')))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [search, categoryId, sort, pageNumber])

  function update(key, value) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'hal') next.delete('hal')
    setParams(next)
  }

  return (
    <div>
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-display text-3xl text-ink">Katalog</h1>
          <p className="mt-1 text-sm text-muted">
            {page ? `${page.total} produk` : 'Memuat katalog'}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <select
            value={params.get('kategori') ?? ''}
            onChange={(event) => update('kategori', event.target.value)}
            className="rounded-sm border border-line bg-white px-3 py-2 text-sm"
          >
            <option value="">Semua kategori</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
          <select
            value={params.get('urut') ?? 'newest'}
            onChange={(event) => update('urut', event.target.value)}
            className="rounded-sm border border-line bg-white px-3 py-2 text-sm"
          >
            {sorts.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <form
        className="mt-5"
        onSubmit={(event) => {
          event.preventDefault()
          update('q', new FormData(event.currentTarget).get('q'))
        }}
      >
        <input
          name="q"
          defaultValue={params.get('q') ?? ''}
          placeholder="Cari produk"
          className="w-full max-w-sm rounded-sm border border-line bg-white px-3 py-2.5 text-sm outline-none focus:border-stone-400"
        />
      </form>

      {error ? <p className="mt-8 text-sm text-red-700">{error}</p> : null}
      {loading ? (
        <Spinner />
      ) : page?.items.length === 0 ? (
        <div className="mt-8">
          <Empty title="Tidak ada produk" body="Ubah kata kunci atau kategori." />
        </div>
      ) : (
        <div className="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
          {page?.items.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}

      {page && page.lastPage > 1 ? (
        <div className="mt-8 flex items-center justify-between text-sm">
          <button
            type="button"
            disabled={page.page <= 1}
            onClick={() => update('hal', String(page.page - 1))}
            className="rounded-sm border border-line px-3 py-2 disabled:opacity-40"
          >
            Sebelumnya
          </button>
          <span className="tabular-nums text-muted">
            {page.page} / {page.lastPage}
          </span>
          <button
            type="button"
            disabled={page.page >= page.lastPage}
            onClick={() => update('hal', String(page.page + 1))}
            className="rounded-sm border border-line px-3 py-2 disabled:opacity-40"
          >
            Berikutnya
          </button>
        </div>
      ) : null}
    </div>
  )
}
