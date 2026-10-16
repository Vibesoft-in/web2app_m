<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppUser;
use App\Services\Connectors\ConnectorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory
    ) {}

    /**
     * Revalidates cart items/prices via connector and returns a secure checkout URL.
     */
    public function checkoutUrl(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        $validator = Validator::make($request->all(), [
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.variant_id'    => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Valid cart items are required.',
                    'errors'  => $validator->errors(),
                ],
            ], 422);
        }

        if (! $app->connection) {
            return response()->json([
                'error' => [
                    'code'    => 'STORE_DISCONNECTED',
                    'message' => 'Store connection is not configured.',
                ],
            ], 400);
        }

        /** @var AppUser|null $user */
        $user = $request->user();

        try {
            $connector = $this->connectorFactory->make($app->connection);
            $checkoutUrl = $connector->createCheckoutUrl(
                $request->input('items'),
                $user?->store_customer_id
            );

            // Calculate allowed hosts for Flutter webview security
            $allowedHosts = [];
            $parsedHost = parse_url($app->connection->site_url, PHP_URL_HOST);
            if (! empty($parsedHost)) {
                $allowedHosts[] = strtolower($parsedHost);
            }
            $allowedHosts = array_merge($allowedHosts, [
                'api.razorpay.com',
                'checkout.razorpay.com',
                'api.stripe.com',
                'checkout.stripe.com',
            ]);

            return response()->json([
                'checkout_url'           => $checkoutUrl,
                'webview_allowed_hosts'  => array_values(array_unique($allowedHosts)),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'STOCK_UNAVAILABLE',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'CHECKOUT_GENERATION_FAILED',
                    'message' => 'Failed to initialize checkout session: '.$e->getMessage(),
                ],
            ], 500);
        }
    }
}
