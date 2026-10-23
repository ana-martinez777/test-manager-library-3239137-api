<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;
use Library\Application\ReadModels\BookView;
use Library\Domain\Errors\ValidationFailed;

/**
 * CA-03.4 y api-contract.md §4: solo JPG o PNG, máximo 2 MB, tipo validado por CONTENIDO.
 */
final class UploadCoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
        private readonly BookViews $views,
    ) {
    }

    public function upload(string $bookId, string $bytes): BookView
    {
        $book = $this->books->findActive($bookId) ?? throw new NotFound('El libro no existe.');

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new ValidationFailed('La portada supera el máximo de 2 MB.', [['field' => 'file', 'issue' => 'máximo 2 MB']]);
        }
        $extension = self::detectExtension($bytes)
            ?? throw new ValidationFailed('La portada debe ser una imagen JPG o PNG.', [['field' => 'file', 'issue' => 'solo JPG o PNG']]);

        $previousKey = $book->coverKey();
        $newKey = $this->storage->put($bytes, $extension);
        $book->setCover($newKey);
        try {
            // Se guarda primero la clave; el binario anterior se borra después (data-model.md §7).
            $this->books->updateCover($book);
        } catch (\Throwable $e) {
            $this->storage->delete($newKey);
            throw $e;
        }
        if ($previousKey !== null && $previousKey !== $newKey) {
            $this->storage->delete($previousKey);
        }

        return $this->views->of($book);
    }


    /** Firma de bytes: JPEG empieza con FF D8 FF; PNG con 89 50 4E 47 0D 0A 1A 0A. */
    public static function detectExtension(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }

        return null;
    }
}
