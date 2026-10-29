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
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_bn');
            $table->string('name_translit');
            $table->string('name_ar_normalized');
            $table->text('about_md')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX authors_names_trgm ON authors USING gin ((name_ar_normalized || \' \' || name_bn || \' \' || name_translit) gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
