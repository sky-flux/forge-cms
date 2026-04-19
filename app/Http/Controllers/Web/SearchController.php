<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Models\Page;
use App\Models\Post;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->string('q')->toString() ?: null;

        $posts = $query !== null
            ? Post::search($query)->paginate(10)->withQueryString()
            : Post::query()->whereRaw('1=0')->paginate(10);

        $pages = $query !== null
            ? Page::search($query)->paginate(10)->withQueryString()
            : Page::query()->whereRaw('1=0')->paginate(10);

        $canonical = $query !== null
            ? route('search', ['q' => $query])
            : route('search');

        return Inertia::render('Search', [
            'query' => $query,
            'posts' => PostResource::collection($posts),
            'pages' => PageResource::collection($pages),
            'canonical' => $canonical,
            'ogImage' => app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
