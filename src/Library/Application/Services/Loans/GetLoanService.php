<?php

declare(strict_types=1);

namespace Library\Application\Services\Loans;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\LoanRepository;
use Library\Domain\Model\Loan;

/** HU-08 · CA-08.1. */
final class GetLoanService
{
    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function get(string $id): Loan
    {
        return $this->loans->find($id) ?? throw new NotFound('El préstamo no existe.');
    }
}
