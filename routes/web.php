<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
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

    Route::get('/quiz/lektion/{lektion}', [QuizController::class, 'lektion'])->name('quiz.lektion');
    Route::get('/quiz/global', [QuizController::class, 'global'])->name('quiz.global');
    Route::get('/quiz/weak', [QuizController::class, 'weak'])->name('quiz.weak');
    Route::post('/quiz/words', [QuizController::class, 'words'])->name('quiz.words');
    Route::get('/quiz/{session}', [QuizController::class, 'show'])->name('quiz.show');
    Route::post('/quiz/{session}/answer', [QuizController::class, 'answer'])->name('quiz.answer');
    Route::post('/quiz/{session}/finish', [QuizController::class, 'finish'])->name('quiz.finish');
    Route::post('/quiz/{session}/retry-wrong', [QuizController::class, 'retryWrong'])->name('quiz.retry');

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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
