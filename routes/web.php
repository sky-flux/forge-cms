<?php

use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\TagController;
use App\Settings\SeoSettings;
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

Route::feeds();

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

/*
 * Dynamic robots.txt. In production the static `public/robots.txt` file is
 * served by the web server (nginx/apache) before Laravel's routing layer is
 * reached, so this route only takes effect if that static file is removed.
 * Under `php artisan serve` / feature tests the request passes through the
 * HTTP kernel and this handler is used, which is what drives RobotsTxtTest.
 */
Route::get('/robots.txt', function () {
    $settings = app(SeoSettings::class);
    $base = "User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml');
    if ($settings->robots_extra) {
        $base .= "\n".$settings->robots_extra;
    }

    return response($base, 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::post('/posts/{post:uuid}/comments', [CommentController::class, 'storeForPost'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('posts.comments.store');

Route::post('/pages/{page:slug}/comments', [CommentController::class, 'storeForPage'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('pages.comments.store');

require __DIR__.'/settings.php';
