<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Settings\GeneralSettings;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Category $category): Response
    {
        $posts = $category->posts()
            ->published()
            ->with('user:id,name')
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        return Inertia::render('Categories/Show', [
            'category' => new CategoryResource($category),
            'posts' => PostResource::collection($posts),
            'canonical' => route('categories.show', ['category' => $category]),
            'ogImage' => app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
