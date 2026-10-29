<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\GrammatikController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LearningSettingsController;
use App\Http\Controllers\LektionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/lektionen', [LektionController::class, 'index'])->name('lektionen.index');
    Route::get('/lektionen/{lektion}', [LektionController::class, 'show'])->name('lektionen.show');
    Route::post('/lektionen/{lektion}/words', [WordController::class, 'store'])->name('words.store');

    Route::get('/words/{word}', [WordController::class, 'show'])->name('words.show');
    Route::put('/words/{word}', [WordController::class, 'update'])->name('words.update');
    Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');

    // A1 course (level-aware: /kurs/a1)
    Route::get('/kurs/{level?}', [CourseController::class, 'dashboard'])->name('course.dashboard');
    Route::post('/lektionen/{lektion}/section/{section}', [CourseController::class, 'markSection'])->name('course.section');

    Route::post('/uebungen/start', [ExerciseController::class, 'start'])->name('exercises.start');
    Route::get('/uebungen/{session}', [ExerciseController::class, 'show'])->name('exercises.show');
    Route::post('/uebungen/{session}/answer', [ExerciseController::class, 'answer'])->name('exercises.answer');
    Route::post('/uebungen/{session}/finish', [ExerciseController::class, 'finish'])->name('exercises.finish');
    Route::post('/uebungen/{session}/retry-wrong', [ExerciseController::class, 'retryWrong'])->name('exercises.retry');

    Route::get('/ueben', [PracticeController::class, 'index'])->name('practice.index');
    Route::get('/wiederholung', [RevisionController::class, 'index'])->name('revision.index');
    Route::post('/words/{word}/difficult', [RevisionController::class, 'toggleDifficult'])->name('words.difficult');

    Route::get('/quiz/lektion/{lektion}', [QuizController::class, 'lektion'])->name('quiz.lektion');
    Route::get('/quiz/global', [QuizController::class, 'global'])->name('quiz.global');
    Route::get('/quiz/weak', [QuizController::class, 'weak'])->name('quiz.weak');
    Route::post('/quiz/words', [QuizController::class, 'words'])->name('quiz.words');
    Route::get('/quiz/{session}', [QuizController::class, 'show'])->name('quiz.show');
    Route::post('/quiz/{session}/answer', [QuizController::class, 'answer'])->name('quiz.answer');
    Route::post('/quiz/{session}/hint', [QuizController::class, 'hint'])->name('quiz.hint');
    Route::post('/quiz/{session}/finish',[QuizController::class, 'finish'])->name('quiz.finish');
    Route::post('/quiz/{session}/retry-wrong', [QuizController::class, 'retryWrong'])->name('quiz.retry');

    // Grammatik aktiv A1–B1: transcribed from the book, so admin-only (content is not in git)
    Route::middleware('admin')->prefix('grammatik')->name('grammatik.')->group(function () {
        Route::get('/einheit/{unit}', [GrammatikController::class, 'show'])->whereNumber('unit')->name('show');
        Route::post('/einheit/{unit}/{exercise}', [GrammatikController::class, 'result'])->whereNumber('unit')->name('result');
        Route::get('/bild/{file}', [GrammatikController::class, 'image'])->name('image');
        Route::get('/{level?}', [GrammatikController::class, 'index'])->whereIn('level', ['a1', 'a2', 'b1', 'A1', 'A2', 'B1'])->name('index');
    });

    Route::middleware('admin')->prefix('import')->name('import.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/{preview}', [ImportController::class, 'show'])->name('show');
        Route::get('/{preview}/status', [ImportController::class, 'status'])->name('status');
        Route::put('/{preview}', [ImportController::class, 'update'])->name('update');
        Route::post('/{preview}/confirm', [ImportController::class, 'confirm'])->name('confirm');
        Route::post('/{preview}/retry', [ImportController::class, 'retry'])->name('retry');
        Route::delete('/{preview}', [ImportController::class, 'destroy'])->name('destroy');
    });

    Route::patch('/settings/learning', [LearningSettingsController::class, 'update'])->name('settings.learning');
    Route::post('/push/subscribe', [LearningSettingsController::class, 'subscribe'])->name('push.subscribe');
    Route::delete('/push/subscribe', [LearningSettingsController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [LearningSettingsController::class, 'test'])->middleware('throttle:6,1')->name('push.test');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
