<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookExportController;
use App\Http\Controllers\BookPageController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DarsController;
use App\Http\Controllers\DarsSeriesController;
use App\Http\Controllers\Me;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SentenceController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (Sanctum SPA cookie auth: GET /sanctum/csrf-cookie first)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('password.email');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed:relative', 'throttle:6,1'])
    ->whereNumber('id')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'user'])->name('user');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Public content
|--------------------------------------------------------------------------
*/

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

Route::get('/authors', [AuthorController::class, 'index'])->name('authors.index');
Route::get('/authors/{author}', [AuthorController::class, 'show'])->name('authors.show');

Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/books/{book}/toc', [BookController::class, 'toc'])->name('books.toc');
Route::get('/books/{book}/pages/{number}', [BookPageController::class, 'show'])->whereNumber('number')->name('books.pages.show');
Route::get('/books/{book}/search', [SearchController::class, 'book'])->middleware('throttle:60,1')->name('books.search');
Route::post('/books/{book}/exports', [BookExportController::class, 'store'])->middleware('throttle:30,1')->name('books.exports.store');
Route::get('/books/{book:id}/exports/{export}/download', [BookExportController::class, 'download'])->name('books.exports.download');

Route::get('/sentences/{sentence}', [SentenceController::class, 'show'])->name('sentences.show');

Route::get('/stats', StatsController::class)->name('stats');

Route::middleware('throttle:120,1')->prefix('analytics')->name('analytics.')->group(function () {
    Route::post('/collect', [AnalyticsController::class, 'collect'])->name('collect');
    Route::post('/engage', [AnalyticsController::class, 'engage'])->name('engage');
});

