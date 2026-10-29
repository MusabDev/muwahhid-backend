<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('slug')->unique();
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->string('title_ar');
            $table->string('title_bn');
            $table->string('title_translit');
            $table->string('title_ar_normalized');
            $table->text('about_md')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('content_version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX books_titles_trgm ON books USING gin ((title_ar_normalized || \' \' || title_bn || \' \' || title_translit) gin_trgm_ops)');

        Schema::create('book_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->timestamps();

            $table->unique(['book_id', 'number']);
        });

        Schema::create('book_sentences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('book_pages')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('block_type', 20)->default('paragraph');
            $table->boolean('paragraph_break')->default(false);
            $table->text('text_ar_md');
            $table->text('text_bn_md');
            $table->text('text_ar_plain');
            $table->text('text_bn_plain');
            $table->text('text_ar_normalized');
            $table->timestamps();

            $table->index(['page_id', 'position']);
            $table->index('book_id');
        });

        DB::statement('CREATE INDEX book_sentences_ar_trgm ON book_sentences USING gin (text_ar_normalized gin_trgm_ops)');
        DB::statement('CREATE INDEX book_sentences_bn_trgm ON book_sentences USING gin (text_bn_plain gin_trgm_ops)');
        DB::statement("CREATE INDEX book_sentences_fts ON book_sentences USING gin ((to_tsvector('simple', text_ar_normalized) || to_tsvector('simple', text_bn_plain)))");

        Schema::create('book_footnotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('book_pages')->cascadeOnDelete();
            $table->string('lang', 2);
            $table->string('marker', 32);
            $table->text('text_md');
            $table->text('text_plain');
            $table->text('text_normalized');
            $table->timestamps();

            $table->unique(['page_id', 'lang', 'marker']);
            $table->index('book_id');
        });

        DB::statement('CREATE INDEX book_footnotes_trgm ON book_footnotes USING gin (text_normalized gin_trgm_ops)');

        Schema::create('toc_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('toc_entries')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title_ar');
            $table->string('title_bn');
            $table->unsignedInteger('page_number');
            $table->foreignId('sentence_id')->nullable()->constrained('book_sentences')->nullOnDelete();
            $table->timestamps();

            $table->index(['book_id', 'position']);
        });

        Schema::create('book_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 10);
            $table->unsignedInteger('version');
            $table->string('font_ar', 50);
            $table->string('font_bn', 50);
            $table->string('file_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'variant', 'version', 'font_ar', 'font_bn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_exports');
        Schema::dropIfExists('toc_entries');
        Schema::dropIfExists('book_footnotes');
        Schema::dropIfExists('book_sentences');
        Schema::dropIfExists('book_pages');
        Schema::dropIfExists('books');
    }
};
