<?php

/*
 * Rutas de api-contract.md §3. El prefijo /api/v1 se define en bootstrap/app.php.
 * Solo POST /auth/login y GET /health son públicas (CA-01.3).
 * whereUuid: un id con formato inválido responde 404 en vez de llegar a la base.
 */

use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\Http\Controllers\AuthController;
use Library\Adapters\Inbound\Http\Controllers\BookController;
use Library\Adapters\Inbound\Http\Controllers\BookCoverController;
use Library\Adapters\Inbound\Http\Controllers\CategoryController;
use Library\Adapters\Inbound\Http\Controllers\HealthController;
use Library\Adapters\Inbound\Http\Controllers\LoanController;
use Library\Adapters\Inbound\Http\Controllers\MemberController;
use Library\Adapters\Inbound\Http\Controllers\ReportController;

// 3.1 Públicas
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.token')->group(function (): void {
    // 3.2 Usuarios
    Route::post('/users', [AuthController::class, 'createUser'])->middleware('role:admin');

    // 3.3 Categorías
    Route::get('/categories', [CategoryController::class, 'index']);

    // 3.4 Libros: lectura para cualquier autenticado; escritura solo admin
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::get('/books/{id}/cover', [BookCoverController::class, 'show'])->whereUuid('id');
    Route::middleware('role:admin')->group(function (): void {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
        Route::delete('/books/{id}', [BookController::class, 'destroy'])->whereUuid('id');
        Route::put('/books/{id}/cover', [BookCoverController::class, 'upload'])->whereUuid('id');
        Route::delete('/books/{id}/cover', [BookCoverController::class, 'remove'])->whereUuid('id');
    });

    Route::middleware('role:admin,librarian')->group(function (): void {
        // 3.5 Socios
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
        Route::post('/members', [MemberController::class, 'store']);

        // 3.6 Préstamos (overdue va antes de {id})
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem'])
            ->whereUuid(['loanId', 'itemId']);
    });

    // 3.7 Reporte
    Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed'])->middleware('role:admin');
});
