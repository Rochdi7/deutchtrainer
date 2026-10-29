<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Latest result per user × Grammatik-aktiv exercise (content lives in storage/app/grammatik-aktiv).
        Schema::create('grammar_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('unit');
            $table->string('exercise', 8);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'unit', 'exercise']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_results');
    }
};
