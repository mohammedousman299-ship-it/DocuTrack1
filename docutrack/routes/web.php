<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'fr']), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang');

Route::view('/feedback', 'feedback')->name('feedback');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('guest')->group(function () {
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/finder', 'finder')->name('finder');
    Route::view('/owner', 'owner')->name('owner');

    Route::get('/found/create', [DocumentController::class, 'createFound'])->name('found.create');
    Route::post('/found', [DocumentController::class, 'storeFound'])->name('found.store');
    Route::get('/search', [DocumentController::class, 'search'])->name('search');
    Route::get('/declare', [DocumentController::class, 'createDeclaration'])->name('declare.create');
    Route::post('/declare', [DocumentController::class, 'storeDeclaration'])->name('declare.store');
    Route::get('/documents/{document}/pay', [DocumentController::class, 'payForm'])->name('documents.pay');
    Route::post('/documents/{document}/pay', [DocumentController::class, 'pay']);
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/notifications', [DocumentController::class, 'notifications'])->name('notifications');

    Route::middleware('admin')->prefix('admin')->name('admin.')->controller(AdminController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::delete('/found/{document}', 'destroyFound')->name('found.destroy');
        Route::delete('/declarations/{declaration}', 'destroyDeclaration')->name('declarations.destroy');
        Route::patch('/users/{user}/toggle', 'toggleUser')->name('users.toggle');
        Route::post('/categories', 'storeCategory')->name('categories.store');
        Route::delete('/categories/{category}', 'destroyCategory')->name('categories.destroy');
        Route::patch('/feedback/{feedback}', 'updateFeedback')->name('feedback.update');
        Route::delete('/feedback/{feedback}', 'destroyFeedback')->name('feedback.destroy');
    });
});
