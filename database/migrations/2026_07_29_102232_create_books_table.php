<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->date('release_date')->nullable();
            $table->text('description')->nullable();
            $table->string('isbn')->nullable()->unique();
            $table->string('genre')->nullable();
            $table->string('cover')->nullable();
            $table->integer('pages')->nullable();
            $table->integer('pages_read')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
