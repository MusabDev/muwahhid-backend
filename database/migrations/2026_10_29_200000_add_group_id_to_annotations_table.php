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
        Schema::table('annotations', function (Blueprint $table) {
            // Shared by the per-sentence parts of one highlight that spans several sentences
            $table->string('group_id', 64)->nullable()->after('note');
            $table->index(['user_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'group_id']);
            $table->dropColumn('group_id');
        });
    }
};
