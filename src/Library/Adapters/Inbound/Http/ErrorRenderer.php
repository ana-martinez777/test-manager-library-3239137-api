<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\JsonResponse;
use Library\Domain\Errors\DomainError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * El ÚNICO lugar donde un error se traduce al cuerpo y al estado del contrato
 * (architecture.md §5, api-contract.md §1-2, RNF-4). Nunca incluye trazas, SQL ni rutas.
 */
final class ErrorRenderer
{
    private const STATUS_BY_CODE = [
        'INVALID_CREDENTIALS' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function render(\Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            return self::json(self::STATUS_BY_CODE[$e->errorCode()] ?? 422, $e->errorCode(), $e->getMessage(), $e->details());
        }

        if ($e instanceof HttpExceptionInterface) {
            return match ($e->getStatusCode()) {
                401 => self::json(401, 'INVALID_CREDENTIALS', 'Se requiere un token de acceso válido.'),
                403 => self::json(403, 'FORBIDDEN_ROLE', 'Tu rol no tiene permiso para esta operación.'),
                404 => self::json(404, 'NOT_FOUND', 'El recurso solicitado no existe.'),
                405 => self::json(405, 'METHOD_NOT_ALLOWED', 'Método no permitido para esta ruta.'),
                413 => self::json(422, 'VALIDATION_ERROR', 'El archivo enviado es demasiado grande.', [['field' => 'file', 'issue' => 'máximo 2 MB']]),
                default => self::json($e->getStatusCode(), 'HTTP_ERROR', 'La solicitud no se pudo procesar.'),
            };
        }

        // Cualquier otro error (base de datos, configuración...) sale genérico: sin detalles internos.
        return self::json(500, 'INTERNAL_ERROR', 'Ocurrió un error interno. Intenta de nuevo más tarde.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function json(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(
            ['code' => $code, 'message' => $message, 'details' => array_values($details)],
            $status,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
