<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WooWebhookController extends Controller
{
    /**
     * Handles incoming WooCommerce webhooks.
     * Verifies HMAC signature, enforces idempotency, and records the event.
     */
    public function handle(Request $request, string $appUlid): JsonResponse
    {
        // 1. Resolve app without tenant scope (webhooks are public and authenticate via HMAC)
        $app = App::withoutTenantScope()->where('ulid', $appUlid)->first();

        if (! $app) {
            return response()->json([
                'error' => [
                    'code'    => 'APP_NOT_FOUND',
                    'message' => 'No store app found for this webhook endpoint.',
                ],
            ], 404);
        }

        // 2. Resolve webhook secret
        $secret = $this->resolveWebhookSecret($app);

        // 3. Verify WooCommerce HMAC signature
        $signature = $request->header('X-WC-Webhook-Signature');
        if (empty($signature)) {
            return response()->json([
                'error' => [
                    'code'    => 'MISSING_SIGNATURE',
                    'message' => 'Missing X-WC-Webhook-Signature header.',
                ],
            ], 401);
        }

        $rawPayload = $request->getContent();
        $expectedSignature = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_SIGNATURE',
                    'message' => 'HMAC signature verification failed.',
                ],
            ], 401);
        }

        // 4. Replay attack & idempotency protection
        $deliveryId = $request->header('X-WC-Webhook-Delivery-ID')
            ?? $request->header('X-WC-Webhook-ID')
            ?? md5($signature . $rawPayload);

        $eventId = "woo_{$app->ulid}_{$deliveryId}";

        if (WebhookEvent::where('source', 'woo')->where('event_id', $eventId)->exists()) {
            return response()->json([
                'status'   => 'ignored',
                'message'  => 'Webhook event has already been processed.',
                'event_id' => $eventId,
            ]);
        }

        // 5. Record webhook event
        WebhookEvent::create([
            'source'       => 'woo',
            'event_id'     => $eventId,
            'payload'      => $request->json()->all(),
            'processed_at' => now(),
        ]);

        $topic = $request->header('X-WC-Webhook-Topic', 'unknown');
        $payload = $request->json()->all();

        // 6. Trigger order status push notification on order events
        if (in_array($topic, ['order.updated', 'order.created'], true)) {
            $customerId = (string) ($payload['customer_id'] ?? '');
            $orderId = (string) ($payload['id'] ?? '');
            $status = (string) ($payload['status'] ?? 'processing');

            if (! empty($customerId) && ! empty($orderId)) {
                \App\Jobs\SendOrderStatusPushJob::dispatch(
                    $app->id,
                    $customerId,
                    $orderId,
                    $status
                );
            }
        }

        Log::info("WooCommerce webhook processed", [
            'app_id'   => $app->id,
            'topic'    => $topic,
            'event_id' => $eventId,
        ]);

        return response()->json([
            'status'   => 'acknowledged',
            'topic'    => $topic,
            'event_id' => $eventId,
        ]);
    }

    /**
     * Resolves the webhook secret for the given app.
     */
    public function resolveWebhookSecret(App $app): string
    {
        $connection = $app->connection;

        if ($connection && is_array($connection->credentials) && ! empty($connection->credentials['webhook_secret'])) {
            return (string) $connection->credentials['webhook_secret'];
        }

        // Deterministic fallback derived from app_key and backend app.key
        return hash_hmac('sha256', "woo_webhook:{$app->ulid}:{$app->app_key}", (string) config('app.key'));
    }
}