Route::get('/seo/sitemap', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/seo/feed', [SeoController::class, 'feed'])->name('seo.feed');

Route::get('/search', [SearchController::class, 'global'])->middleware('throttle:60,1')->name('search');

Route::get('/dars-series', [DarsSeriesController::class, 'index'])->name('dars-series.index');
Route::get('/dars-series/{darsSeries}', [DarsSeriesController::class, 'show'])->name('dars-series.show');
Route::get('/dars-series/{darsSeries}/durus/{number}', [DarsController::class, 'showByNumber'])->whereNumber('number')->name('dars-series.durus.show');
Route::get('/durus/{dars}', [DarsController::class, 'show'])->name('durus.show');

Route::get('/reader/fonts', fn () => response()->json([
    'fonts' => collect(config('reader.fonts'))->map(fn ($fonts) => collect($fonts)->map(fn ($font, $key) => ['key' => $key, 'family' => $font['family']])->values()),
    'defaults' => config('reader.default_fonts'),
]))->name('reader.fonts');

/*
|--------------------------------------------------------------------------
| Signed-in user data
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function () {
    Route::put('/profile', [Me\AccountController::class, 'updateProfile'])->middleware('throttle:10,1')->name('profile.update');
    Route::put('/password', [Me\AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/sessions', [Me\AccountController::class, 'sessions'])->name('sessions.index');
    Route::delete('/sessions/others', [Me\AccountController::class, 'destroyOtherSessions'])->middleware('throttle:6,1')->name('sessions.destroy-others');
    Route::delete('/account', [Me\AccountController::class, 'destroy'])->middleware('throttle:6,1')->name('account.destroy');

    Route::get('/settings', [Me\SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings', [Me\SettingsController::class, 'update'])->name('settings.update');

    Route::get('/library', [Me\LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{book:id}', [Me\LibraryController::class, 'show'])->name('library.show');
    Route::put('/library/{book:id}/status', [Me\LibraryController::class, 'updateStatus'])->name('library.status');
    Route::put('/library/{book:id}/progress', [Me\LibraryController::class, 'updateProgress'])->name('library.progress');
    Route::delete('/library/{book:id}', [Me\LibraryController::class, 'destroy'])->name('library.destroy');

    Route::get('/durus', [Me\DurusController::class, 'index'])->name('durus.index');
    Route::get('/durus/{dars}', [Me\DurusController::class, 'show'])->name('durus.show');
    Route::put('/durus/{dars}/status', [Me\DurusController::class, 'updateStatus'])->name('durus.status');
    Route::put('/durus/{dars}/progress', [Me\DurusController::class, 'updateProgress'])->name('durus.progress');
    Route::delete('/durus/{dars}', [Me\DurusController::class, 'destroy'])->name('durus.destroy');
    Route::put('/dars-series/{darsSeries:id}/status', [Me\DurusController::class, 'updateSeriesStatus'])->name('dars-series.status');

    Route::apiResource('annotations', Me\AnnotationController::class)->except('show');
    Route::apiResource('bookmarks', Me\BookmarkController::class)->except('show');

    Route::post('/sync', [Me\SyncController::class, 'store'])->name('sync');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/realtime', [Admin\AnalyticsController::class, 'realtime'])->name('analytics.realtime');

    Route::apiResource('categories', Admin\CategoryController::class)->except('show')->parameters(['categories' => 'category:id']);

    Route::apiResource('authors', Admin\AuthorController::class)->parameters(['authors' => 'author:id']);

    Route::post('/books/import', [Admin\BookController::class, 'importNew'])->name('books.import-new');
    Route::apiResource('books', Admin\BookController::class)->parameters(['books' => 'book:id']);
    Route::post('/books/{book:id}/cover', [Admin\BookController::class, 'uploadCover'])->name('books.cover.store');
    Route::delete('/books/{book:id}/cover', [Admin\BookController::class, 'deleteCover'])->name('books.cover.destroy');
    Route::post('/books/{book:id}/import', [Admin\BookController::class, 'import'])->name('books.import');
    Route::get('/books/{book:id}/export', [Admin\BookController::class, 'export'])->name('books.export');
    Route::put('/books/{book:id}/toc', [Admin\BookController::class, 'updateToc'])->name('books.toc');

    Route::get('/books/{book:id}/pages', [Admin\BookPageController::class, 'index'])->name('pages.index');
    Route::post('/books/{book:id}/pages', [Admin\BookPageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}', [Admin\BookPageController::class, 'show'])->name('pages.show');
    Route::put('/pages/{page}', [Admin\BookPageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{page}', [Admin\BookPageController::class, 'destroy'])->name('pages.destroy');

    Route::post('/pages/{page}/sentences', [Admin\BookSentenceController::class, 'store'])->name('sentences.store');
    Route::put('/pages/{page}/sentences/order', [Admin\BookSentenceController::class, 'reorder'])->name('sentences.reorder');
    Route::put('/sentences/{sentence}', [Admin\BookSentenceController::class, 'update'])->name('sentences.update');
    Route::delete('/sentences/{sentence}', [Admin\BookSentenceController::class, 'destroy'])->name('sentences.destroy');

    Route::post('/pages/{page}/footnotes', [Admin\BookFootnoteController::class, 'store'])->name('footnotes.store');
    Route::put('/footnotes/{footnote}', [Admin\BookFootnoteController::class, 'update'])->name('footnotes.update');
    Route::delete('/footnotes/{footnote}', [Admin\BookFootnoteController::class, 'destroy'])->name('footnotes.destroy');

    Route::post('/dars-series/import', [Admin\DarsSeriesController::class, 'importNew'])->name('dars-series.import-new');
    Route::apiResource('dars-series', Admin\DarsSeriesController::class)->parameters(['dars-series' => 'darsSeries:id']);
    Route::post('/dars-series/{darsSeries:id}/import', [Admin\DarsSeriesController::class, 'import'])->name('dars-series.import');
    Route::get('/dars-series/{darsSeries:id}/export', [Admin\DarsSeriesController::class, 'export'])->name('dars-series.export');
    Route::post('/dars-series/{darsSeries:id}/durus', [Admin\DarsController::class, 'store'])->name('durus.store');
    Route::get('/durus/{dars}', [Admin\DarsController::class, 'show'])->name('durus.show');
    Route::put('/durus/{dars}', [Admin\DarsController::class, 'update'])->name('durus.update');
    Route::delete('/durus/{dars}', [Admin\DarsController::class, 'destroy'])->name('durus.destroy');
    Route::post('/durus/{dars}/import', [Admin\DarsController::class, 'import'])->name('durus.import');

    Route::post('/durus/{dars}/segments', [Admin\DarsSegmentController::class, 'store'])->name('segments.store');
    Route::put('/segments/{segment}', [Admin\DarsSegmentController::class, 'update'])->name('segments.update');
    Route::delete('/segments/{segment}', [Admin\DarsSegmentController::class, 'destroy'])->name('segments.destroy');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
});
