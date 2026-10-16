<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignJob;
use App\Models\App;
use App\Models\NotificationCampaign;
use App\Services\Billing\EntitlementService;
use App\Services\Notifications\DeepLinkValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationCampaignController extends Controller
{
    public function __construct(
        private readonly DeepLinkValidator $deepLinkValidator,
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Lists all notification campaigns for an application.
     */
    public function index(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $campaigns = NotificationCampaign::where('app_id', $app->id)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $campaigns,
        ]);
    }

    /**
     * Creates and dispatches or schedules a push notification campaign.
     */
    public function store(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();
        $tenant = $app->tenant;

        $validator = Validator::make($request->all(), [
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['required', 'string', 'max:1000'],
            'deep_link'    => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'errors'  => $validator->errors(),
                ],
            ], 422);
        }

        $cleanTitle = strip_tags(trim((string) $request->input('title')));
        $cleanBody = strip_tags(trim((string) $request->input('body')));

        if (empty($cleanTitle) || empty($cleanBody)) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Notification title and body must contain valid text after sanitization.',
                ],
            ], 422);
        }

        // Validate deep link allowlist
        $deepLink = $request->input('deep_link');
        if (! $this->deepLinkValidator->isValid($deepLink)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_DEEP_LINK',
                    'message' => "Invalid deep link format '{$deepLink}'. Allowed formats: product:ID, order:ID, category:ID.",
                ],
            ], 422);
        }

        // Gate by entitlement limit
        if (! $this->entitlementService->hasRemainingLimit($tenant, 'push_per_month')) {
            return response()->json([
                'error' => [
                    'code'    => 'PUSH_LIMIT_EXCEEDED',
                    'message' => 'Monthly push notification limit reached for your plan.',
                ],
            ], 403);
        }

        $scheduledAt = $request->input('scheduled_at') ? Carbon::parse($request->input('scheduled_at')) : null;
        $isScheduled = $scheduledAt !== null;
        $status = $isScheduled ? 'scheduled' : 'pending';

        $campaign = NotificationCampaign::create([
            'tenant_id'    => $tenant->id,
            'app_id'       => $app->id,
            'title'        => $cleanTitle,
            'body'         => $cleanBody,
            'audience'     => [
                'type'      => 'all',
                'deep_link' => $deepLink,
            ],
            'scheduled_at' => $scheduledAt,
            'status'       => $status,
        ]);

        if (! $isScheduled) {
            SendCampaignJob::dispatch($campaign->id);
        }

        return response()->json([
            'message'  => $isScheduled ? 'Campaign scheduled successfully.' : 'Campaign queued for delivery.',
            'campaign' => $campaign,
        ], 201);
    }
}
