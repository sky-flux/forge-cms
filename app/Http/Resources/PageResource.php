<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'bodyHtml' => $this->body_html,
            'status' => $this->status?->value,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'sortOrder' => $this->sort_order,
            'isHomepage' => $this->is_homepage,
            'isCommentsEnabled' => $this->is_comments_enabled,
            'author' => [
                'name' => $this->whenLoaded('user', fn () => $this->user->name),
            ],
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
        ];
    }
}
