<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course structure for the A1 curriculum (DaF kompakt neu A1) – level-aware so that
 * A2/B1 can be added later without a redesign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lektionen', function (Blueprint $table) {
            $table->string('level', 8)->default('A1')->after('id');
            $table->string('slug', 64)->nullable()->unique()->after('number');
            $table->string('subtitle')->nullable()->after('title');
            $table->json('objectives')->nullable()->after('subtitle');
            $table->json('source_pages')->nullable()->after('objectives');
            $table->text('revision_md')->nullable()->after('source_pages');
            $table->unsignedInteger('sort')->default(0)->after('revision_md');
        });
        Schema::table('lektionen', function (Blueprint $table) {
            $table->dropUnique('lektionen_number_unique');
            $table->unique(['level', 'number']);
        });

        Schema::table('words', function (Blueprint $table) {
            $table->string('english')->nullable()->after('arabic');
            $table->string('example_en')->nullable()->after('example_ar');
            $table->string('image')->nullable()->after('note');
            $table->unsignedSmallInteger('source_page')->nullable()->after('image');
            $table->json('tags')->nullable()->after('source_page');
        });

        Schema::table('user_word_progress', function (Blueprint $table) {
            $table->boolean('is_difficult')->default(false)->after('streak');
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('title');
            $table->text('text_md');
            $table->text('translation_md')->nullable();
            $table->json('questions')->nullable();
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lektion_id')->nullable()->constrained('lektionen')->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('section', 32)->default('grammar');
            $table->string('type', 32);
            $table->text('prompt');
            $table->json('data');
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->timestamps();
            $table->index(['lektion_id', 'section']);
            $table->index(['lektion_id', 'type']);
        });

        Schema::create('exercise_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lektion_id')->nullable()->constrained('lektionen')->nullOnDelete();
            $table->string('mode', 16);
            $table->string('label')->nullable();
            $table->string('section', 32)->nullable();
            $table->json('items');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mode', 'lektion_id']);
            $table->index(['user_id', 'finished_at']);
        });

        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_session_id')->nullable()->constrained('exercise_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->foreignId('word_id')->nullable()->constrained('words')->nullOnDelete();
            $table->string('item_id', 64);
            $table->string('type', 32);
            $table->text('given')->nullable();
            $table->boolean('is_correct');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->index(['user_id', 'exercise_id']);
            $table->index(['user_id', 'is_correct']);
            $table->index(['exercise_session_id', 'item_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lektion_id')->constrained('lektionen')->cascadeOnDelete();
            $table->string('section', 32);
            $table->json('data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lektion_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('exercise_sessions');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('readings');

        Schema::table('user_word_progress', fn (Blueprint $table) => $table->dropColumn('is_difficult'));
        Schema::table('words', fn (Blueprint $table) => $table->dropColumn(['english', 'example_en', 'image', 'source_page', 'tags']));
        Schema::table('lektionen', function (Blueprint $table) {
            $table->dropUnique(['level', 'number']);
            $table->unique('number');
        });
        Schema::table('lektionen', fn (Blueprint $table) => $table->dropColumn(['level', 'slug', 'subtitle', 'objectives', 'source_pages', 'revision_md', 'sort']));
    }
};
