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
        Schema::create('user_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->nullable();
            $table->unsignedInteger('last_page')->nullable();
            $table->foreignId('last_sentence_id')->nullable()->constrained('book_sentences')->nullOnDelete();
            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('annotatable');
            $table->foreignId('book_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('dars_id')->nullable()->constrained('durus')->cascadeOnDelete();
            $table->string('lang', 2);
            $table->unsignedInteger('start_offset');
            $table->unsignedInteger('end_offset');
            $table->text('quote');
            $table->string('color', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
            $table->index(['user_id', 'dars_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->foreignId('sentence_id')->nullable()->constrained('book_sentences')->nullOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('annotations');
        Schema::dropIfExists('user_books');
    }
};
