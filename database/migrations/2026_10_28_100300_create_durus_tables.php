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
        Schema::create('dars_series', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_bn');
            $table->string('title_translit');
            $table->string('title_ar_normalized');
            $table->text('about_md')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('durus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('dars_series')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title_ar')->nullable();
            $table->string('title_bn');
            $table->string('media_type', 10)->default('video');
            $table->string('media_url');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['series_id', 'number']);
        });

        Schema::create('dars_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dars_id')->constrained('durus')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('start_time', 10, 3);
            $table->decimal('end_time', 10, 3)->nullable();
            $table->text('text_ar')->default('');
            $table->text('text_bn')->default('');
            $table->text('text_ar_normalized')->default('');
            $table->timestamps();

            $table->index(['dars_id', 'position']);
        });

        DB::statement('CREATE INDEX dars_segments_ar_trgm ON dars_segments USING gin (text_ar_normalized gin_trgm_ops)');
        DB::statement('CREATE INDEX dars_segments_bn_trgm ON dars_segments USING gin (text_bn gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dars_segments');
        Schema::dropIfExists('durus');
        Schema::dropIfExists('dars_series');
    }
};
