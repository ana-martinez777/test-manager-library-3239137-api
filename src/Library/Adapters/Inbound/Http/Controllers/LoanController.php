<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Adapters\Inbound\Http\Presenter;
use Library\Application\Ports\Clock;
use Library\Application\ReadModels\LoanSearchCriteria;
use Library\Application\Services\Loans\GetLoanService;
use Library\Application\Services\Loans\LendBooksService;
use Library\Application\Services\Loans\OverdueService;
use Library\Application\Services\Loans\ReturnItemService;
use Library\Application\Services\Loans\SearchLoansService;

/** api-contract.md §3.6 (HU-06 a HU-09). No hay PUT ni DELETE: los préstamos no se editan ni se borran. */
final class LoanController
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(private readonly Clock $clock)
    {
    }

    public function store(Request $request, LendBooksService $service): JsonResponse
    {
        $data = Input::validate($request->all(), [
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);
        $bookIds = array_map(static fn (array $item) => strtolower($item['book_id']), $data['items']);
        $loan = $service->lend(strtolower($data['member_id']), $bookIds, Input::userId($request));

        return new JsonResponse(Presenter::loan($loan, $this->clock->now()), 201, [], self::JSON);
    }

    public function index(Request $request, SearchLoansService $service): JsonResponse
    {
        $query = Input::validate($request->query(), [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'nullable', 'in:open,overdue,closed'],
            'member_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $criteria = LoanSearchCriteria::of(
            Input::date($query['from'] ?? null),
            Input::date($query['to'] ?? null),
            $query['status'] ?? null,
            isset($query['member_id']) ? strtolower($query['member_id']) : null,
        );
        $now = $this->clock->now();

        return new JsonResponse(
            Presenter::page($service->search($criteria, Input::page($request)), fn ($loan) => Presenter::loan($loan, $now)),
            200,
            [],
            self::JSON,
        );
    }

    public function show(string $id, GetLoanService $service): JsonResponse
    {
        return new JsonResponse(Presenter::loan($service->get($id), $this->clock->now()), 200, [], self::JSON);
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return new JsonResponse(Presenter::item($service->returnItem($loanId, $itemId)), 200, [], self::JSON);
    }

    public function overdue(OverdueService $service): JsonResponse
    {
        return new JsonResponse(['items' => array_map(Presenter::overdue(...), $service->list())], 200, [], self::JSON);
    }
}
