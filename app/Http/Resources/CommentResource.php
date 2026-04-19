<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'parentId' => $this->parent_id,
            'bodyHtml' => $this->body_html,
            'authorName' => $this->authorName(),
            'isGuest' => $this->isGuest(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'children' => CommentResource::collection($this->whenLoaded('approvedChildren')),
        ];
    }
}
