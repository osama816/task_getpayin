<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class HoldController extends Controller
{
    public function __construct(
        private HoldService $holdService
    ) {
    }

    #[OA\Post(
        path: "/api/holds",
        summary: "Create a temporary hold for a product",
        tags: ["Holds"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product_id", "qty"],
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 1, description: "Product ID"),
                    new OA\Property(property: "qty", type: "integer", example: 2, description: "Quantity to hold", minimum: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Hold created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "hold_id", type: "integer", example: 1),
                        new OA\Property(property: "expires_at", type: "string", format: "date-time", example: "2024-01-01T12:02:00.000000Z")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Bad request (insufficient stock or other error)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Insufficient stock. Available: 5, Requested: 10")
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
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->holdService->createHold(
                $request->input('product_id'),
                $request->input('qty')
            );

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

