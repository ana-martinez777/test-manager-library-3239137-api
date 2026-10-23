<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\Members\CreateMemberService;
use Library\Application\Services\Members\GetMemberService;
use Library\Application\Services\Members\SearchMembersService;

/** api-contract.md §3.5 (HU-05). No hay endpoint de borrado: los socios no se eliminan. */
final class MemberController
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function index(Request $request, SearchMembersService $service): JsonResponse
    {
        $query = Input::validate($request->query(), ['q' => ['sometimes', 'nullable', 'string']]);

        return new JsonResponse(Presenter::page($service->search($query['q'] ?? null, Input::page($request)), Presenter::member(...)), 200, [], self::JSON);
    }

    public function show(string $id, GetMemberService $service): JsonResponse
    {
        return new JsonResponse(Presenter::memberDetail($service->get($id)), 200, [], self::JSON);
    }

    public function store(Request $request, CreateMemberService $service): JsonResponse
    {
        $data = Input::validate($request->all(), ['full_name' => ['required', 'string'], 'document_number' => ['required', 'string']]);

        return new JsonResponse(Presenter::member($service->create($data['full_name'], $data['document_number'])), 201, [], self::JSON);
    }
}
