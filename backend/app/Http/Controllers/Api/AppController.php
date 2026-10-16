<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    /**
     * List all applications for the authenticated tenant.
     *
     * GET /api/apps
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $apps = App::where('tenant_id', $tenantId)
            ->with([
                'connection',
                'builds' => fn ($q) => $q->latest('id'),
                'subscriptions.plan',
                'configs' => fn ($q) => $q->latest('version'),
                'keystore',
            ])
            ->latest()
            ->get();

        return response()->json($apps);
    }

    /**
     * Initialize a new mobile application.
     *
     * POST /api/apps
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:50'],
            'platform' => ['required', 'in:woo,shopify'],
        ]);

        $user = $request->user();
        $tenant = $user->tenant;

        $sanitizedSlug = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::slug($data['name'])));
        if (empty($sanitizedSlug)) {
            $sanitizedSlug = 'store';
        }
        $random = Str::lower(Str::random(6));
        $packageName = "com.{$sanitizedSlug}.app{$random}";

        $app = App::create([
            'tenant_id'    => $tenant->id,
            'name'         => $data['name'],
            'platform'     => $data['platform'],
            'package_name' => $packageName,
            'app_key'      => 'app_key_'.Str::random(32),
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        AuditLog::create([
            'tenant_id'  => $tenant->id,
            'actor_id'   => $user->id,
            'action'     => 'app.created',
            'target'     => "app:{$app->ulid}",
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta'       => [
                'name'     => $app->name,
                'platform' => $app->platform->value ?? (string) $app->platform,
            ],
        ]);

        return response()->json($app, 201);
    }

    /**
     * Show a single application details.
     *
     * GET /api/apps/{ulid}
     */
    public function show(string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)
            ->with([
                'connection',
                'builds' => fn ($q) => $q->latest('id'),
                'subscriptions.plan',
                'configs' => fn ($q) => $q->latest('version'),
                'keystore',
            ])
            ->firstOrFail();

        return response()->json($app);
    }
}
