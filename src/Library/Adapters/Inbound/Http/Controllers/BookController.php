<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\Books\CreateBookService;
use Library\Application\Services\Books\DeleteBookService;
use Library\Application\Services\Books\GetBookService;
use Library\Application\Services\Books\SearchBooksService;
use Library\Application\Services\Books\UpdateBookService;

/** api-contract.md §3.4 (HU-03 y HU-04). Solo traduce HTTP ↔ casos de uso. */
final class BookController
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const BODY_RULES = [
        'title' => ['required', 'string'],
        'author' => ['required', 'string'],
        'category_id' => ['required', 'uuid'],
        'total_copies' => ['required', 'integer'],
    ];

    public function index(Request $request, SearchBooksService $service): JsonResponse
    {
        $query = Input::validate($request->query(), ['q' => ['sometimes', 'nullable', 'string'], 'category_id' => ['sometimes', 'nullable', 'uuid']]);
        $page = $service->search($query['q'] ?? null, $query['category_id'] ?? null, Input::page($request));

        return new JsonResponse(Presenter::page($page, Presenter::book(...)), 200, [], self::JSON);
    }

    public function show(string $id, GetBookService $service): JsonResponse
    {
        return new JsonResponse(Presenter::book($service->get($id)), 200, [], self::JSON);
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $data = Input::validate($request->all(), self::BODY_RULES + ['isbn' => ['required', 'string']]);
        $view = $service->create($data['title'], $data['author'], $data['isbn'], $data['category_id'], (int) $data['total_copies']);

        return new JsonResponse(Presenter::book($view), 201, [], self::JSON);
    }

    public function update(Request $request, string $id, UpdateBookService $service): JsonResponse
    {
        $data = Input::validate($request->all(), self::BODY_RULES);
        $view = $service->update($id, $data['title'], $data['author'], $data['category_id'], (int) $data['total_copies']);

        return new JsonResponse(Presenter::book($view), 200, [], self::JSON);
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->delete($id);

        return new Response(null, 204);
    }
}
