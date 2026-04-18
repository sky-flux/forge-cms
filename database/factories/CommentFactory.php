<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        $body = $this->faker->paragraph();

        return [
            'commentable_type' => Post::class,
            'commentable_id' => Post::factory(),
            'parent_id' => null,
            'user_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'guest_ip_hash' => hash('sha256', 'factory-ip-'.$this->faker->unique()->numberBetween(1, 99999)),
            'user_agent' => 'Mozilla/5.0 (Factory)',
            'body' => $body,
            'body_html' => nl2br(e($body)),
            'status' => CommentStatus::Pending,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CommentStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function spam(): static
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Spam]);
    }

    public function trash(): static
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Trash]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'guest_name' => null,
            'guest_email' => null,
        ]);
    }

    public function guest(?string $name = null, ?string $email = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'guest_name' => $name ?? $this->faker->name(),
            'guest_email' => $email ?? $this->faker->safeEmail(),
        ]);
    }
}
