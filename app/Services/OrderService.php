<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function createOrder(int $holdId): array
    {
        return DB::transaction(function () use ($holdId) {
            $hold = Hold::where('id', $holdId)
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                throw new \Exception("Hold not found");
            }

            if ($hold->isExpired()) {
                throw new \Exception("Hold has expired");
            }

            if ($hold->status !== 'reserved') {
                throw new \Exception("Hold is not in reserved status");
            }

            if ($hold->used_for_order_id !== null) {
                throw new \Exception("Hold has already been used");
            }

            $totalAmount = $hold->product->price * $hold->qty;

            $order = Order::create([
                'hold_id' => $holdId,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // Mark hold as used
            $holdService = app(HoldService::class);
            $holdService->markHoldAsUsed($holdId, $order->id);

            // Check for pending webhooks for this order
            $webhookService = app(PaymentWebhookService::class);
            $webhookService->processPendingWebhooksForOrder($order->id);

            Log::info("Order created", [
                'order_id' => $order->id,
                'hold_id' => $holdId,
            ]);

            return [
                'order_id' => $order->id,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
            ];
        });
    }

    public function markOrderAsPaid(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::find($orderId);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            if ($order->status === 'paid') {
                return; // Already paid
            }

            $order->update(['status' => 'paid']);

            // Mark hold as consumed (do not release stock)
            $holdService = app(HoldService::class);
            $holdService->markHoldAsConsumed($order->hold_id);

            $productService = app(ProductService::class);
            $productService->invalidateCache($order->hold->product_id);

            Log::info("Order marked as paid", [
                'order_id' => $orderId,
            ]);
        });
    }

    public function markOrderAsCancelled(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::find($orderId);

            if (!$order) {
                throw new \Exception("Order not found");
            }

            if ($order->status === 'cancelled') {
                return; // Already cancelled
            }

            $order->update(['status' => 'cancelled']);

            // Expire the hold to release stock
            $holdService = app(HoldService::class);
            $hold = $order->hold;
            if ($hold && $hold->status === 'reserved') {
                $holdService->expireHold($hold);
            }

            $productService = app(ProductService::class);
            $productService->invalidateCache($order->hold->product_id);

            Log::info("Order marked as cancelled", [
                'order_id' => $orderId,
            ]);
        });
    }

    public function getOrder(int $orderId): ?Order
    {
        return Order::find($orderId);
    }
}

