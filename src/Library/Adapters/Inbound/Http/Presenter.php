<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use Library\Application\ReadModels\BookView;
use Library\Application\ReadModels\MemberView;
use Library\Application\ReadModels\OverdueLine;
use Library\Application\ReadModels\Page;
use Library\Domain\Model\Category;
use Library\Domain\Model\Loan;
use Library\Domain\Model\LoanItem;
use Library\Domain\Model\Member;
use Library\Domain\Model\User;

/** Convierte objetos del núcleo en la forma JSON de api-contract.md (snake_case, fechas UTC con Z). */
final class Presenter
{
    public static function instant(?\DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @param callable(mixed): array<string, mixed> $item */
    public static function page(Page $page, callable $item): array
    {
        return ['items' => array_map($item, $page->items), 'page' => $page->page, 'size' => $page->size, 'total' => $page->total];
    }

    public static function user(User $user): array
    {
        return ['id' => $user->id, 'username' => $user->username, 'role' => $user->role];
    }

    public static function category(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name];
    }

    public static function book(BookView $view): array
    {
        $book = $view->book;

        return [
            'id' => $book->id,
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value,
            'category' => self::category($view->category),
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
        ];
    }

    public static function member(Member $member): array
    {
        return ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber->value];
    }

    public static function memberDetail(MemberView $view): array
    {
        return self::member($view->member) + ['active_loans' => $view->activeLoans];
    }

    public static function loan(Loan $loan, \DateTimeImmutable $now): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => self::instant($loan->loanedAt),
            'status' => $loan->status($now),
            'items' => array_map(self::item(...), $loan->items()),
        ];
    }

    public static function item(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'book_id' => $item->bookId,
            'book_title' => $item->bookTitle,
            'isbn' => $item->isbn,
            'category_name' => $item->categoryName,
            'due_at' => self::instant($item->dueAt),
            'returned_at' => self::instant($item->returnedAt()),
        ];
    }

    public static function overdue(OverdueLine $line): array
    {
        return [
            'loan_id' => $line->loanId,
            'item_id' => $line->itemId,
            'book_title' => $line->bookTitle,
            'member_name' => $line->memberName,
            'due_at' => self::instant($line->dueAt),
            'days_late' => $line->daysLate,
        ];
    }
}
