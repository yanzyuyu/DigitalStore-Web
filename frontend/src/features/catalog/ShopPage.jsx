import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { fetchProducts, fetchShop } from './api'
import ProductCard from '../../components/ProductCard'
import { Empty, Spinner } from '../../components/ui'
import { apiError } from '../../lib/api'

export default function ShopPage() {
  const { slug } = useParams()
  const [shop, setShop] = useState(null)
  const [products, setProducts] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    setShop(null)
    fetchShop(slug)
      .then((found) => {
        setShop(found)
        return fetchProducts({ shop_id: found.id, per_page: 24 })
      })
      .then((page) => setProducts(page?.items ?? []))
      .catch((err) => setError(apiError(err, 'Toko tidak ditemukan.')))
  }, [slug])

  if (error) return <p className="text-sm text-red-700">{error}</p>
  if (!shop) return <Spinner />

  return (
    <div>
      <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted">Toko</p>
      <h1 className="mt-2 font-display text-4xl text-ink">{shop.name}</h1>
      {shop.description ? (
        <p className="mt-3 max-w-2xl text-sm leading-relaxed text-ink-soft">{shop.description}</p>
      ) : null}
      {products.length === 0 ? (
        <div className="mt-8">
          <Empty title="Toko ini belum menerbitkan produk" />
        </div>
      ) : (
        <div className="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}
    </div>
  )
}
