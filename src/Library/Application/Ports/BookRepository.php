<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\ReadModels\Page;
use Library\Domain\Model\Book;

interface BookRepository
{
    /** Libro activo por id (los dados de baja no se devuelven). */
    public function findActive(string $id): ?Book;

    /** Libro por id, incluidos los dados de baja (para devoluciones). */
    public function findAny(string $id): ?Book;

    /**
     * Q3: libros activos por lote.
     *
     * @param list<string> $ids
     * @return array<string, Book> indexado por id
     */
    public function findActiveByIds(array $ids): array;

    public function existsActiveIsbn(string $isbn): bool;

    /** Q1: búsqueda paginada de libros activos, orden por título. @return Page<Book> */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;

    /** Inserta un libro nuevo. Lanza IsbnTaken si choca con el índice único parcial. */
    public function add(Book $book): void;

    /**
     * Guarda título, autor, categoría y total. Los disponibles se ajustan en el motor en
     * $copiesDifference de forma atómica. Lanza ConcurrencyConflict si quedarían < 0.
     */
    public function update(Book $book, int $copiesDifference): void;

    /** Guarda la baja lógica. Lanza ConcurrencyConflict si ya no estaba activo. */
    public function markDeleted(Book $book): void;

    public function updateCover(Book $book): void;
}
