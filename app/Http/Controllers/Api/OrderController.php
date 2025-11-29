<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {
    }

    #[OA\Post(
        path: "/api/orders",
        summary: "Create an order from an existing hold",
        tags: ["Orders"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["hold_id"],
                properties: [
                    new OA\Property(property: "hold_id", type: "integer", example: 1, description: "Hold ID")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Order created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "order_id", type: "integer", example: 1),
                        new OA\Property(property: "total_amount", type: "string", example: "199.98"),
                        new OA\Property(property: "status", type: "string", example: "pending", enum: ["pending", "paid", "cancelled"])
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Bad request (hold expired, already used, or other error)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Hold has expired")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "errors", type: "object")
                    ]
                )
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|integer|exists:holds,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->orderService->createOrder($request->input('hold_id'));

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

