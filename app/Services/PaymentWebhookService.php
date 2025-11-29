<?php

namespace App\Services;

use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function processWebhook(array $payload): array
    {
       // dd($payload);
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$idempotencyKey) {
            throw new \Exception("idempotency_key is required");
        }

        if (!$orderId) {
            throw new \Exception("order_id is required");
        }

        if (!in_array($status, ['paid', 'failed'])) {
            throw new \Exception("Invalid status. Must be 'paid' or 'failed'");
        }


        return DB::transaction(function () use ($idempotencyKey, $orderId, $status, $payload) {

            $existingWebhook = PaymentWebhook::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingWebhook && $existingWebhook->isProcessed()) {
                Log::info("Webhook already processed (idempotency)", [
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $orderId,
                ]);

                return [
                    'message' => 'Webhook already processed',
                    'status' => $existingWebhook->status,
                ];
            }


            $webhook = PaymentWebhook::firstOrNew(['idempotency_key' => $idempotencyKey]);
            $webhook->payload = $payload;
            $webhook->target_order_id = $orderId;
            $webhook->status = 'pending';
            $webhook->save();


            try {
                $this->processWebhookForOrder($orderId, $status, $webhook);
            } catch (\Exception $e) {

                if (str_contains($e->getMessage(), 'not found')) {
                    Log::info("Webhook received before order creation, will process later", [
                        'idempotency_key' => $idempotencyKey,
                        'order_id' => $orderId,
                    ]);
                    return [
                        'message' => 'Webhook received, will process when order is created',
                        'status' => 'pending',
                    ];
                }

                $webhook->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                ]);

                throw $e;
            }

            return [
                'message' => 'Webhook processed successfully',
                'status' => $webhook->status,
            ];
        });
    }

    public function processWebhookForOrder(int $orderId, string $status, ?PaymentWebhook $webhook = null): void
    {
        $orderService = app(OrderService::class);
        $order = $orderService->getOrder($orderId);

        if (!$order) {
            throw new \Exception("Order not found");
        }

        // Prevent duplicate processing
        if ($status === 'paid' && $order->isPaid()) {
            Log::info("Order already paid, skipping webhook processing", [
                'order_id' => $orderId,
            ]);
            if ($webhook) {
                $webhook->update([
                    'order_id' => $order->id,
                    'target_order_id' => $order->id,
                    'status' => 'success',
                    'processed_at' => now(),
                ]);
            }
            return;
        }

        if ($status === 'failed' && $order->isCancelled()) {
            Log::info("Order already cancelled, skipping webhook processing", [
                'order_id' => $orderId,
            ]);
            if ($webhook) {
                $webhook->update([
                    'status' => 'success',
                    'processed_at' => now(),
                ]);
            }
            return;
        }

        DB::transaction(function () use ($order, $status, $webhook) {
            if ($status === 'paid') {
                $orderService = app(OrderService::class);
                $orderService->markOrderAsPaid($order->id);
            } elseif ($status === 'failed') {
                $orderService = app(OrderService::class);
                $orderService->markOrderAsCancelled($order->id);
            }

            if ($webhook) {
                $webhook->update([
                    'status' => 'success',
                    'processed_at' => now(),
                ]);
            }

            Log::info("Webhook processed", [
                'order_id' => $order->id,
                'status' => $status,
            ]);
        });
    }

    public function processPendingWebhooksForOrder(int $orderId): void
    {
        $pendingWebhooks = PaymentWebhook::where('status', 'pending')
            ->where(function ($query) use ($orderId) {
                $query->where('order_id', $orderId)
                    ->orWhere('target_order_id', $orderId);
            })
            ->lockForUpdate()
            ->get();

        foreach ($pendingWebhooks as $webhook) {
            try {
                $status = $webhook->payload['status'] ?? null;
                if ($status && in_array($status, ['paid', 'failed'])) {
                    $this->processWebhookForOrder($orderId, $status, $webhook);
                }
            } catch (\Exception $e) {
                Log::error("Failed to process pending webhook", [
                    'webhook_id' => $webhook->id,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

