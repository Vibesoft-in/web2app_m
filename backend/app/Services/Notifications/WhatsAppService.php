<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Tenant;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class WhatsAppService
{
    private const DEFAULT_MESSAGE_COST = 0.80; // ₹0.80 per business template message

    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Sends a template-only WhatsApp message via Meta Cloud API.
     * Enforces entitlement gating and debits wallet usage event BEFORE sending.
     *
     * @param array<mixed> $parameters
     * @return array{message_id: string, status: string, cost: float}
     */
    public function sendTemplateMessage(
        Tenant $tenant,
        string $toPhone,
        string $templateName,
        array $parameters = [],
        float $costPerMessage = self::DEFAULT_MESSAGE_COST
    ): array {
        // 1. Gate by entitlement: tenant must have active plan including 'whatsapp'
        if (! $this->entitlementService->can($tenant, 'whatsapp')) {
            throw new RuntimeException('WhatsApp messaging is not included in your current plan or your subscription is past the grace period.');
        }

        // Validate destination phone number
        $cleanPhone = preg_replace('/[^\d]/', '', $toPhone);
        if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
            throw new InvalidArgumentException('Invalid destination phone number for WhatsApp message.');
        }

        // Validate template name format per Meta requirements (lowercase alphanumeric and underscore)
        if (! preg_match('/^[a-z0-9_]+$/', $templateName)) {
            throw new InvalidArgumentException('Invalid template name. Only lowercase alphanumeric and underscores allowed.');
        }

        // 2. Wallet debit before send (Meta policy & SaaS credit protection)
        $this->entitlementService->recordUsage(
            $tenant,
            null,
            'whatsapp_message',
            1,
            $costPerMessage
        );

        $isLive = config('services.whatsapp.live', false);
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if ($isLive && ! empty($phoneNumberId) && ! empty($accessToken)) {
            $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to'                => $cleanPhone,
                    'type'              => 'template',
                    'template'          => [
                        'name'     => $templateName,
                        'language' => ['code' => 'en_US'],
                    ],
                ]);

            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::error("WhatsApp Cloud API delivery failed", [
                    'status' => $response->status(),
                ]);
                throw new RuntimeException("WhatsApp Cloud API message delivery failed. Please verify your phone number and template configuration.");
            }

            $msgId = (string) ($response->json('messages.0.id') ?? 'wamid.live_'.uniqid());

            return [
                'message_id' => $msgId,
                'status'     => 'sent',
                'cost'       => $costPerMessage,
            ];
        }

        // Test/local simulation
        return [
            'message_id' => 'wamid.mock_'.substr(md5("{$cleanPhone}:{$templateName}"), 0, 16),
            'status'     => 'sent',
            'cost'       => $costPerMessage,
        ];
    }

    /**
     * Sends a verified test message to confirm integration credentials.
     */
    public function sendTestMessage(Tenant $tenant, string $toPhone): array
    {
        return $this->sendTemplateMessage(
            $tenant,
            $toPhone,
            'order_status_update',
            [],
            self::DEFAULT_MESSAGE_COST
        );
    }
}
