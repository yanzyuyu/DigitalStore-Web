import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchProduct } from './api'
import { useCart } from '../../lib/cart'
import { money, plural } from '../../lib/format'
import { apiError } from '../../lib/api'
import { Button, Spinner } from '../../components/ui'
import Icon from '../../components/Icon'

export default function ProductPage() {
  const { slug } = useParams()
  const [product, setProduct] = useState(null)
  const [error, setError] = useState('')
  const [added, setAdded] = useState(false)
  const add = useCart((s) => s.add)
  const navigate = useNavigate()

  useEffect(() => {
    let active = true
    setProduct(null)
    setError('')
    fetchProduct(slug)
      .then((item) => active && setProduct(item))
      .catch((err) => active && setError(apiError(err, 'Produk tidak ditemukan.')))
    return () => {
      active = false
    }
  }, [slug])

  if (error) {
    return <p className="text-sm text-red-700">{error}</p>
  }
  if (!product) return <Spinner />

  const soldOut = product.status !== 'published'

  function buyNow() {
    add(product)
    navigate('/checkout')
  }

  return (
    <article className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr]">
      <div>
        <div className="aspect-[4/3] overflow-hidden rounded-sm border border-line bg-paper-2">
          {product.thumbnail_url ? (
            <img
              src={product.thumbnail_url}
              alt={product.name}
              fetchPriority="high"
              className="h-full w-full object-cover"
            />
          ) : null}
        </div>
        {product.images?.length > 0 ? (
          <div className="mt-3 grid grid-cols-5 gap-2">
            {product.images.map((image) => (
              <img
                key={image.id}
                src={image.url}
                alt={image.alt_text ?? ''}
                className="aspect-square rounded-sm border border-line object-cover"
              />
            ))}
          </div>
        ) : null}
      </div>

      <div>
        <p className="text-sm text-muted">
          {product.category?.name ? `${product.category.name} · ` : ''}
          <Link to={`/toko/${product.shop?.slug}`} className="hover:text-ink">
            {product.shop?.name}
          </Link>
        </p>
        <h1 className="mt-2 font-display text-3xl leading-tight text-ink sm:text-4xl">
          {product.name}
        </h1>
        <p className="mt-4 font-display text-3xl tabular-nums text-ink">
          {product.formatted_price ?? money(product.price)}
        </p>
        <p className="mt-1 text-xs text-muted">
          {plural(product.sales_count ?? 0, 'terjual', 'terjual')}
          {product.rating_count > 0
            ? ` · ${Number(product.rating_average).toFixed(1)} dari ${product.rating_count} ulasan`
            : ''}
        </p>

        <div className="mt-6 flex flex-wrap gap-2">
          <Button tone="accent" onClick={buyNow} disabled={soldOut}>
            Beli sekarang
            <Icon name="arrow" />
          </Button>
          <Button
            tone="ghost"
            disabled={soldOut}
            onClick={() => {
              add(product)
              setAdded(true)
            }}
          >
            <Icon name="cart" />
            {added ? 'Masuk keranjang' : 'Tambah ke keranjang'}
          </Button>
        </div>
        {soldOut ? (
          <p className="mt-3 text-sm text-muted">Produk ini belum tersedia untuk dibeli.</p>
        ) : null}

        {product.short_description ? (
          <p className="mt-8 text-sm leading-relaxed text-ink-soft">{product.short_description}</p>
        ) : null}
        {product.description ? (
          <div className="mt-4 whitespace-pre-line text-sm leading-relaxed text-ink-soft">
            {product.description}
          </div>
        ) : null}

        {product.reviews?.length > 0 ? (
          <section className="mt-10 border-t border-line pt-6">
            <h2 className="font-display text-xl text-ink">Ulasan</h2>
            <ul className="mt-4 space-y-4">
              {product.reviews.map((review) => (
                <li key={review.id} className="text-sm">
                  <p className="font-medium text-ink">
                    {review.user?.name ?? 'Pembeli'} · {review.rating}/5
                  </p>
                  {review.comment ? <p className="mt-1 text-ink-soft">{review.comment}</p> : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </article>
  )
}
