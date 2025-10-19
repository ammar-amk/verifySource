<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// Home and main pages
Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/about', [\App\Http\Controllers\HomeController::class, 'about'])->name('about');
Route::get('/search', [\App\Http\Controllers\HomeController::class, 'search'])->name('search');

// Source and Article browsing (web interface)
Route::get('/sources', [\App\Http\Controllers\SourceController::class, 'index'])->name('sources.index');
Route::get('/sources/{source}', [\App\Http\Controllers\SourceController::class, 'show'])->name('sources.show');
Route::get('/articles', [\App\Http\Controllers\ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article}', [\App\Http\Controllers\ArticleController::class, 'show'])->name('articles.show');
Route::post('/articles/{article}/verify', [\App\Http\Controllers\ArticleController::class, 'reverify'])->name('verification.verify-article');

Route::prefix('verification')->group(function () {
    Route::get('/', [VerificationController::class, 'index'])->name('verification.index');
    Route::post('/verify', [VerificationController::class, 'verify'])->name('verification.verify');
    Route::get('/results/{id}', [VerificationController::class, 'results'])->name('verification.results');
});

Route::get('/content/dashboard', function () {
    return view('content.dashboard');
})->name('content.dashboard');

Route::prefix('api/v1')->group(function () {
    // Verification endpoints
    Route::post('/verify', [VerificationController::class, 'verify'])->name('api.verification.verify');
    Route::get('/results/{id}', [VerificationController::class, 'results'])->name('api.verification.results');

    // Content management endpoints
    Route::prefix('content')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('api.content.index');
        Route::get('/recent', [ContentController::class, 'getRecent'])->name('api.content.recent');
        Route::get('/stats', [ContentController::class, 'getStats'])->name('api.content.stats');
        Route::get('/search', [ContentController::class, 'search'])->name('api.content.search');
        Route::post('/', [ContentController::class, 'store'])->name('api.content.store');
        Route::get('/{article}', [ContentController::class, 'show'])->name('api.content.show');
        Route::put('/{article}', [ContentController::class, 'update'])->name('api.content.update');
        Route::delete('/{article}', [ContentController::class, 'destroy'])->name('api.content.destroy');
        Route::post('/{article}/process', [ContentController::class, 'process'])->name('api.content.process');
        Route::get('/{article}/duplicates', [ContentController::class, 'findDuplicates'])->name('api.content.duplicates');
        Route::post('/{article}/mark-duplicate', [ContentController::class, 'markAsDuplicate'])->name('api.content.mark-duplicate');
    });

    // Source management endpoints
    Route::prefix('sources')->group(function () {
        Route::get('/', [SourceController::class, 'index'])->name('api.sources.index');
        Route::get('/active', [SourceController::class, 'getActive'])->name('api.sources.active');
        Route::get('/high-credibility', [SourceController::class, 'getHighCredibility'])->name('api.sources.high-credibility');
        Route::get('/performance-metrics', [SourceController::class, 'getPerformanceMetrics'])->name('api.sources.performance');
        Route::get('/recommendations', [SourceController::class, 'getRecommendations'])->name('api.sources.recommendations');
        Route::get('/search', [SourceController::class, 'search'])->name('api.sources.search');
        Route::post('/', [SourceController::class, 'store'])->name('api.sources.store');
        Route::get('/{source}', [SourceController::class, 'show'])->name('api.sources.show');
        Route::put('/{source}', [SourceController::class, 'update'])->name('api.sources.update');
        Route::delete('/{source}', [SourceController::class, 'destroy'])->name('api.sources.destroy');
        Route::post('/{source}/activate', [SourceController::class, 'activate'])->name('api.sources.activate');
        Route::post('/{source}/deactivate', [SourceController::class, 'deactivate'])->name('api.sources.deactivate');
        Route::post('/{source}/verify', [SourceController::class, 'verify'])->name('api.sources.verify');
        Route::put('/{source}/credibility', [SourceController::class, 'updateCredibility'])->name('api.sources.credibility');
        Route::get('/{source}/articles', [ContentController::class, 'getBySource'])->name('api.sources.articles');
    });
});
