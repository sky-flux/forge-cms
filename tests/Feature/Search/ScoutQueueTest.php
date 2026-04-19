<?php

declare(strict_types=1);

use App\Models\Post;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;

test('saving a published Post queues a Scout indexing job', function (): void {
    Queue::fake();

    Post::factory()->published()->create();

    Queue::assertPushed(MakeSearchable::class);
});

test('scout queue config defaults to true', function (): void {
    expect(config('scout.queue'))->toBeTrue();
});
