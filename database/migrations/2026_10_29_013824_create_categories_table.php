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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_bn');
            $table->string('name_ar')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('author_id')->constrained()->nullOnDelete();
        });

        Schema::table('dars_series', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('book_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dars_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('categories');
    }
};
