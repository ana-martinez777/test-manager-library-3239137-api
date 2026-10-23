<?php

declare(strict_types=1);

namespace Library\Application\Services\Books;

use Library\Application\Ports\BookRepository;
use Library\Application\ReadModels\Page;
use Library\Application\ReadModels\PageRequest;

/** HU-04 · CA-04.1 y CA-04.2. */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books, private readonly BookViews $views)
    {
    }

    public function search(?string $text, ?string $categoryId, PageRequest $page): Page
    {
        $text = $text !== null ? trim($text) : null;

        return $this->books
            ->search($text === '' ? null : $text, $categoryId, $page->page, $page->size)
            ->map($this->views->of(...));
    }
}
