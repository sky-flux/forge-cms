<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGuest = $this->user() === null;

        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'guest_name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
            'guest_email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
        ];
    }
}
