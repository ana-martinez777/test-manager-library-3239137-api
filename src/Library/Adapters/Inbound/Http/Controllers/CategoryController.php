<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\Categories\ListCategoriesService;

final class CategoryController
{
    /** GET /categories: las cinco categorías por nombre. */
    public function index(ListCategoriesService $service): JsonResponse
    {
        return new JsonResponse(array_map(Presenter::category(...), $service->list()), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
