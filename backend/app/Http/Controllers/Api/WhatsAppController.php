<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class WhatsAppController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService
    ) {}

    /**
     * Sends a test template message to verify WhatsApp integration credentials.
     * Enforces entitlement gating and debits wallet before dispatching.
     */
    public function sendTest(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();
        $tenant = $app->tenant;

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Phone number is required.',
                    'errors'  => $validator->errors(),
                ],
            ], 422);
        }

        try {
            $result = $this->whatsAppService->sendTestMessage($tenant, (string) $request->input('phone'));

            return response()->json([
                'message'    => 'WhatsApp test message sent successfully.',
                'message_id' => $result['message_id'],
                'status'     => $result['status'],
                'cost'       => $result['cost'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'WHATSAPP_NOT_ENTITLED',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_PHONE_NUMBER',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
