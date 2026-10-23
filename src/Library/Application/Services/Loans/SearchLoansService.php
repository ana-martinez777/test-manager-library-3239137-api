<?php

declare(strict_types=1);

namespace Library\Application\Services\Loans;

use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\ReadModels\Page;
use Library\Application\ReadModels\PageRequest;

/** HU-08 · CA-08.2: por rango, estado y socio, fecha descendente, paginado. */
final class SearchLoansService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    public function search(LoanSearchCriteria $criteria, PageRequest $page): Page
    {
        return $this->loans->search($criteria, $this->clock->now(), $page->page, $page->size);
    }
}
