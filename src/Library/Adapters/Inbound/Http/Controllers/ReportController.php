<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Adapters\Inbound\Http\Input;
use Library\Application\ReadModels\MostBorrowedRow;
use Library\Application\Services\Reports\MostBorrowedReportService;

/** GET /reports/most-borrowed (HU-10, solo admin). Sin socios ni bibliotecarios. */
final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        $query = Input::validate($request->query(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $report = $service->generate(Input::date($query['from']), Input::date($query['to']));

        return new JsonResponse([
            'from' => $report->range->from->format('Y-m-d'),
            'to' => $report->range->to->format('Y-m-d'),
            'rows' => array_map(static fn (MostBorrowedRow $row) => [
                'book_id' => $row->bookId,
                'book_title' => $row->bookTitle,
                'category_name' => $row->categoryName,
                'times_borrowed' => $row->timesBorrowed,
            ], $report->rows),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
