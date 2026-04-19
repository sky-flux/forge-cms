<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Models\Page;
use App\Models\Post;
use App\Settings\GeneralSettings;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $homepage = Page::homepage()->published()->with('user:id,name')->first();

        $latestPosts = Post::published()
            ->with('user:id,name')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        $homepageOgImage = $homepage?->getFirstMediaUrl('featured') ?: null;

        return Inertia::render('Home', [
            'homepage' => $homepage ? new PageResource($homepage) : null,
            'latestPosts' => PostResource::collection($latestPosts),
            'canonical' => route('home'),
            'ogImage' => $homepageOgImage ?? app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
