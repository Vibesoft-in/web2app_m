<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ConnectionStatus;
use App\Enums\SetupStep;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppConfig;
use App\Models\AuditLog;
use App\Models\Theme;
use App\Services\Config\ConfigValidator;
use App\Services\Config\Exceptions\ConfigValidationException;
use App\Services\Connectors\ConnectorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigController extends Controller
{
    public function __construct(
        private readonly ConfigValidator $validator = new ConfigValidator(),
        private readonly ConnectorFactory $connectorFactory = new ConnectorFactory()
    ) {}

    /**
     * List all available themes.
     *
     * GET /api/apps/{ulid}/themes
     */
    public function getThemes(): JsonResponse
    {
        $themes = Theme::where('is_active', true)->get();

        return response()->json([
            'themes' => $themes,
        ]);
    }

    /**
     * Get the draft app config.
     * If no draft exists, returns a template populated with the active theme defaults.
     *
     * GET /api/apps/{ulid}/config/draft
     */
    public function getDraft(string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        /** @var AppConfig|null $draft */
        $draft = $app->configs()->where('state', 'draft')->latest()->first();

        if ($draft && ! empty($draft->config)) {
            $config = $draft->config;
            if (empty($config['home'])) {
                $template = $this->getDefaultTemplate($app);
                $config['home'] = $template['home'];
            }
            if (empty($config['app']['name']) || $config['app']['name'] === 'Glow Beauty') {
                $config['app']['name'] = $app->name ?: 'My Store';
            }

            return response()->json([
                'config'     => $config,
                'state'      => 'draft',
                'updated_at' => $draft->updated_at?->toISOString(),
            ]);
        }

        $template = $this->getDefaultTemplate($app);

        return response()->json([
            'config' => $template,
            'state'  => 'template',
        ]);
    }

    /**
     * Get default config template for an app.
     */
    private function getDefaultTemplate(App $app): array
    {
        $defaultTheme = Theme::where('key', 'theme_a')->first() ?? Theme::first();
        $defaults = $defaultTheme?->defaults ?? [
            'layout'  => 'theme_a',
            'colors'  => [
                'primary'    => '#E91E63',
                'secondary'  => '#222222',
                'background' => '#FFFFFF',
                'surface'    => '#FAFAFA',
                'text'       => '#111111',
            ],
            'radius'  => 12,
            'font'    => 'Poppins',
            'nav'     => 'bottom',
        ];

        return [
            'schema_version' => 1,
            'app' => [
                'name'     => $app->name ?: 'My Store',
                'currency' => 'INR',
            ],
            'theme'    => $defaults,
            'features' => [
                'wishlist'      => true,
                'otp_login'     => true,
                'google_login'  => true,
                'notifications' => true,
                'reviews'       => false,
            ],
            'home' => [
                [
                    'type'  => 'banner',
                    'items' => [
                        [
                            'image' => 'https://assets.webintoapp.com/placeholders/banner1.jpg',
                            'link'  => 'category:all',
                        ],
                    ],
                ],
                ['type' => 'categories'],
                [
                    'type'   => 'product_rail',
                    'title'  => 'New Arrivals',
                    'source' => 'recent',
                ],
            ],
            'links' => [
                'privacy' => 'https://webintoapp.com/privacy',
                'terms'   => 'https://webintoapp.com/terms',
            ],
            'webview_allowed_hosts' => [],
        ];
    }

    /**
     * Save draft configuration.
     * Validates JSON schema, hex colors, and HTTPS URLs via ConfigValidator.
     *
     * PUT /api/apps/{ulid}/config/draft
     */
    public function saveDraft(Request $request, string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();
        $payload = $request->all();

        $existingDraft = AppConfig::where('app_id', $app->id)
            ->where('tenant_id', $app->tenant_id)
            ->where('state', 'draft')
            ->first();

        // Optimistic concurrency check: if client sent base_updated_at and it differs, flag conflict (409)
        if ($existingDraft && $request->has('base_updated_at') && ! empty($request->input('base_updated_at'))) {
            $clientBaseTime = $request->input('base_updated_at');
            $serverDraftTime = $existingDraft->updated_at?->toISOString();

            if ($serverDraftTime && $clientBaseTime !== $serverDraftTime) {
                return response()->json([
                    'error' => [
                        'code'    => 'CONFIG_CONFLICT',
                        'message' => 'The configuration was modified in another session. Please reload to avoid overwriting changes.',
                    ],
                ], 409);
            }
        }

        // Merge incoming payload over existing draft or default template so sections like home are NEVER lost
        $baseConfig = ($existingDraft && ! empty($existingDraft->config))
            ? $existingDraft->config
            : $this->getDefaultTemplate($app);

        $merged = array_replace_recursive($baseConfig, $payload);
        if (isset($payload['home']) && is_array($payload['home']) && count($payload['home']) > 0) {
            $merged['home'] = $payload['home'];
        } elseif (empty($merged['home'])) {
            $merged['home'] = $this->getDefaultTemplate($app)['home'];
        }

        try {
            $validatedConfig = $this->validator->validate($merged);
        } catch (ConfigValidationException $e) {
            return response()->json([
                'error' => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors,
                ],
            ], 422);
        }

        $draft = DB::transaction(function () use ($app, $validatedConfig) {
            $draft = AppConfig::updateOrCreate(
                [
                    'app_id'    => $app->id,
                    'tenant_id' => $app->tenant_id,
                    'state'     => 'draft',
                ],
                [
                    'config' => $validatedConfig,
                ]
            );

            // Advance setup step if at theme or branding
            if ($app->setup_step === SetupStep::Theme) {
                $app->update(['setup_step' => SetupStep::Branding]);
            } elseif ($app->setup_step === SetupStep::Branding) {
                $app->update(['setup_step' => SetupStep::Preview]);
            }

            return $draft;
        });

        return response()->json([
            'message'    => 'Draft configuration saved successfully.',
            'config'     => $draft->config,
            'state'      => 'draft',
            'updated_at' => $draft->updated_at?->toISOString(),
        ]);
    }

    /**
     * Publish draft configuration to a new immutable version.
     *
     * POST /api/apps/{ulid}/config/publish
     */
    public function publish(Request $request, string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        /** @var AppConfig|null $draft */
        $draft = $app->configs()->where('state', 'draft')->latest()->first();

        if (! $draft) {
            return response()->json([
                'error' => [
                    'code'    => 'NO_DRAFT_CONFIG',
                    'message' => 'No draft configuration found to publish. Please save a draft first.',
                ],
            ], 400);
        }

        try {
            $validatedConfig = $this->validator->validate($draft->config);
        } catch (ConfigValidationException $e) {
            return response()->json([
                'error' => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors,
                ],
            ], 422);
        }

        // Idempotency check: if the latest published config has identical contents, return it without creating duplicate versions
        $latestPublished = $app->configs()->where('state', 'published')->latest('version')->first();
        if ($latestPublished && $latestPublished->config === $validatedConfig) {
            return response()->json([
                'message'      => 'Configuration already published.',
                'version'      => $latestPublished->version,
                'published_at' => $latestPublished->published_at?->toISOString(),
                'config'       => $latestPublished->config,
            ]);
        }

        $published = DB::transaction(function () use ($app, $validatedConfig, $request) {
            $currentMaxVersion = (int) ($app->configs()->where('state', 'published')->max('version') ?? 0);
            $nextVersion = $currentMaxVersion + 1;

            $published = AppConfig::create([
                'tenant_id'    => $app->tenant_id,
                'app_id'       => $app->id,
                'version'      => $nextVersion,
                'state'        => 'published',
                'config'       => $validatedConfig,
                'published_at' => now(),
            ]);

            // Advance setup step to Build to unlock integrations, payment, and build
            if ($app->setup_step->order() < SetupStep::Build->order()) {
                $app->update(['setup_step' => SetupStep::Build]);
            }

            AuditLog::create([
                'tenant_id'  => $app->tenant_id,
                'actor_id'   => $request->user()?->id,
                'action'     => 'config.published',
                'target'     => "app:{$app->ulid}",
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta'       => ['version' => $nextVersion],
            ]);

            return $published;
        });

        return response()->json([
            'message'      => 'Configuration published successfully.',
            'version'      => $published->version,
            'published_at' => $published->published_at?->toISOString(),
            'config'       => $published->config,
        ]);
    }

    /**
     * Return live preview data containing real products fetched from the store connector.
     *
     * GET /api/apps/{ulid}/preview-data
     */
    public function previewData(string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $connection = $app->connection;
        if (! $connection || $connection->status !== ConnectionStatus::Verified) {
            return response()->json([
                'error' => [
                    'code'    => 'CONNECTION_NOT_VERIFIED',
                    'message' => 'The store connection must be verified before live preview data can be loaded.',
                ],
            ], 400);
        }

        try {
            $connector = $this->connectorFactory->make($app);
            $paginated = $connector->getProducts(['per_page' => 20]);
            $categories = $connector->getCategories();
            $products = $paginated->toArray()['items'];

            // Find banner image candidate from products or categories
            $bannerImage = null;
            foreach ($products as $p) {
                if (! empty($p['images'][0])) {
                    $bannerImage = $p['images'][0];
                    break;
                }
            }

            $websiteLogo = null;
            if (! empty($connection->site_url)) {
                $siteClean = rtrim($connection->site_url, '/');
                $websiteLogo = "{$siteClean}/favicon.ico";
            }

            return response()->json([
                'store_name'   => $app->name,
                'store_url'    => $connection->site_url,
                'website_logo' => $websiteLogo,
                'banner_image' => $bannerImage,
                'currency'     => 'INR',
                'products'     => $products,
                'categories'   => $categories,
                'total'        => $paginated->totalItems,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'PREVIEW_DATA_FAILED',
                    'message' => 'Failed to load preview data from store: ' . $e->getMessage(),
                ],
            ], 502);
        }
    }
}
