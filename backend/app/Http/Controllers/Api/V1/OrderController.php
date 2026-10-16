<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppUser;
use App\Services\Connectors\ConnectorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory
    ) {}

    /**
     * Retrieves orders specifically for the logged-in customer.
     * Filtered strictly by store_customer_id.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        /** @var AppUser $user */
        $user = $request->user();

        if (empty($user->store_customer_id) || ! $app->connection) {
            return response()->json(['data' => []]);
        }

        try {
            $connector = $this->connectorFactory->make($app->connection);
            $orders = $connector->getOrders((string) $user->store_customer_id);

            return response()->json(['data' => $orders]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'ORDERS_FETCH_FAILED',
                    'message' => 'Unable to fetch orders: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Retrieves single order by ID, verifying store_customer_id ownership.
     * Prevents User A from reading User B's orders (IDOR protection).
     */
    public function show(string $id, Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        /** @var AppUser $user */
        $user = $request->user();

        if (empty($user->store_customer_id) || ! $app->connection) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Order not found.'],
            ], 404);
        }

        try {
            $connector = $this->connectorFactory->make($app->connection);
            $order = $connector->getOrder($id, (string) $user->store_customer_id);

            if (! $order) {
                return response()->json([
                    'error' => [
                        'code'    => 'NOT_FOUND',
                        'message' => 'Order not found or does not belong to your account.',
                    ],
                ], 404);
            }

            return response()->json(['data' => $order]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'ORDER_FETCH_FAILED',
                    'message' => 'Unable to fetch order details.',
                ],
            ], 500);
        }
    }
}
