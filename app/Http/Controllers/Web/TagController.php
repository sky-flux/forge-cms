<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Settings\GeneralSettings;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function show(Tag $tag): Response
    {
        $posts = $tag->posts()
            ->published()
            ->with('user:id,name')
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        return Inertia::render('Tags/Show', [
            'tag' => new TagResource($tag),
            'posts' => PostResource::collection($posts),
            'canonical' => route('tags.show', ['tag' => $tag]),
            'ogImage' => app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
