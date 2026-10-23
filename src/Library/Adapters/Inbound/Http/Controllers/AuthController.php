<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Application\Services\Auth\CreateLibrarianService;
use Library\Application\Services\Auth\LoginService;
use Library\Adapters\Inbound\Http\Presenter;

final class AuthController
{
    /** POST /auth/login (HU-01). */
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = Input::validate($request->all(), ['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $result = $service->login($data['username'], $data['password']);

        return new JsonResponse([
            'access_token' => $result->token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->token->expiresIn,
            'role' => $result->role,
        ]);
    }

    /** POST /users (HU-02, solo admin). La respuesta nunca incluye la clave ni su hash. */
    public function createUser(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = Input::validate($request->all(), [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ]);

        return new JsonResponse(Presenter::user($service->create($data['username'], $data['password'], $data['role'])), 201);
    }
}
