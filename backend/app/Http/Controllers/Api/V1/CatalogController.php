<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Services\Connectors\ConnectorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly ConnectorFactory $connectorFactory
    ) {}

    public function products(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        if (! $app->connection) {
            return response()->json(['data' => []]);
        }

        $connector = $this->connectorFactory->make($app->connection);
        $paginated = $connector->getProducts([
            'page'     => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 12),
            'search'   => $request->input('search'),
            'category' => $request->input('category'),
        ]);

        return response()->json([
            'data'         => array_map(fn ($p) => $p->toArray(), $paginated->items),
            'total'        => $paginated->total,
            'current_page' => $paginated->currentPage,
            'total_pages'  => $paginated->totalPages,
        ]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        if (! $app->connection) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Store connection not found.'],
            ], 404);
        }

        try {
            $connector = $this->connectorFactory->make($app->connection);
            $product = $connector->getProduct($id);
            return response()->json(['data' => $product->toArray()]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Product not found.'],
            ], 404);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        /** @var App $app */
        $app = $request->attributes->get('resolved_app');

        if (! $app->connection) {
            return response()->json(['data' => []]);
        }

        $connector = $this->connectorFactory->make($app->connection);
        $categories = $connector->getCategories();

        return response()->json(['data' => $categories]);
    }
}
