<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Exceptions\DownloadException;
use App\Models\DownloadLog;
use App\Models\OrderDeliverable;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves purchased files.
 *
 * Authorisation is checked on every request against the entitlement ledger,
 * not against a URL that was valid once. That is what makes revocation
 * (a refund, a fraud hold) take effect immediately even for a link the buyer
 * has already saved.
 */
class DownloadService
{
    public function __construct(private readonly DeliverableService $deliverables) {}

    /**
     * Assert that a user may download a deliverable and return it.
     *
     * @throws DownloadException
     */
    public function authorize(OrderDeliverable $deliverable, User $user): OrderDeliverable
    {
        $deliverable->loadMissing(['productFile', 'order']);

        if ($deliverable->buyer_id !== $user->id && ! $user->isAdmin()) {
            throw DownloadException::notEntitled();
        }

        if (! $deliverable->is_active) {
            throw DownloadException::revoked();
        }

        // The order itself must still grant access: a refund flips this even
        // if the deliverable row was somehow missed.
        if (! $deliverable->order->grantsDownloadAccess()) {
            throw DownloadException::revoked();
        }

        if ($deliverable->productFile === null) {
            throw DownloadException::fileUnavailable();
        }

        return $deliverable;
    }

    /**
     * Stream a file to the client and record the download.
     *
     * The file is served through the application rather than a direct storage
     * URL so that every access is authenticated, audited and countable.
     *
     * @throws DownloadException
     */
    public function stream(OrderDeliverable $deliverable, User $user, ?string $ip, ?string $userAgent): StreamedResponse
    {
        $deliverable = $this->authorize($deliverable, $user);

        $file = $deliverable->productFile;
        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw DownloadException::fileUnavailable();
        }

        // Audit BEFORE streaming: if the transfer is interrupted, we still want
        // a record that it was initiated.
        $this->deliverables->recordDownload($deliverable);

        DownloadLog::create([
            'order_deliverable_id' => $deliverable->id,
            'user_id' => $user->id,
            'product_file_id' => $file->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            'downloaded_at' => now(),
        ]);

        // Bump the product's aggregate counter.
        $deliverable->orderItem?->product?->increment('download_count');

        return $disk->download($file->path, $this->downloadName($file->name, $file->version));
    }

    /**
     * Name the file as it was uploaded, but namespaced by version so a buyer
     * who downloaded v1.0 can tell it apart from v1.1.
     */
    private function downloadName(string $name, ?string $version): string
    {
        if ($version === null || $version === '' || $version === '1.0') {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return $extension !== ''
            ? sprintf('%s-v%s.%s', $base, $version, $extension)
            : sprintf('%s-v%s', $base, $version);
    }
}
