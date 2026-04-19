<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Settings\CommentSettings;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isGuest = $this->user() === null;

        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(
                    fn ($q) => $q->where('status', CommentStatus::Approved->value),
                ),
            ],
            'guest_name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
            'guest_email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parentId = $this->integer('parent_id') ?: null;
                if ($parentId === null) {
                    return;
                }

                $parent = Comment::find($parentId);
                if ($parent === null) {
                    return;
                }

                $maxDepth = app(CommentSettings::class)->max_depth;

                $depth = 1;
                $cursor = $parent;
                while ($cursor->parent_id !== null && $depth < $maxDepth + 1) {
                    $next = Comment::find($cursor->parent_id);
                    if ($next === null) {
                        break;
                    }
                    $cursor = $next;
                    $depth++;
                }

                if ($depth >= $maxDepth) {
                    $validator->errors()->add(
                        'parent_id',
                        'Replies beyond depth '.$maxDepth.' are not allowed.',
                    );
                }
            },
        ];
    }
}
