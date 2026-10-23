<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * T-18 · Bajar a la base las invariantes que en data-model.md §4 figuran como «pendiente (T-18)».
 * Al aplicar esta migración, data-model.md §4 debe pasar esas dos filas de «pendiente» a «motor».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE library.book
    ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1);
ALTER TABLE library."user"
    ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'));
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE library.book DROP CONSTRAINT IF EXISTS ck_book_total_positive;
ALTER TABLE library."user" DROP CONSTRAINT IF EXISTS ck_user_role;
SQL);
    }
};
