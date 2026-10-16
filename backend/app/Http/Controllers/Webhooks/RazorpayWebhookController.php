<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\Billing\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly RazorpayService $razorpayService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');

        // 1. Verify HMAC SHA-256 signature
        if (! $this->razorpayService->verifyWebhookSignature($rawPayload, $signature)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_SIGNATURE',
                    'message' => 'The Razorpay webhook signature could not be verified.',
                ],
            ], 400);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_PAYLOAD',
                    'message' => 'Webhook payload must be valid JSON.',
                ],
            ], 400);
        }

        // 2. Extract event_id for idempotency
        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? $request->header('X-Razorpay-Event-Id') ?? '');
        if (empty($eventId)) {
            $eventId = 'evt_'.hash('sha256', $rawPayload);
        }

        // 3. Replay Protection: Idempotently check if event has already been registered
        if (WebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json([
                'status'  => 'ignored',
                'message' => 'Duplicate webhook event already processed.',
            ], 200);
        }

        // 4. Save webhook event
        $webhookEvent = WebhookEvent::create([
            'source'   => 'razorpay',
            'event_id' => $eventId,
            'payload'  => $payload,
        ]);

        $event = (string) ($payload['event'] ?? '');

        DB::transaction(function () use ($event, $payload, $webhookEvent) {
            match ($event) {
                'payment.captured', 'subscription.charged', 'order.paid' => $this->handlePaymentCaptured($payload),
                'subscription.cancelled'                                 => $this->handleSubscriptionCancelled($payload),
                default                                                 => null,
            };

            $webhookEvent->update(['processed_at' => now()]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => "Webhook {$event} processed successfully.",
        ]);
    }

    private function handlePaymentCaptured(array $payload): void
    {
        $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
        $paymentId = (string) ($paymentEntity['id'] ?? '');

        if (empty($paymentId)) {
            return;
        }

        // 5. Double Payment Protection: prevent double-credit if payment ID was already processed
        if (Payment::where('razorpay_payment_id', $paymentId)->exists()) {
            return;
        }

        $amount = (int) ($paymentEntity['amount'] ?? 0);
        $notes = $paymentEntity['notes'] ?? [];
        $tenantId = (int) ($notes['tenant_id'] ?? 0);

        if (! $tenantId) {
            // Fallback: look up by subscription ID if present
            $subId = (string) ($paymentEntity['subscription_id'] ?? '');
            if (! empty($subId)) {
                $sub = Subscription::withoutTenantScope()->where('razorpay_sub_id', $subId)->first();
                $tenantId = $sub ? (int) $sub->tenant_id : 0;
            }
        }

        if (! $tenantId) {
            $firstTenant = Tenant::first();
            $tenantId = $firstTenant ? $firstTenant->id : 1;
        }

        // Record immutable Payment
        Payment::withoutTenantScope()->create([
            'tenant_id'          => $tenantId,
            'razorpay_payment_id'=> $paymentId,
            'amount'             => $amount,
            'status'             => 'captured',
            'raw'                => $paymentEntity,
        ]);

        // Activate or renew Subscription
        $subscription = null;
        $subId = (string) ($paymentEntity['subscription_id'] ?? '');
        if (! empty($subId)) {
            $subscription = Subscription::withoutTenantScope()->where('razorpay_sub_id', $subId)->first();
        }

        if (! $subscription) {
            $subscription = Subscription::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->latest('id')
                ->first();
        }

        if ($subscription) {
            $interval = $notes['interval'] ?? 'monthly';
            $duration = $interval === 'annual' ? now()->addYear() : now()->addMonth();

            $subscription->update([
                'status'             => 'active',
                'current_period_end' => $duration,
            ]);
        }

        // Audit Trail
        AuditLog::create([
            'tenant_id' => $tenantId,
            'action'    => 'payment.captured',
            'target'    => "Payment:{$paymentId}",
            'meta'      => [
                'amount'   => $amount,
                'currency' => $paymentEntity['currency'] ?? 'INR',
            ],
            'created_at'=> now(),
        ]);
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        $subEntity = $payload['payload']['subscription']['entity'] ?? [];
        $subId = (string) ($subEntity['id'] ?? '');

        if (! empty($subId)) {
            $sub = Subscription::withoutTenantScope()->where('razorpay_sub_id', $subId)->first();
            if ($sub) {
                $sub->update(['status' => 'cancelled']);

                AuditLog::create([
                    'tenant_id' => $sub->tenant_id,
                    'action'    => 'subscription.cancelled',
                    'target'    => "Subscription:{$subId}",
                    'meta'      => ['reason' => $subEntity['ended_reason'] ?? 'customer_request'],
                    'created_at'=> now(),
                ]);
            }
        }
    }
}
