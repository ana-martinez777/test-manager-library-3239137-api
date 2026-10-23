<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Library\Application\ReadModels\PageRequest;
use Library\Domain\Errors\ValidationFailed;

/**
 * Validación de FORMA de la entrada (tipos, obligatorios). Las reglas de negocio las valida el
 * dominio. Los mensajes van en español y el error sale con el cuerpo único (VALIDATION_ERROR).
 */
final class Input
{
    private const MESSAGES = [
        'required' => 'es obligatorio',
        'present' => 'es obligatorio',
        'string' => 'debe ser texto',
        'integer' => 'debe ser un número entero',
        'uuid' => 'debe ser un UUID válido',
        'array' => 'debe ser una lista',
        'min' => 'debe ser como mínimo :min',
        'max' => 'debe ser como máximo :max',
        'in' => 'tiene un valor no permitido',
        'date_format' => 'debe tener el formato AAAA-MM-DD',
        'file' => 'debe ser un archivo',
    ];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules, self::MESSAGES);
        if ($validator->fails()) {
            $details = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $details[] = ['field' => (string) $field, 'issue' => $messages[0]];
            }
            throw new ValidationFailed('Los datos enviados no son válidos.', $details);
        }

        return $validator->validated();
    }

    public static function page(Request $request): PageRequest
    {
        $query = self::validate($request->query(), [
            'page' => ['sometimes', 'integer'],
            'size' => ['sometimes', 'integer'],
        ]);

        return PageRequest::of((int) ($query['page'] ?? 1), (int) ($query['size'] ?? 20));
    }

    public static function date(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC')) ?: null;
    }

    /** Usuario autenticado, puesto por AuthenticateToken. */
    public static function userId(Request $request): string
    {
        return (string) $request->attributes->get('auth.user_id');
    }
}
