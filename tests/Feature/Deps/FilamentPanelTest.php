<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Spatie\Permission\Models\Role;

test('redirects guests from /console to the filament login page', function (): void {
    $response = $this->get('/console');

    $response->assertRedirect('/console/login');
});

test('renders the filament dashboard for a super_admin user', function (): void {
    Role::create(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)->get('/console')->assertSuccessful();
});

test('denies panel access to a regular user in production', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/console')->assertForbidden();
});

test('admin panel declares 内容 and 系统 navigation groups in that order', function (): void {
    $panel = filament()->getPanel('admin');

    $groupKeys = array_values(array_map(
        fn ($g) => is_string($g) ? $g : $g->getLabel(),
        $panel->getNavigationGroups(),
    ));

    expect($groupKeys)->toContain('内容')
        ->and($groupKeys)->toContain('系统')
        ->and(array_search('内容', $groupKeys, true))
        ->toBeLessThan(array_search('系统', $groupKeys, true));
});

test('roles resource appears under the 系统 navigation group', function (): void {
    $resource = RoleResource::class;

    expect(class_exists($resource))->toBeTrue()
        ->and($resource::getNavigationGroup())->toBe('系统');
});

test('content resources are grouped under 内容 with stable sort order', function (): void {
    expect(PostResource::getNavigationGroup())->toBe('内容')
        ->and(PostResource::getNavigationSort())->toBe(1)
        ->and(PageResource::getNavigationGroup())->toBe('内容')
        ->and(PageResource::getNavigationSort())->toBe(2)
        ->and(CategoryResource::getNavigationGroup())->toBe('内容')
        ->and(CategoryResource::getNavigationSort())->toBe(3)
        ->and(TagResource::getNavigationGroup())->toBe('内容')
        ->and(TagResource::getNavigationSort())->toBe(4)
        ->and(CommentResource::getNavigationGroup())->toBe('内容')
        ->and(CommentResource::getNavigationSort())->toBe(5);
});
