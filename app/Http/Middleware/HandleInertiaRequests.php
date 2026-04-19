<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $this->resolveAuthUser($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Resolve the authenticated user payload exposed to Inertia.
     *
     * Only whitelisted fields are returned so sensitive attributes such as
     * `password`, `remember_token`, `two_factor_secret`, and
     * `two_factor_recovery_codes` never leak into the client-side props.
     *
     * @return array{id: int, name: string, email: string, avatar: string|null, email_verified_at: Carbon|null, two_factor_enabled: bool, created_at: Carbon|null, updated_at: Carbon|null}|null
     */
    private function resolveAuthUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? null,
            'email_verified_at' => $user->email_verified_at,
            'two_factor_enabled' => $user->two_factor_secret !== null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
