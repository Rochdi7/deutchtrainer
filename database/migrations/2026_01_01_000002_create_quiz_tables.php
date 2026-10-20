<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['lektion', 'global', 'weak']);
            $table->foreignId('lektion_id')->nullable()->constrained('lektionen')->nullOnDelete();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->json('questions');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mode', 'lektion_id']);
            $table->index(['user_id', 'finished_at']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('word_id')->nullable()->constrained('words')->nullOnDelete();
            $table->string('question_type', 32);
            $table->string('prompt');
            $table->string('correct_answer');
            $table->string('given_answer')->nullable();
            $table->boolean('is_correct');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->index(['word_id', 'answered_at']);
        });

        Schema::create('user_word_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained('words')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('streak')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'word_id']);
            $table->index(['user_id', 'next_review_at']);
        });

        Schema::create('import_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lektion_number');
            $table->string('title')->nullable();
            $table->string('pdf_path');
            $table->enum('status', ['pending', 'processing', 'ready', 'failed', 'imported'])->default('pending');
            $table->longText('raw_text')->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('chunks_total')->default(0);
            $table->unsignedInteger('chunks_done')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
        Schema::dropIfExists('import_previews');
        Schema::dropIfExists('user_word_progress');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_sessions');
    }
};
