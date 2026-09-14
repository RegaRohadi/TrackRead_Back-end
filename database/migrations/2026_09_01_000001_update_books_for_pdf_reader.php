<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE books DROP CONSTRAINT IF EXISTS books_isbn_unique');
        DB::statement('DROP INDEX IF EXISTS books_isbn_unique');

        if (Schema::hasColumn('books', 'isbn')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('isbn');
            });
        }

        DB::statement('ALTER TABLE books DROP CONSTRAINT IF EXISTS books_user_id_foreign');
        DB::statement('ALTER TABLE books ALTER COLUMN user_id DROP NOT NULL');

        $fkExists = DB::selectOne("SELECT 1 FROM information_schema.table_constraints WHERE table_name='books' AND constraint_name='books_user_id_foreign' AND constraint_type='FOREIGN KEY'");
        if (!$fkExists) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('books', 'source')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('source')->default('upload');
            });
        }
        if (!Schema::hasColumn('books', 'pdf_path')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('pdf_path')->nullable();
            });
        }
        if (!Schema::hasColumn('books', 'pdf_original_name')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('pdf_original_name')->nullable();
            });
        }
        if (!Schema::hasColumn('books', 'pdf_mime')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('pdf_mime')->nullable()->default('application/pdf');
            });
        }
        if (!Schema::hasColumn('books', 'pdf_size_bytes')) {
            Schema::table('books', function (Blueprint $table) {
                $table->unsignedBigInteger('pdf_size_bytes')->nullable();
            });
        }
        if (!Schema::hasColumn('books', 'pdf_total_pages')) {
            Schema::table('books', function (Blueprint $table) {
                $table->integer('pdf_total_pages')->nullable();
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS books_source_index ON books (source)');
        DB::statement('CREATE INDEX IF NOT EXISTS books_user_id_source_index ON books (user_id, source)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS books_source_index');
        DB::statement('DROP INDEX IF EXISTS books_user_id_source_index');

        foreach (['source', 'pdf_path', 'pdf_original_name', 'pdf_mime', 'pdf_size_bytes', 'pdf_total_pages'] as $col) {
            if (Schema::hasColumn('books', $col)) {
                Schema::table('books', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        if (!Schema::hasColumn('books', 'isbn')) {
            Schema::table('books', function (Blueprint $table) {
                $table->string('isbn')->nullable()->unique();
            });
        }

        DB::statement('ALTER TABLE books ALTER COLUMN user_id SET NOT NULL');
    }
};
