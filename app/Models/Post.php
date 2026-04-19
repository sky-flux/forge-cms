<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasSlug, HasUuids, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'body_html',
        'seo_title', 'seo_description', 'status', 'published_at',
        'view_count', 'is_comments_enabled', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_comments_enabled' => 'boolean',
            'view_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'post_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function approvedComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->where('status', CommentStatus::Approved);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PostStatus::Draft);
    }

    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', PostStatus::Scheduled);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body_html' => strip_tags($this->body_html),
            'status' => $this->status?->value,
            'published_at' => $this->published_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'published_at', 'is_comments_enabled'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "post.{$eventName}");
    }
}
