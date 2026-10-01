import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { fetchProducts } from './api'
import ProductCard from '../../components/ProductCard'
import { Button, Spinner } from '../../components/ui'
import Icon from '../../components/Icon'

export default function HomePage() {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const navigate = useNavigate()

  useEffect(() => {
    let active = true
    fetchProducts({ per_page: 8, sort: 'popular' })
      .then((page) => active && setProducts(page.items))
      .catch(() => active && setProducts([]))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  function onSearch(event) {
    event.preventDefault()
    const term = query.trim()
    navigate(term ? `/produk?q=${encodeURIComponent(term)}` : '/produk')
  }

  return (
    <div className="space-y-16">
      <section className="grid items-end gap-10 lg:grid-cols-[1.4fr_1fr]">
        <div>
          <h1 className="max-w-xl font-display text-4xl leading-[1.12] tracking-tight text-ink sm:text-6xl">
            Produk digital dari penjual yang Anda percaya.
          </h1>
          <p className="mt-5 max-w-lg text-base leading-relaxed text-ink-soft">
            Ebook, template, preset, dan aset digital. Bayar sekali, unduh selamanya —
            akses dicabut otomatis bila pesanan dikembalikan.
          </p>
          <form onSubmit={onSearch} className="mt-8 flex max-w-md gap-2">
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Cari ebook, template, preset…"
              className="w-full rounded-sm border border-line bg-white px-3 py-2.5 text-sm outline-none focus:border-stone-400"
            />
            <Button type="submit" tone="accent">
              <Icon name="search" />
              Cari
            </Button>
          </form>
        </div>
        <aside className="rounded-sm border border-line bg-white p-6 shadow-sm">
          <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted">Untuk penjual</p>
          <p className="mt-3 font-display text-2xl leading-snug text-ink">
            Buka toko, unggah file, biarkan platform yang menagih.
          </p>
          <p className="mt-3 text-sm leading-relaxed text-ink-soft">
            Komisi dipotong saat pesanan lunas. Saldo Anda tercatat terpisah dari
            pembeli lain.
          </p>
          <Button as="link" to="/daftar" tone="ink" className="mt-5">
            Daftar sebagai penjual
            <Icon name="arrow" />
          </Button>
        </aside>
      </section>

      <section>
        <div className="mb-6 flex items-end justify-between gap-4">
          <h2 className="font-display text-2xl text-ink">Paling banyak dibeli</h2>
          <Link to="/produk" className="text-sm text-ink-soft hover:text-ink">
            Lihat semua
          </Link>
        </div>
        {loading ? (
          <Spinner />
        ) : products.length === 0 ? (
          <p className="text-sm text-muted">Belum ada produk terbit. Katalog kosong.</p>
        ) : (
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
