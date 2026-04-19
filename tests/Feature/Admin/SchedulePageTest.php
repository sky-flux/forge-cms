<?php

declare(strict_types=1);

use App\Filament\Pages\Schedule;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('schedule page lives under 系统', function (): void {
    $cls = Schedule::class;
    expect($cls::getNavigationGroup())->toBe('系统')
        ->and($cls::getNavigationSort())->toBe(5);
});

test('super_admin can access schedule page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(Schedule::getUrl())
        ->assertSuccessful();
});

test('guest redirected from schedule page', function (): void {
    $this->get(Schedule::getUrl())
        ->assertRedirect('/console/login');
});

test('ScheduledTaskRun model persists run record', function (): void {
    $run = ScheduledTaskRun::create([
        'command' => 'posts:publish-scheduled',
        'started_at' => now(),
        'finished_at' => now()->addSecond(),
        'exit_code' => 0,
        'output' => 'Published 3 posts',
    ]);

    expect($run->fresh())->not->toBeNull()
        ->and($run->exit_code)->toBe(0);
});
