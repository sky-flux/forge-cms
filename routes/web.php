<?php

use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\TagController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

Route::get('/search', [SearchController::class, 'index'])->name('search');

Route::post('/posts/{post:uuid}/comments', [CommentController::class, 'storeForPost'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('posts.comments.store');

Route::post('/pages/{page:slug}/comments', [CommentController::class, 'storeForPage'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('pages.comments.store');

require __DIR__.'/settings.php';
