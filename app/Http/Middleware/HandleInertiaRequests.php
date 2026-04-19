<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\AppearanceSettings;
use App\Settings\LegalSettings;
use App\Settings\SeoSettings;
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
            'seo' => fn () => $this->resolveSeoSharedProps(),
            'appearance' => fn () => $this->resolveAppearanceSharedProps(),
            'legal' => fn () => $this->resolveLegalSharedProps(),
        ];
    }

    /**
     * Resolve the appearance / branding shared props exposed to every Inertia
     * response.
     *
     * Resolved per-request so Octane-shared containers and runtime settings
     * edits take effect on the next visit.
     *
     * @return array{logo_url: ?string, favicon_url: ?string, primary_color: string, footer_text: ?string}
     */
    private function resolveAppearanceSharedProps(): array
    {
        $appearance = app(AppearanceSettings::class);

        return [
            'logo_url' => $appearance->logo_url,
            'favicon_url' => $appearance->favicon_url,
            'primary_color' => $appearance->primary_color,
            'footer_text' => $appearance->footer_text,
        ];
    }

    /**
     * Resolve the legal / compliance shared props exposed to every Inertia
     * response.
     *
     * Resolved per-request so Octane-shared containers and runtime settings
     * edits take effect on the next visit.
     *
     * @return array{terms_url: ?string, privacy_url: ?string, cookie_banner_enabled: bool, cookie_banner_text: ?string}
     */
    private function resolveLegalSharedProps(): array
    {
        $legal = app(LegalSettings::class);

        return [
            'terms_url' => $legal->terms_url,
            'privacy_url' => $legal->privacy_url,
            'cookie_banner_enabled' => $legal->cookie_banner_enabled,
            'cookie_banner_text' => $legal->cookie_banner_text,
        ];
    }

    /**
     * Resolve the SEO / analytics shared props exposed to every Inertia
     * response.
     *
     * Resolved per-request (not captured at middleware boot) so Octane-shared
     * containers and runtime settings edits take effect on the next visit.
     *
     * @return array{google_analytics_id: ?string, google_tag_manager_id: ?string, google_site_verification: ?string, bing_site_verification: ?string, twitter_site_handle: ?string, facebook_app_id: ?string}
     */
    private function resolveSeoSharedProps(): array
    {
        $seo = app(SeoSettings::class);

        return [
            'google_analytics_id' => $seo->google_analytics_id,
            'google_tag_manager_id' => $seo->google_tag_manager_id,
            'google_site_verification' => $seo->google_site_verification,
            'bing_site_verification' => $seo->bing_site_verification,
            'twitter_site_handle' => $seo->twitter_site_handle,
            'facebook_app_id' => $seo->facebook_app_id,
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
