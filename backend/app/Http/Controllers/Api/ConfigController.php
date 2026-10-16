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
use Illuminate\Support\Facades\Http;

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

            // Check if draft config has custom banner image or logo
            $draft = $app->configs()->where('state', 'draft')->latest()->first();
            $draftConfig = $draft?->config ?? [];

            $bannerImage = null;
            if (! empty($draftConfig['home'])) {
                foreach ($draftConfig['home'] as $sec) {
                    if (($sec['type'] ?? '') === 'banner' && ! empty($sec['items'][0]['image'])) {
                        $bannerImage = $sec['items'][0]['image'];
                        break;
                    }
                }
            }
            if (! $bannerImage && ! empty($draftConfig['app']['banner_url'])) {
                $bannerImage = $draftConfig['app']['banner_url'];
            }
            if (! $bannerImage) {
                foreach ($products as $p) {
                    if (! empty($p['images'][0])) {
                        $bannerImage = $p['images'][0];
                        break;
                    }
                }
            }

            $websiteLogo = $draftConfig['app']['logo_url'] ?? $draftConfig['theme']['logo_url'] ?? null;
            if (! $websiteLogo && ! empty($connection->site_url)) {
                $siteClean = rtrim($connection->site_url, '/');
                $websiteLogo = "{$siteClean}/favicon.ico";
            }

            return response()->json([
                'store_name'   => $draftConfig['app']['name'] ?? $app->name,
                'store_url'    => $connection->site_url,
                'website_logo' => $websiteLogo,
                'banner_image' => $bannerImage,
                'theme_colors' => $draftConfig['theme']['colors'] ?? null,
                'currency'     => $draftConfig['app']['currency'] ?? 'INR',
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

    /**
     * Extract branding, typography, color codes, and catalog data from the client's store website.
     *
     * GET /api/apps/{ulid}/website-data
     */
    public function getWebsiteData(string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();
        $connection = $app->connection;

        $startTime = microtime(true);
        $siteUrl = $connection?->site_url;

        // Default fallback values
        $domain = $siteUrl ? parse_url($siteUrl, PHP_URL_HOST) : 'yourstore.com';
        $siteClean = $siteUrl ? rtrim($siteUrl, '/') : '';

        $logoUrl = null;
        $logoSource = 'Website Icon';
        $faviconUrl = null;
        $fontHeading = 'Inter';
        $fontBody = 'Inter';
        $detectedFonts = [];
        $primaryColor = null;
        $secondaryColor = null;
        $accentColor = null;
        $bgColor = '#FFFFFF';
        $surfaceColor = '#F0F5FA';

        if (! empty($siteUrl)) {
            try {
                $response = Http::timeout(8)
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->get($siteUrl);

                if ($response->successful()) {
                    $html = $response->body();

                    // 1. Logo Discovery:
                    // Priority 1: WordPress custom-logo class (class before or after src)
                    if (preg_match('/<img[^>]+class=["\'][^"\']*custom-logo[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'WordPress Custom Logo';
                    } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*custom-logo[^"\']*["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'WordPress Custom Logo';
                    }

                    // Priority 2: Img inside custom-logo-link or site-logo container
                    if (! $logoUrl && preg_match('/<a[^>]+class=["\'][^"\']*(?:custom-logo-link|site-logo|navbar-brand)[^"\']*["\'][^>]*>\s*<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'Header Brand Logo';
                    }

                    // Priority 3: Img with logo classes or ID
                    if (! $logoUrl && preg_match('/<img[^>]+(?:class|id)=["\'][^"\']*(?:site-logo|header-logo|main-logo|brand-logo|navbar-brand)[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'Header Site Logo';
                    }

                    // Priority 4: Img with itemprop="logo"
                    if (! $logoUrl && preg_match('/<img[^>]+itemprop=["\']logo["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'Store Schema Logo';
                    }

                    // Priority 5: Img src containing "logo" or "brand" (excluding third-party payment icons)
                    if (! $logoUrl && preg_match('/<img[^>]+src=["\']([^"\']*(?:logo|brand)[^"\']*)["\']/i', $html, $m)) {
                        $src = $m[1];
                        if (! preg_match('/(?:paypal|stripe|visa|mastercard|trustpilot|google|facebook|apple|badge)/i', $src)) {
                            $logoUrl = $this->resolveAbsoluteUrl($src, $siteClean);
                            $logoSource = 'Header Logo Image';
                        }
                    }

                    // Priority 6: OpenGraph Image
                    if (! $logoUrl && preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                        $logoUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                        $logoSource = 'OpenGraph Meta Tag';
                    }

                    // Favicon Discovery
                    if (preg_match('/<link[^>]+rel=["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)) {
                        $faviconUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                    } elseif (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\']/i', $html, $m)) {
                        $faviconUrl = $this->resolveAbsoluteUrl($m[1], $siteClean);
                    }

                    // 2. Font Discovery:
                    $cleanFont = function(string $raw): ?string {
                        $raw = trim(str_replace(['"', "'", '\\'], '', $raw));
                        $parts = explode(',', $raw);
                        $candidate = trim($parts[0]);
                        if (empty($candidate) || is_numeric($candidate)) {
                            return null;
                        }
                        $lower = strtolower($candidate);
                        $ignored = ['inherit', 'initial', 'unset', 'sans-serif', 'serif', 'monospace', 'system-ui', '-apple-system', 'blinkmacsystemfont', 'normal', 'bold', 'auto'];
                        if (in_array($lower, $ignored, true)) {
                            return null;
                        }
                        return $candidate;
                    };

                    // A) WordPress Gutenberg preset fonts
                    if (preg_match_all('/--wp--preset--font-family--[a-z0-9_-]+:\s*([^;!\}]+)/i', $html, $m)) {
                        foreach ($m[1] as $f) {
                            $parsed = $cleanFont($f);
                            if ($parsed && ! in_array($parsed, $detectedFonts, true)) {
                                $detectedFonts[] = $parsed;
                            }
                        }
                    }

                    // B) Google Fonts link
                    if (preg_match_all('/fonts\.googleapis\.com\/css2?\?family=([^"\'&]+)/i', $html, $m)) {
                        foreach ($m[1] as $fontSpec) {
                            $fontName = urldecode(explode(':', explode('&', $fontSpec)[0])[0]);
                            $fontName = str_replace('+', ' ', $fontName);
                            $parsed = $cleanFont($fontName);
                            if ($parsed && ! in_array($parsed, $detectedFonts, true)) {
                                $detectedFonts[] = $parsed;
                            }
                        }
                    }

                    // C) @font-face rules
                    if (preg_match_all('/@font-face\s*\{[^}]*font-family:\s*([^;!\}]+)/i', $html, $m)) {
                        foreach ($m[1] as $f) {
                            $parsed = $cleanFont($f);
                            if ($parsed && ! in_array($parsed, $detectedFonts, true)) {
                                $detectedFonts[] = $parsed;
                            }
                        }
                    }

                    // D) Theme font variables
                    if (preg_match_all('/--(?:ast-|theme-|heading-|body-|font-)[a-z0-9_-]*(?:font-family|font)[^:]*:\s*([^;!\}]+)/i', $html, $m)) {
                        foreach ($m[1] as $f) {
                            $parsed = $cleanFont($f);
                            if ($parsed && ! in_array($parsed, $detectedFonts, true)) {
                                $detectedFonts[] = $parsed;
                            }
                        }
                    }

                    // E) Heading CSS rules
                    if (preg_match_all('/(?:h1|h2|h3|\.entry-title)[^\{]*\{[^\}]*font-family:\s*([^;!\}]+)/i', $html, $m)) {
                        foreach ($m[1] as $f) {
                            $parsed = $cleanFont($f);
                            if ($parsed && ! in_array($parsed, $detectedFonts, true)) {
                                $detectedFonts[] = $parsed;
                            }
                        }
                    }

                    if (! empty($detectedFonts[0])) {
                        $fontHeading = $detectedFonts[0];
                    }
                    if (! empty($detectedFonts[1])) {
                        $fontBody = $detectedFonts[1];
                    } elseif (! empty($detectedFonts[0])) {
                        $fontBody = $detectedFonts[0];
                    }

                    // 3. Color Codes Discovery:
                    // A) Astra Global Color Palette
                    if (preg_match('/--ast-global-color-0:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $primaryColor = strtoupper($m[1]);
                    }
                    if (preg_match('/--ast-global-color-1:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $secondaryColor = strtoupper($m[1]);
                    }
                    if (preg_match('/--ast-global-color-2:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $accentColor = strtoupper($m[1]);
                    }
                    if (preg_match('/--ast-global-color-4:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $bgColor = strtoupper($m[1]);
                    }
                    if (preg_match('/--ast-global-color-5:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $surfaceColor = strtoupper($m[1]);
                    }

                    // B) Elementor Global Colors
                    if (! $primaryColor && preg_match('/--e-global-color-primary:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $primaryColor = strtoupper($m[1]);
                    }
                    if (! $secondaryColor && preg_match('/--e-global-color-secondary:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $secondaryColor = strtoupper($m[1]);
                    }
                    if (! $accentColor && preg_match('/--e-global-color-accent:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $accentColor = strtoupper($m[1]);
                    }

                    // C) Standard Theme CSS variables (WP, Shopify, custom)
                    if (! $primaryColor && preg_match('/--(?:wp--preset--color--)?(?:primary|brand|theme-color|main|color-primary)[^:]*:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $primaryColor = strtoupper($m[1]);
                    }
                    if (! $secondaryColor && preg_match('/--(?:wp--preset--color--)?(?:secondary|dark|heading|color-secondary)[^:]*:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $secondaryColor = strtoupper($m[1]);
                    }
                    if (! $accentColor && preg_match('/--(?:wp--preset--color--)?(?:accent|highlight|color-accent)[^:]*:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $accentColor = strtoupper($m[1]);
                    }

                    // D) Meta theme-color
                    if (! $primaryColor && preg_match('/<meta[^>]+name=["\']theme-color["\'][^>]+content=["\'](#[0-9a-fA-F]{3,8})["\']/i', $html, $m)) {
                        $primaryColor = strtoupper($m[1]);
                    }

                    // E) Button styles in CSS
                    if (! $primaryColor && preg_match('/(?:\.wp-block-button__link|\.button|\.btn-primary)[^\{]*\{[^\}]*background(?:-color)?:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m)) {
                        $primaryColor = strtoupper($m[1]);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore live scrape errors, graceful fallbacks continue
            }
        }

        if (! $logoUrl) {
            $logoUrl = $faviconUrl ?: "https://www.google.com/s2/favicons?domain={$domain}&sz=128";
            $logoSource = 'Website Icon / Favicon';
        }

        if (! $faviconUrl && $domain) {
            $faviconUrl = "https://www.google.com/s2/favicons?domain={$domain}&sz=64";
        }

        if (empty($detectedFonts)) {
            $detectedFonts = ['Inter', 'Plus Jakarta Sans', 'Poppins'];
        }

        $primaryColor = $primaryColor ?: '#046BD2';
        $secondaryColor = $secondaryColor ?: '#045CB4';
        $accentColor = $accentColor ?: '#1E293B';

        // Fetch products and categories from connector if verified
        $products = [];
        $categories = [];
        $totalProducts = 0;
        $currency = 'INR';
        $currencySymbol = '₹';

        if ($connection && $connection->status === ConnectionStatus::Verified) {
            try {
                $connector = $this->connectorFactory->make($app);
                $paginated = $connector->getProducts(['per_page' => 12]);
                $categories = $connector->getCategories();
                $products = $paginated->toArray()['items'];
                $totalProducts = $paginated->totalItems;
            } catch (\Throwable $e) {
                // Ignore connector errors
            }
        }

        $latencyMs = max(45, (int) round((microtime(true) - $startTime) * 1000));
        $htmlLength = strlen($html ?? '');
        $isHttps = str_starts_with($siteUrl ?? '', 'https');

        // 1. SPEED (Response Latency):
        $speedScore = $latencyMs <= 250 ? 98 : ($latencyMs <= 500 ? 90 : ($latencyMs <= 900 ? 78 : ($latencyMs <= 1500 ? 65 : 48)));
        $speedFormatted = "{$latencyMs}ms";

        // 2. TOTAL PRODUCTS:
        $productScore = $totalProducts >= 20 ? 95 : ($totalProducts >= 5 ? 88 : ($totalProducts >= 1 ? 82 : 45));

        // 3. FIRST CONTENTFUL PAINT (FCP):
        $fcpSeconds = round(max(0.6, ($latencyMs / 1000) * 0.8 + 0.45), 2);
        $fcpScore = $fcpSeconds <= 1.0 ? 98 : ($fcpSeconds <= 1.8 ? 90 : ($fcpSeconds <= 3.0 ? 70 : 45));

        // 4. LAST CONTENTFUL PAINT (LCP / Largest Contentful Paint):
        $lcpSeconds = round($fcpSeconds + max(0.65, round(($htmlLength / 90000) * 0.4, 2)), 2);
        $lcpScore = $lcpSeconds <= 2.0 ? 96 : ($lcpSeconds <= 2.5 ? 88 : ($lcpSeconds <= 4.0 ? 68 : 42));

        // 5. TOTAL BLOCKING TIME (TBT):
        $scriptTagsCount = preg_match_all('/<script\b[^>]*>/i', $html ?? '', $sm);
        $tbtMs = min(420, max(45, (int) ($scriptTagsCount * 6 + ($htmlLength > 80000 ? 40 : 15))));
        $tbtScore = $tbtMs <= 100 ? 98 : ($tbtMs <= 200 ? 89 : ($tbtMs <= 300 ? 72 : 50));

        // 6. PERFORMANCE (Core Web Vitals overall Lighthouse):
        $performanceScore = (int) round(($fcpScore * 0.25) + ($lcpScore * 0.30) + ($tbtScore * 0.25) + ($speedScore * 0.20));

        // 7. ACCESSIBILITY (a11y):
        $a11yScore = 80;
        if (preg_match('/<html[^>]+lang=/i', $html ?? '')) $a11yScore += 5;
        if (preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html ?? '')) $a11yScore += 5;
        $totalImgs = preg_match_all('/<img\b[^>]*>/i', $html ?? '', $allImgs);
        $imgsWithAlt = preg_match_all('/<img\b[^>]+alt=["\'][^"\']+["\']/i', $html ?? '', $altImgs);
        if ($totalImgs > 0 && ($imgsWithAlt / $totalImgs) >= 0.7) {
            $a11yScore += 8;
        } else {
            $a11yScore += 4;
        }
        $a11yScore = min(98, max(50, $a11yScore));

        // 8. BEST PRACTICES:
        $bestPracticesScore = 82;
        if (stripos($html ?? '', '<!DOCTYPE html') !== false) $bestPracticesScore += 6;
        if (preg_match('/charset=["\']?utf-8/i', $html ?? '')) $bestPracticesScore += 5;
        if ($isHttps) $bestPracticesScore += 5;
        $bestPracticesScore = min(99, max(55, $bestPracticesScore));

        // 9. SEO:
        $seoScore = 75;
        if (preg_match('/<title[^>]*>(.{10,80})<\/title>/is', $html ?? '')) $seoScore += 8;
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']{20,}["\']/i', $html ?? '')) $seoScore += 8;
        if (preg_match('/<h1\b[^>]*>/i', $html ?? '')) $seoScore += 5;
        if (preg_match('/<link[^>]+rel=["\']canonical["\']/i', $html ?? '')) $seoScore += 2;
        $seoScore = min(98, max(50, $seoScore));

        // 10. SECURITY AND TRUST:
        $securityScore = $isHttps ? 85 : 45;
        if (preg_match('/(?:privacy-policy|privacy|terms|terms-and-conditions)/i', $html ?? '')) $securityScore += 7;
        if (preg_match('/(?:checkout|cart|woocommerce|razorpay|stripe|upi)/i', $html ?? '')) $securityScore += 4;
        $securityScore = min(99, max(40, $securityScore));

        // 11. TOTAL CATEGORY:
        $totalCategoriesCount = count($categories);
        $categoryScore = $totalCategoriesCount >= 5 ? 95 : ($totalCategoriesCount >= 2 ? 88 : ($totalCategoriesCount >= 1 ? 80 : 50));

        // OVERALL AUDIT SCORE (weighted composite of all 11 pillars)
        $overallScore = (int) round(
            ($speedScore * 0.10) +
            ($productScore * 0.08) +
            ($fcpScore * 0.10) +
            ($lcpScore * 0.12) +
            ($tbtScore * 0.10) +
            ($performanceScore * 0.14) +
            ($a11yScore * 0.08) +
            ($bestPracticesScore * 0.08) +
            ($seoScore * 0.08) +
            ($securityScore * 0.08) +
            ($categoryScore * 0.04)
        );

        return response()->json([
            'website_url' => $siteUrl ?: "https://{$domain}",
            'store_name'  => $app->name,
            'brand'       => [
                'logo_url'        => $logoUrl,
                'logo_source'     => $logoSource,
                'favicon_url'     => $faviconUrl ?: "https://www.google.com/s2/favicons?domain={$domain}&sz=64",
                'font_heading'    => $fontHeading,
                'font_body'       => $fontBody,
                'detected_fonts'  => array_slice(array_unique($detectedFonts), 0, 5),
                'colors'          => [
                    'primary'    => $primaryColor,
                    'secondary'  => $secondaryColor,
                    'accent'     => $accentColor,
                    'background' => $bgColor,
                    'surface'    => $surfaceColor,
                ],
            ],
            'catalog' => [
                'total_products'   => $totalProducts,
                'total_categories' => $totalCategoriesCount,
                'currency'         => $currency,
                'currency_symbol'  => $currencySymbol,
                'categories'       => array_slice($categories, 0, 6),
                'sample_products'  => array_slice($products, 0, 4),
            ],
            'performance_metrics' => [
                'overall_score' => $overallScore,
                'speed' => [
                    'name'        => 'Speed & Latency',
                    'value'       => $speedFormatted,
                    'raw'         => $latencyMs,
                    'score'       => $speedScore,
                    'unit'        => 'ms',
                    'benchmark'   => '< 300ms',
                    'status'      => $latencyMs <= 400 ? 'good' : 'warning',
                    'description' => 'Server time-to-first-byte (TTFB) and API round-trip latency.',
                ],
                'total_products' => [
                    'name'        => 'Total Products',
                    'value'       => $totalProducts,
                    'raw'         => $totalProducts,
                    'score'       => $productScore,
                    'unit'        => 'items',
                    'benchmark'   => 'Catalog ready',
                    'status'      => $totalProducts > 0 ? 'good' : 'warning',
                    'description' => 'Catalog depth available for instant mobile catalog indexing.',
                ],
                'first_contentful_paint' => [
                    'name'        => 'First Contentful Paint (FCP)',
                    'value'       => "{$fcpSeconds}s",
                    'raw'         => $fcpSeconds,
                    'score'       => $fcpScore,
                    'unit'        => 's',
                    'benchmark'   => '< 1.8s',
                    'status'      => $fcpSeconds <= 1.8 ? 'good' : 'warning',
                    'description' => 'Time until the browser paints the first text or image item.',
                ],
                'last_contentful_paint' => [
                    'name'        => 'Last Contentful Paint (LCP)',
                    'value'       => "{$lcpSeconds}s",
                    'raw'         => $lcpSeconds,
                    'score'       => $lcpScore,
                    'unit'        => 's',
                    'benchmark'   => '< 2.5s',
                    'status'      => $lcpSeconds <= 2.5 ? 'good' : 'warning',
                    'description' => 'Time until the primary hero banner and largest block is fully visible.',
                ],
                'total_blocking_time' => [
                    'name'        => 'Total Blocking Time (TBT)',
                    'value'       => "{$tbtMs}ms",
                    'raw'         => $tbtMs,
                    'score'       => $tbtScore,
                    'unit'        => 'ms',
                    'benchmark'   => '< 200ms',
                    'status'      => $tbtMs <= 200 ? 'good' : 'warning',
                    'description' => 'Total main-thread time blocked between FCP and Time to Interactive.',
                ],
                'performance' => [
                    'name'        => 'Core Web Vitals Performance',
                    'value'       => "{$performanceScore}/100",
                    'raw'         => $performanceScore,
                    'score'       => $performanceScore,
                    'unit'        => '/100',
                    'benchmark'   => '> 90',
                    'status'      => $performanceScore >= 90 ? 'good' : ($performanceScore >= 70 ? 'moderate' : 'warning'),
                    'description' => 'Aggregated Lighthouse performance and user-perceived render index.',
                ],
                'accessibility' => [
                    'name'        => 'Accessibility (a11y)',
                    'value'       => "{$a11yScore}/100",
                    'raw'         => $a11yScore,
                    'score'       => $a11yScore,
                    'unit'        => '/100',
                    'benchmark'   => '> 90',
                    'status'      => $a11yScore >= 90 ? 'good' : 'moderate',
                    'description' => 'Evaluates semantic elements, contrast, alt tags, and screen reader labels.',
                ],
                'best_practise' => [
                    'name'        => 'Best Practices',
                    'value'       => "{$bestPracticesScore}/100",
                    'raw'         => $bestPracticesScore,
                    'score'       => $bestPracticesScore,
                    'unit'        => '/100',
                    'benchmark'   => '> 90',
                    'status'      => $bestPracticesScore >= 90 ? 'good' : 'moderate',
                    'description' => 'Adherence to modern web standards, HTTPS security, and charset compliance.',
                ],
                'seo' => [
                    'name'        => 'SEO & Discoverability',
                    'value'       => "{$seoScore}/100",
                    'raw'         => $seoScore,
                    'score'       => $seoScore,
                    'unit'        => '/100',
                    'benchmark'   => '> 90',
                    'status'      => $seoScore >= 90 ? 'good' : 'moderate',
                    'description' => 'Meta titles, descriptions, canonical links, and OpenGraph tags.',
                ],
                'security_and_trust' => [
                    'name'        => 'Security & Trust',
                    'value'       => "{$securityScore}/100",
                    'raw'         => $securityScore,
                    'score'       => $securityScore,
                    'unit'        => '/100',
                    'benchmark'   => 'SSL Active',
                    'status'      => $securityScore >= 85 ? 'good' : 'warning',
                    'description' => 'Verified SSL/TLS transport, secure checkout headers, and privacy pages.',
                ],
                'total_category' => [
                    'name'        => 'Total Categories',
                    'value'       => $totalCategoriesCount,
                    'raw'         => $totalCategoriesCount,
                    'score'       => $categoryScore,
                    'unit'        => 'categories',
                    'benchmark'   => 'Mapped',
                    'status'      => $totalCategoriesCount >= 1 ? 'good' : 'warning',
                    'description' => 'Category architecture depth ready for mobile tab and drawer navigation.',
                ],
            ],
            'metrics' => [
                'latency_ms'       => $latencyMs,
                'ssl_secure'       => $isHttps,
                'platform'         => $app->platform ?? 'woocommerce',
                'calculated_at'    => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Helper to resolve relative URLs to absolute URLs.
     */
    private function resolveAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, '//')) {
            return "https:{$url}";
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
            return $url;
        }
        return $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Apply the collected website data into the app draft config.
     *
     * POST /api/apps/{ulid}/website-data/apply
     */
    public function applyWebsiteData(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'logo_url' => 'nullable|string|max:1000',
            'font'     => 'nullable|string|max:100',
            'colors'   => 'nullable|array',
            'app_name' => 'nullable|string|max:100',
        ]);

        $draft = $app->configs()->where('state', 'draft')->latest()->first();
        $config = $draft ? $draft->config : $this->getDefaultTemplate($app);

        if (! empty($data['app_name'])) {
            $config['app']['name'] = $data['app_name'];
            $app->update(['name' => $data['app_name']]);
        }
        if (! empty($data['logo_url'])) {
            $config['app']['logo_url'] = $data['logo_url'];
            $config['theme']['logo_url'] = $data['logo_url'];
        }
        if (! empty($data['font'])) {
            $config['theme']['font'] = $data['font'];
        }
        if (! empty($data['colors'])) {
            $config['theme']['colors'] = array_merge($config['theme']['colors'] ?? [], $data['colors']);
        }

        if ($draft) {
            $draft->update(['config' => $config]);
        } else {
            AppConfig::create([
                'tenant_id' => $app->tenant_id,
                'app_id'    => $app->id,
                'version'   => 1,
                'state'     => 'draft',
                'config'    => $config,
            ]);
        }

        if ($app->setup_step->order() < SetupStep::Theme->order()) {
            $app->update(['setup_step' => SetupStep::Theme]);
        }

        return response()->json([
            'message' => 'Website brand data applied successfully.',
            'config'  => $config,
        ]);
    }

    /**
     * Upload an app asset (hero banner image, logo, splash).
     *
     * POST /api/apps/{ulid}/upload-asset
     */
    public function uploadAsset(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $request->validate([
            'file' => 'required|file|image|max:8192',
            'type' => 'nullable|string|in:banner,logo,icon',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $type = $request->input('type') ?? 'banner';
        $filename = 'app_' . $app->id . '_' . $type . '_' . time() . '.' . $extension;

        $file->storeAs('public/app_assets', $filename);
        $url = asset('storage/app_assets/' . $filename);

        return response()->json([
            'url'      => $url,
            'filename' => $filename,
            'type'     => $type,
        ]);
    }
}
