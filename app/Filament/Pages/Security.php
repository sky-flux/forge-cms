<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\FailedLoginAttempt;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

class Security extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = '安全';

    protected static ?string $title = '安全';

    protected static ?string $slug = 'security';

    protected string $view = 'filament.pages.security';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * Active sessions pulled from the `sessions` table (only available when
     * the application uses the database session driver).
     *
     * @return array<int, array{
     *     id: string,
     *     user_name: ?string,
     *     ip: ?string,
     *     user_agent: ?string,
     *     last_activity: ?CarbonInterface,
     * }>
     */
    public function getSessionsData(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $table = (string) config('session.table', 'sessions');

        return DB::table($table)
            ->leftJoin('users', $table.'.user_id', '=', 'users.id')
            ->select(
                $table.'.id',
                $table.'.ip_address as ip',
                $table.'.user_agent',
                $table.'.last_activity',
                'users.name as user_name',
            )
            ->orderByDesc($table.'.last_activity')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'user_name' => $row->user_name,
                'ip' => $row->ip,
                'user_agent' => $row->user_agent,
                'last_activity' => $row->last_activity !== null
                    ? Carbon::createFromTimestamp((int) $row->last_activity)
                    : null,
            ])
            ->all();
    }

    public function forceLogout(string $sessionId): void
    {
        abort_unless(static::canAccess(), 403);

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete();

        Notification::make()
            ->title('Session 已注销')
            ->success()
            ->send();
    }

    /**
     * @return array<int, array{
     *     email: string,
     *     ip: ?string,
     *     attempted_at: ?CarbonInterface,
     * }>
     */
    public function getFailedLoginsData(): array
    {
        return FailedLoginAttempt::query()
            ->orderByDesc('attempted_at')
            ->limit(50)
            ->get()
            ->map(fn (FailedLoginAttempt $attempt) => [
                'email' => $attempt->email,
                'ip' => $attempt->ip,
                'attempted_at' => $attempt->attempted_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     user_name: ?string,
     *     name: string,
     *     abilities: array<int, string>,
     *     last_used_at: ?CarbonInterface,
     * }>
     */
    public function getTokensData(): array
    {
        return PersonalAccessToken::query()
            ->with('tokenable')
            ->orderByDesc('last_used_at')
            ->limit(100)
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => (int) $token->id,
                'user_name' => $token->tokenable?->name ?? null,
                'name' => (string) $token->name,
                'abilities' => is_array($token->abilities) ? $token->abilities : [],
                'last_used_at' => $token->last_used_at,
            ])
            ->all();
    }

    public function revokeToken(int $tokenId): void
    {
        abort_unless(static::canAccess(), 403);

        PersonalAccessToken::query()->where('id', $tokenId)->delete();

        Notification::make()
            ->title('Token 已撤销')
            ->success()
            ->send();
    }
}
