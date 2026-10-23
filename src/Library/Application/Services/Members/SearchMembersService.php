<?php

declare(strict_types=1);

namespace Library\Application\Services\Members;

use Library\Application\Ports\MemberRepository;
use Library\Application\ReadModels\Page;
use Library\Application\ReadModels\PageRequest;

/** HU-05 · CA-05.2: lista y busca por nombre o documento, paginado. */
final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function search(?string $text, PageRequest $page): Page
    {
        $text = $text !== null ? trim($text) : null;

        return $this->members->search($text === '' ? null : $text, $page->page, $page->size);
    }
}
