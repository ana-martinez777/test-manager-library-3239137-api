<?php

declare(strict_types=1);

namespace Library\Domain\Model;

use DateTimeImmutable;
use Library\Domain\Errors\BookNotAvailable;
use Library\Domain\Errors\ValidationFailed;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Library\Domain\ValueObjects\Text;

/**
 * Agregado Libro (architecture.md §3).
 * Invariantes: 0 <= disponibles <= total; total >= 1; título y autor no vacíos.
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        private readonly Isbn $isbn,
        private string $categoryId,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    /** CA-03.1: los disponibles arrancan iguales al total. */
    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, Quantity $total): self
    {
        return new self(
            $id,
            Text::required($title, 'title', 250),
            Text::required($author, 'author', 200),
            $isbn,
            $categoryId,
            $total->value,
            $total->value,
            null,
            null,
        );
    }

    /** Reconstruye un libro ya guardado (lo usa la persistencia). */
    public static function rehydrate(
        string $id,
        string $title,
        string $author,
        string $isbn,
        string $categoryId,
        int $totalCopies,
        int $availableCopies,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self($id, $title, $author, Isbn::fromString($isbn), $categoryId, $totalCopies, $availableCopies, $coverKey, $deletedAt);
    }

    /**
     * CA-03.3: reemplaza título, autor, categoría y total. Los disponibles se ajustan en la misma
     * diferencia y nunca quedan por debajo de 0. Devuelve la diferencia aplicada al total.
     */
    public function update(string $title, string $author, string $categoryId, Quantity $total): int
    {
        $difference = $total->value - $this->totalCopies;
        $newAvailable = $this->availableCopies + $difference;
        if ($newAvailable < 0) {
            $lent = $this->totalCopies - $this->availableCopies;
            throw new ValidationFailed(
                "El total no puede ser menor que los ejemplares prestados ({$lent}).",
                [['field' => 'total_copies', 'issue' => "debe ser al menos {$lent}"]]
            );
        }

        $this->title = Text::required($title, 'title', 250);
        $this->author = Text::required($author, 'author', 200);
        $this->categoryId = $categoryId;
        $this->totalCopies = $total->value;
        $this->availableCopies = $newAvailable;

        return $difference;
    }

    /** Descuenta un ejemplar al prestarlo. Nunca deja disponibles < 0. */
    public function withdraw(): void
    {
        if ($this->availableCopies <= 0) {
            throw new BookNotAvailable("No quedan ejemplares disponibles de «{$this->title}».");
        }
        $this->availableCopies--;
    }

    /** Suma un ejemplar al devolverlo. Nunca supera el total. */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new \LogicException('Los ejemplares disponibles no pueden superar el total.');
        }
        $this->availableCopies++;
    }

    /** CA-03.5: baja lógica. Que no tenga préstamos abiertos lo comprueba el caso de uso. */
    public function delete(DateTimeImmutable $at): void
    {
        if ($this->deletedAt === null) {
            $this->deletedAt = $at;
        }
    }

    /** CA-03.4: clave opaca; nunca cadena vacía. */
    public function setCover(string $coverKey): void
    {
        if (trim($coverKey) === '') {
            throw new \InvalidArgumentException('La clave de portada no puede estar vacía.');
        }
        $this->coverKey = $coverKey;
    }

    /** Quita la portada y devuelve la clave anterior (para borrar el binario después). */
    public function removeCover(): ?string
    {
        $previous = $this->coverKey;
        $this->coverKey = null;

        return $previous;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
