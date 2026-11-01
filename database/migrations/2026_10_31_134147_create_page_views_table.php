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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('visitor_hash', 64);
            $table->uuid('session_key');
            $table->string('path', 500);
            $table->string('route', 150);
            $table->string('title', 300)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('device', 20);
            $table->string('browser', 50);
            $table->string('os', 50);
            $table->string('language', 10)->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->boolean('is_authenticated')->default(false);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['visitor_hash', 'created_at']);
            $table->index(['path', 'created_at']);
            $table->index('session_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
