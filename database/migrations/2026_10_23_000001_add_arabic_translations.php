<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arabic translations for the course texts (readings, grammar notes, lesson revision summary).
 * Objectives already live in a JSON column and get an "ar" key without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lektionen', function (Blueprint $table) {
            $table->text('revision_ar_md')->nullable()->after('revision_md');
        });
        Schema::table('readings', function (Blueprint $table) {
            $table->text('translation_ar_md')->nullable()->after('translation_md');
        });
        Schema::table('grammar_notes', function (Blueprint $table) {
            $table->text('body_ar_md')->nullable()->after('body_md');
        });
    }

    public function down(): void
    {
        Schema::table('lektionen', fn (Blueprint $table) => $table->dropColumn('revision_ar_md'));
        Schema::table('readings', fn (Blueprint $table) => $table->dropColumn('translation_ar_md'));
        Schema::table('grammar_notes', fn (Blueprint $table) => $table->dropColumn('body_ar_md'));
    }
};
