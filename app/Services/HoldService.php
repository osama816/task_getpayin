<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class HoldService
{
    private const HOLD_DURATION_MINUTES = 2;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = [100, 200, 400]; // Exponential backoff

    public function createHold(int $productId, int $qty): array
    {
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            try {
                return DB::transaction(function () use ($productId, $qty) {
                    // Lock the product row for update
                    $product = Product::where('id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception("Product not found");
                    }

                    // Calculate available stock
                    $productService = app(ProductService::class);
                    $availableStock = $productService->calculateAvailableStock($product);

                    if ($availableStock < $qty) {
                        throw new \Exception("Insufficient stock. Available: {$availableStock}, Requested: {$qty}");
                    }

                    // Create hold
                    $expiresAt = Carbon::now()->addMinutes(self::HOLD_DURATION_MINUTES);
                    $hold = Hold::create([
                        'product_id' => $productId,
                        'qty' => $qty,
                        'expires_at' => $expiresAt,
                        'status' => 'reserved',
                    ]);

                    // Invalidate cache
                    $productService->invalidateCache($productId);

                    Log::info("Hold created", [
                        'hold_id' => $hold->id,
                        'product_id' => $productId,
                        'qty' => $qty,
                    ]);

                    return [
                        'hold_id' => $hold->id,
                        'expires_at' => $hold->expires_at->toIso8601String(),
                    ];
                });
            } catch (\Exception $e) {
                $retries++;

                if ($retries >= self::MAX_RETRIES) {
                    Log::alert("Failed to create hold after retries", [
                        'product_id' => $productId,
                        'qty' => $qty,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                usleep(self::RETRY_DELAY_MS[$retries - 1] * 1000);
                Log::warning("Retrying hold creation", [
                    'attempt' => $retries,
                    'product_id' => $productId,
                ]);
            }
        }

        throw new \Exception("Failed to create hold after retries");
    }

    public function getHold(int $holdId): ?Hold
    {
        return Hold::find($holdId);
    }

    public function markHoldAsUsed(int $holdId, int $orderId): bool
    {
        return DB::transaction(function () use ($holdId, $orderId) {
            $hold = Hold::where('id', $holdId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                return false;
            }

            if ($hold->used_for_order_id !== null) {
                return false; // Already used
            }

            if ($hold->isExpired()) {
                return false;
            }

            $hold->update([
                'used_for_order_id' => $orderId,
            ]);

            $productService = app(ProductService::class);
            $productService->invalidateCache($hold->product_id);

            return true;
        });
    }

    public function expireHold(Hold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $hold->refresh();

            if ($hold->status !== 'reserved' || $hold->used_for_order_id !== null) {
                return; // Already processed
            }

            $hold->update(['status' => 'expired']);

            $productService = app(ProductService::class);
            $productService->invalidateCache($hold->product_id);

            Log::info("Hold expired", [
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id,
            ]);
        });
    }

    public function markHoldAsConsumed(int $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            $hold = Hold::find($holdId);

            if (!$hold) {
                return;
            }

            $hold->update(['status' => 'consumed']);

            $productService = app(ProductService::class);
            $productService->invalidateCache($hold->product_id);
        });
    }

    public static function getHoldDurationMinutes(): int
    {
        return self::HOLD_DURATION_MINUTES;
    }
}

