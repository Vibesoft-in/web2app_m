<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handles Meta WhatsApp Cloud API webhook challenge verification (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->input('hub_mode', $request->query('hub.mode'));
        $token = $request->input('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->input('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = (string) config('services.whatsapp.webhook_verify_token', 'appify_whatsapp_verify_token');

        if ($mode === 'subscribe' && ! empty($token) && hash_equals($expectedToken, (string) $token)) {
            Log::info("WhatsApp webhook challenge verified successfully.");
            return response((string) $challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning("WhatsApp webhook challenge verification failed.");
        return response()->json(['error' => 'Verification token mismatch.'], 403);
    }

    /**
     * Handles incoming WhatsApp delivery status and inbound message webhooks (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $appSecret = (string) config('services.whatsapp.app_secret', '');
        $signature = $request->header('X-Hub-Signature-256');

        if (! empty($appSecret) && ! empty($signature)) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if (! hash_equals($expectedSignature, $signature)) {
                Log::warning("WhatsApp webhook signature verification failed.");
                return response()->json([
                    'error' => [
                        'code'    => 'INVALID_SIGNATURE',
                        'message' => 'HMAC signature verification failed.',
                    ],
                ], 401);
            }
        }

        $payload = $request->json()->all();
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $statuses = $value['statuses'] ?? [];

                foreach ($statuses as $statusObj) {
                    $msgId = (string) ($statusObj['id'] ?? '');
                    $status = (string) ($statusObj['status'] ?? '');
                    $recipientId = (string) ($statusObj['recipient_id'] ?? '');

                    if (! empty($msgId)) {
                        $eventId = "wa_status_{$msgId}_{$status}";

                        if (! WebhookEvent::where('source', 'whatsapp')->where('event_id', $eventId)->exists()) {
                            WebhookEvent::create([
                                'source'       => 'whatsapp',
                                'event_id'     => $eventId,
                                'payload'      => $statusObj,
                                'processed_at' => now(),
                            ]);
                        }

                        Log::info("WhatsApp message status update", [
                            'message_id'   => $msgId,
                            'status'       => $status,
                            'recipient_id' => $recipientId,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status'  => 'acknowledged',
            'message' => 'WhatsApp delivery event processed.',
        ]);
    }
}
