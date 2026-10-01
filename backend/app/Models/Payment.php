<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'reference',
        'external_id',
        'method',
        'status',
        'amount',
        'currency',
        'proof_path',
        'gateway_payload',
        'gateway_status',
        'failure_reason',
        'expires_at',
        'admin_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // -----------------------------------------------------------------------
    // Behaviour
    // -----------------------------------------------------------------------

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    public function isManual(): bool
    {
        return $this->method->isManual();
    }

    /**
     * A manual payment cannot be confirmed until the buyer has uploaded proof.
     */
    public function hasProof(): bool
    {
        return $this->proof_path !== null;
    }

    public function isAwaitingAdminReview(): bool
    {
        return $this->isManual()
            && $this->status === PaymentStatus::Pending
            && $this->hasProof();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending->value);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }

    /**
     * Manual payments with an uploaded proof, waiting for an admin decision.
     */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query
            ->where('method', PaymentMethod::Manual->value)
            ->where('status', PaymentStatus::Pending->value)
            ->whereNotNull('proof_path');
    }
}
