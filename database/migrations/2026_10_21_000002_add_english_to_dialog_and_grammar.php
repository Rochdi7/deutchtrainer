<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dialog_lines', function (Blueprint $table) {
            $table->string('english')->nullable()->after('arabic');
            $table->unsignedSmallInteger('source_page')->nullable()->after('sort');
        });
        Schema::table('grammar_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('source_page')->nullable()->after('sort');
            $table->string('key', 64)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('dialog_lines', fn (Blueprint $table) => $table->dropColumn(['english', 'source_page']));
        Schema::table('grammar_notes', fn (Blueprint $table) => $table->dropColumn(['source_page', 'key']));
    }
};
