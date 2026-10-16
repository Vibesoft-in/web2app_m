<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppUser;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    /**
     * Registers an FCM push device token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        /** @var AppUser $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'fcm_token' => ['required', 'string'],
            'platform'  => ['nullable', 'string', 'in:android,ios'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'fcm_token is required.',
                    'errors'  => $validator->errors(),
                ],
            ], 422);
        }

        $fcmToken = (string) $request->input('fcm_token');
        $platform = (string) $request->input('platform', 'android');

        PushDevice::withoutTenantScope()->updateOrCreate([
            'tenant_id'   => $app->tenant_id,
            'app_id'      => $app->id,
            'fcm_token'   => $fcmToken,
        ], [
            'app_user_id' => $user->id,
            'platform'    => $platform,
        ]);

        return response()->json([
            'message' => 'Device registered for notifications.',
        ]);
    }

    /**
     * Unregisters an FCM device token upon user logout.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var App|null $app */
        $app = $request->attributes->get('resolved_app');
        /** @var AppUser|null $user */
        $user = $request->user();
        $fcmToken = (string) $request->input('fcm_token');

        $query = PushDevice::withoutTenantScope();

        if ($app) {
            $query->where('tenant_id', $app->tenant_id)->where('app_id', $app->id);
        }

        if ($user) {
            $query->where('app_user_id', $user->id);
        }

        if (! empty($fcmToken)) {
            $query->where('fcm_token', $fcmToken);
        }

        $query->delete();

        return response()->json([
            'message' => 'Device unregistered.',
        ]);
    }
}
