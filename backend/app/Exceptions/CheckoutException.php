<?php

declare(strict_types=1);

namespace App\Exceptions;

class CheckoutException extends DomainException
{
    public static function emptyCart(): self
    {
        return new self('Keranjang tidak boleh kosong.', 'cart_empty');
    }

    public static function productNotPurchasable(string $name): self
    {
        return new self(
            sprintf('Produk "%s" sedang tidak tersedia untuk dibeli.', $name),
            'product_not_purchasable',
        );
    }

    public static function shopNotOperational(string $shopName): self
    {
        return new self(
            sprintf('Toko "%s" sedang tidak aktif sehingga produknya tidak dapat dibeli.', $shopName),
            'shop_not_operational',
        );
    }

    public static function cannotBuyOwnProduct(): self
    {
        return new self('Anda tidak dapat membeli produk dari toko Anda sendiri.', 'self_purchase');
    }

    public static function invalidCoupon(string $reason): self
    {
        return new self($reason, 'invalid_coupon');
    }

    public static function sellerProductLimitReached(int $limit): self
    {
        return new self(
            sprintf('Batas maksimum %d produk per toko telah tercapai.', $limit),
            'seller_product_limit_reached',
        );
    }
}
