<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\App;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    /**
     * Returns the runtime app configuration for the white-label Flutter client.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        $activeConfig = $app->configs()
            ->where('state', 'published')
            ->latest('version')
            ->first() ?? $app->configs()->latest('version')->first();

        $rawConfig = $activeConfig ? ($activeConfig->config ?? $activeConfig->config_data ?? []) : [];

        // Determine allowed webview hosts from store connection
        $allowedHosts = [];
        if ($app->connection && ! empty($app->connection->site_url)) {
            $parsedHost = parse_url($app->connection->site_url, PHP_URL_HOST);
            if (! empty($parsedHost)) {
                $allowedHosts[] = strtolower($parsedHost);
            }
        }
        $allowedHosts = array_merge($allowedHosts, [
            'api.razorpay.com',
            'checkout.razorpay.com',
            'api.stripe.com',
            'checkout.stripe.com',
        ]);

        $defaultConfig = [
            'schema_version' => 1,
            'config_version' => $activeConfig ? $activeConfig->version : 1,
            'app' => [
                'name'        => $app->name,
                'currency'    => $rawConfig['app']['currency'] ?? 'USD',
                'min_version' => $rawConfig['app']['min_version'] ?? '1.0.0',
                'maintenance' => (bool) ($rawConfig['app']['maintenance'] ?? false),
            ],
            'theme' => $rawConfig['theme'] ?? [
                'layout' => 'theme_a',
                'colors' => [
                    'primary'    => '#4F46E5',
                    'secondary'  => '#1E293B',
                    'background' => '#FFFFFF',
                    'surface'    => '#F8FAFC',
                    'text'       => '#0F172A',
                ],
                'radius' => 12,
                'font'   => 'Poppins',
                'nav'    => 'bottom',
            ],
            'features' => $rawConfig['features'] ?? [
                'wishlist'      => true,
                'otp_login'     => true,
                'google_login'  => true,
                'notifications' => true,
                'reviews'       => false,
            ],
            'home' => $rawConfig['home'] ?? [
                ['type' => 'categories'],
                ['type' => 'product_rail', 'title' => 'Trending Products', 'source' => 'recent'],
            ],
            'links' => $rawConfig['links'] ?? [
                'privacy' => 'https://webintoapp.com/privacy',
                'terms'   => 'https://webintoapp.com/terms',
            ],
            'webview_allowed_hosts' => array_values(array_unique($allowedHosts)),
        ];

        return response()->json($defaultConfig);
    }
}
