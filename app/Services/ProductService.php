<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    private const CACHE_TTL = 5; // 5 seconds

    public function getProduct(int $id): ?array
    {
        $cacheKey = "product:{$id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            $product = Product::find($id);

            if (!$product) {
                return null;
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'available_stock' => $this->calculateAvailableStock($product),
            ];
        });
    }

    public function calculateAvailableStock(Product $product): int
    {
        // Calculate: stock - active_holds - consumed_orders
        $activeHoldsQty = DB::table('holds')
            ->where('product_id', $product->id)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->sum('qty');

        $consumedOrdersQty = DB::table('orders')
            ->join('holds', 'orders.hold_id', '=', 'holds.id')
            ->where('holds.product_id', $product->id)
            ->where('orders.status', 'paid')
            ->sum('holds.qty');

        $available = $product->stock - $activeHoldsQty - $consumedOrdersQty;

        return max(0, $available);
    }

    public function invalidateCache(int $productId): void
    {
        Cache::forget("product:{$productId}");
        Log::info("Product cache invalidated for product ID: {$productId}");
    }
}

