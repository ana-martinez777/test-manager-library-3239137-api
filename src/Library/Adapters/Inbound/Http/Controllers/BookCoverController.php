<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Services\Books\GetCoverService;
use Library\Application\Services\Books\RemoveCoverService;
use Library\Application\Services\Books\UploadCoverService;
use Library\Domain\Errors\ValidationFailed;

/** PUT/DELETE/GET /books/{id}/cover (CA-03.4). El tipo se valida por contenido en el caso de uso. */
final class BookCoverController
{
    public function upload(Request $request, string $id, UploadCoverService $service): JsonResponse
    {
        $view = $service->upload($id, self::uploadedBytes($request));

        return new JsonResponse(Presenter::book($view), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function remove(string $id, RemoveCoverService $service): Response
    {
        $service->remove($id);

        return new Response(null, 204);
    }

    public function show(string $id, GetCoverService $service): Response
    {
        $cover = $service->get($id);

        return new Response($cover->bytes, 200, ['Content-Type' => $cover->mimeType, 'Cache-Control' => 'private, max-age=60']);
    }

    /**
     * PHP solo procesa multipart en POST. Para un PUT multipart real se usa request_parse_body()
     * (PHP 8.4). También funciona un POST con _method=PUT desde formularios HTML.
     */
    private static function uploadedBytes(Request $request): string
    {
        $file = $request->file('file');
        if ($file !== null) {
            if (!$file->isValid()) {
                throw self::invalid($file->getError());
            }

            return (string) file_get_contents($file->getRealPath());
        }

        if (function_exists('request_parse_body') && !$request->isMethod('POST')) {
            try {
                [, $files] = request_parse_body();
            } catch (\Throwable) {
                $files = [];
            }
            $raw = $files['file'] ?? null;
            if (is_array($raw) && isset($raw['error'])) {
                if ($raw['error'] !== UPLOAD_ERR_OK) {
                    throw self::invalid((int) $raw['error']);
                }

                return (string) file_get_contents($raw['tmp_name']);
            }
        }

        throw new ValidationFailed('Debes enviar la portada en el campo file (multipart/form-data).', [['field' => 'file', 'issue' => 'es obligatorio']]);
    }

    private static function invalid(int $error): ValidationFailed
    {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return new ValidationFailed('La portada supera el máximo de 2 MB.', [['field' => 'file', 'issue' => 'máximo 2 MB']]);
        }

        return new ValidationFailed('No se pudo recibir el archivo.', [['field' => 'file', 'issue' => 'archivo inválido']]);
    }
}
