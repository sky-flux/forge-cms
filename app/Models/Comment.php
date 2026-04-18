<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'commentable_type', 'commentable_id', 'parent_id', 'user_id',
        'guest_name', 'guest_email', 'guest_ip_hash', 'user_agent',
        'body', 'body_html', 'status', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function approvedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('status', CommentStatus::Approved);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function authorName(): ?string
    {
        return $this->user?->name ?? $this->guest_name;
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', CommentStatus::Pending);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    public function scopeSpam(Builder $query): void
    {
        $query->where('status', CommentStatus::Spam);
    }

    public function scopeTrash(Builder $query): void
    {
        $query->where('status', CommentStatus::Trash);
    }
}
