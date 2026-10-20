<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lektionen', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('title');
            $table->string('source_pdf_path')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['lektion_id', 'name']);
        });

        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('type', [
                'noun', 'verb', 'adjective', 'adverb', 'question_word', 'preposition',
                'particle', 'expression', 'country', 'language', 'nationality',
            ]);
            $table->string('german');
            $table->string('article', 16)->nullable();
            $table->string('plural')->nullable();
            $table->string('plural_suffix', 32)->nullable();
            $table->string('arabic');
            $table->string('example_de')->nullable();
            $table->string('example_ar')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['lektion_id', 'type']);
            $table->index(['lektion_id', 'german']);
        });

        Schema::create('conjugations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')->unique()->constrained('words')->cascadeOnDelete();
            $table->string('ich', 64);
            $table->string('du', 64);
            $table->string('er_sie_es', 64);
            $table->string('wir', 64);
            $table->string('ihr', 64);
            $table->string('sie_Sie', 64);
            $table->timestamps();
        });

        Schema::create('dialog_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->string('topic');
            $table->string('question_formal')->nullable();
            $table->string('question_informal')->nullable();
            $table->string('answer');
            $table->string('arabic');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('grammar_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->string('title');
            $table->text('body_md');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_notes');
        Schema::dropIfExists('dialog_lines');
        Schema::dropIfExists('conjugations');
        Schema::dropIfExists('words');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('lektionen');
    }
};
