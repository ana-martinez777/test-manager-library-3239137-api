<?php

declare(strict_types=1);

namespace Library\Application\Services\Loans;

use Library\Application\Errors\NotFound;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\ConcurrencyConflict;
use Library\Application\Ports\LoanRepository;
use Library\Domain\Errors\ItemAlreadyReturned;
use Library\Domain\Model\LoanItem;

/** HU-07: devolver una línea a la vez; nunca suma dos veces (CA-07.2). */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly BookRepository $books,
        private readonly Clock $clock,
    ) {
    }

    public function returnItem(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw new NotFound('El préstamo no existe.');
        $item = $loan->item($itemId) ?? throw new NotFound('La línea no existe en este préstamo.');
        $book = $this->books->findAny($item->bookId) ?? throw new NotFound('El libro de la línea no existe.');

        $returned = $loan->returnItem($itemId, $book, $this->clock->now());

        try {
            $this->loans->saveReturn($loan, $itemId);
        } catch (ConcurrencyConflict) {
            throw new ItemAlreadyReturned("La línea de «{$item->bookTitle}» ya fue devuelta.");
        }

        return $returned;
    }
}
