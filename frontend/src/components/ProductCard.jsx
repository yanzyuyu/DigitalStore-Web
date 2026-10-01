import { Link } from 'react-router-dom'
import { money } from '../lib/format'

export default function ProductCard({ product }) {
  return (
    <Link
      to={`/produk/${product.slug}`}
      className="group flex flex-col overflow-hidden rounded-sm border border-line bg-white shadow-sm transition hover:shadow-md"
    >
      <div className="aspect-[4/3] bg-paper-2">
        {product.thumbnail_url ? (
          <img
            src={product.thumbnail_url}
            alt=""
            className="h-full w-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full items-end p-4">
            <span className="font-display text-2xl leading-none text-stone-300">
              {product.name.slice(0, 1)}
            </span>
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1 p-4">
        <p className="text-xs text-muted">{product.shop?.name ?? product.category?.name}</p>
        <h3 className="line-clamp-2 text-sm font-medium text-ink group-hover:text-accent">
          {product.name}
        </h3>
        <p className="mt-auto pt-3 text-sm font-medium tabular-nums text-ink">
          {product.formatted_price ?? money(product.price)}
        </p>
      </div>
    </Link>
  )
}
