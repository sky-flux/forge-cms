<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Flip Scheduled posts whose published_at has passed to Published.';

    public function handle(): int
    {
        $query = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now());

        $count = 0;

        // lazyById() over cursor() so status mutation mid-iteration can't cause
        // skipped or duplicate rows (laravel-best-practices §1 "lazyById when
        // updating records while iterating").
        $query->lazyById()->each(function (Post $post) use (&$count): void {
            $post->status = PostStatus::Published;
            $post->save();
            $count++;

            Log::info('post.auto_published', [
                'post_id' => $post->getKey(),
                'published_at' => (string) $post->published_at,
            ]);
        });

        $this->info("Published {$count} scheduled post(s).");

        return self::SUCCESS;
    }
}
