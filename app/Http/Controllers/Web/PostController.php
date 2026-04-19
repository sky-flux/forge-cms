<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $posts = Post::query()
            ->published()
            ->with('user:id,name')
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        return Inertia::render('Posts/Index', [
            'posts' => PostResource::collection($posts),
            'canonical' => route('posts.index'),
            'ogImage' => app(GeneralSettings::class)->default_og_image,
        ]);
    }

    public function show(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);

        $post->load([
            'user:id,name',
            'approvedComments' => fn ($q) => $q->with('user:id,name')->whereNull('parent_id')->orderBy('created_at', 'asc'),
            'approvedComments.approvedChildren' => fn ($q) => $q->with('user:id,name')->orderBy('created_at', 'asc'),
            'approvedComments.approvedChildren.approvedChildren' => fn ($q) => $q->with('user:id,name')->orderBy('created_at', 'asc'),
        ]);
        $post->increment('view_count');

        $featuredUrl = $post->getFirstMediaUrl('featured') ?: null;

        return Inertia::render('Posts/Show', [
            'post' => new PostResource($post),
            'canonical' => route('posts.show', ['post' => $post]),
            'ogImage' => $featuredUrl ?? app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
