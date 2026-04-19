<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Settings\FeedSettings;
use App\Settings\SeoSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    public function __invoke(): Response
    {
        $feed = app(FeedSettings::class);
        $ttlSeconds = $feed->feed_cache_ttl_minutes * 60;

        $xml = $ttlSeconds > 0
            ? Cache::remember(self::CACHE_KEY, $ttlSeconds, fn (): string => $this->renderSitemap())
            : $this->renderSitemap();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function renderSitemap(): string
    {
        $seo = app(SeoSettings::class);
        $feed = app(FeedSettings::class);
        $priority = (float) $feed->sitemap_default_priority;
        $changeFrequency = (string) $feed->sitemap_change_frequency;

        $decorate = fn (Url $url): Url => $url
            ->setPriority($priority)
            ->setChangeFrequency($changeFrequency);

        $sitemap = Sitemap::create()
            ->add($decorate(Url::create(url('/'))))
            ->add($decorate(Url::create(route('posts.index'))));

        Post::query()
            ->published()
            ->get()
            ->each(fn (Post $post) => $sitemap->add(
                $decorate(Url::create(route('posts.show', ['post' => $post])))
                    ->setLastModificationDate($post->updated_at ?? now()),
            ));

        Page::query()
            ->published()
            ->get()
            ->each(fn (Page $page) => $sitemap->add(
                $decorate(Url::create(route('pages.show', ['page' => $page])))
                    ->setLastModificationDate($page->updated_at ?? now()),
            ));

        if ($seo->sitemap_include_categories) {
            Category::all()->each(
                fn (Category $category) => $sitemap->add(
                    $decorate(Url::create(route('categories.show', ['category' => $category]))),
                ),
            );
        }

        if ($seo->sitemap_include_tags) {
            Tag::all()->each(
                fn (Tag $tag) => $sitemap->add(
                    $decorate(Url::create(route('tags.show', ['tag' => $tag]))),
                ),
            );
        }

        return $sitemap->render();
    }
}
