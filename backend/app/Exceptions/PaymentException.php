<?php

declare(strict_types=1);

namespace App\Exceptions;

class PaymentException extends DomainException
{
    public static function orderNotPayable(string $invoiceNumber): self
    {
        return new self(
            sprintf('Pesanan %s tidak dalam status yang dapat dibayar.', $invoiceNumber),
            'order_not_payable',
        );
    }

    public static function orderExpired(string $invoiceNumber): self
    {
        return new self(
            sprintf('Pesanan %s telah kedaluwarsa.', $invoiceNumber),
            'order_expired',
        );
    }

    public static function alreadyPaid(string $invoiceNumber): self
    {
        return new self(
            sprintf('Pesanan %s sudah dibayar.', $invoiceNumber),
            'order_already_paid',
        );
    }

    public static function invalidSignature(): self
    {
        return new self('Signature webhook tidak valid.', 'invalid_signature', 401);
    }

    public static function gatewayNotFound(string $name): self
    {
        return new self(
            sprintf('Payment gateway "%s" tidak terdaftar.', $name),
            'gateway_not_found',
        );
    }

    public static function gatewayDisabled(string $name): self
    {
        return new self(
            sprintf('Payment gateway "%s" tidak dikonfigurasi.', $name),
            'gateway_disabled',
        );
    }

    public static function amountMismatch(string $expected, string $actual): self
    {
        return new self(
            sprintf('Nominal pembayaran tidak sesuai (diharapkan %s, diterima %s).', $expected, $actual),
            'amount_mismatch',
        );
    }

    public static function proofAlreadySubmitted(): self
    {
        return new self(
            'Bukti pembayaran sudah diunggah dan sedang menunggu verifikasi.',
            'proof_already_submitted',
        );
    }

    public static function paymentNotReviewable(): self
    {
        return new self(
            'Pembayaran ini tidak dapat diverifikasi.',
            'payment_not_reviewable',
        );
    }
}
