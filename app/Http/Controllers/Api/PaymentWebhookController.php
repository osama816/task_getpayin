<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentWebhookService $webhookService
    ) {
    }

    #[OA\Post(
        path: "/api/payments/webhook",
        summary: "Receive webhook from payment gateway",
        description: "This endpoint is idempotent and out-of-order safe. If webhook arrives before order creation, it will be saved and processed later.",
        tags: ["Webhooks"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["idempotency_key", "order_id", "status"],
                properties: [
                    new OA\Property(property: "idempotency_key", type: "string", example: "unique-key-123", description: "Unique key for idempotency"),
                    new OA\Property(property: "order_id", type: "integer", example: 1, description: "Order ID"),
                    new OA\Property(property: "status", type: "string", example: "paid", enum: ["paid", "failed"], description: "Payment status")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhook processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Webhook processed successfully"),
                        new OA\Property(property: "status", type: "string", example: "success", enum: ["success", "pending"])
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Bad request",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "idempotency_key is required")
                    ]
                )
            )
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->processWebhook($request->all());

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

