<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Settings\SeoSettings;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $settings = app(SeoSettings::class);

        $sitemap = Sitemap::create()
            ->add(Url::create(url('/')))
            ->add(Url::create(route('posts.index')));

        Post::query()
            ->published()
            ->get()
            ->each(fn (Post $post) => $sitemap->add(
                Url::create(route('posts.show', ['post' => $post]))
                    ->setLastModificationDate($post->updated_at ?? now()),
            ));

        Page::query()
            ->published()
            ->get()
            ->each(fn (Page $page) => $sitemap->add(
                Url::create(route('pages.show', ['page' => $page]))
                    ->setLastModificationDate($page->updated_at ?? now()),
            ));

        if ($settings->sitemap_include_categories) {
            Category::all()->each(
                fn (Category $category) => $sitemap->add(
                    Url::create(route('categories.show', ['category' => $category])),
                ),
            );
        }

        if ($settings->sitemap_include_tags) {
            Tag::all()->each(
                fn (Tag $tag) => $sitemap->add(
                    Url::create(route('tags.show', ['tag' => $tag])),
                ),
            );
        }

        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
