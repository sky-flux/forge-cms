<?php

declare(strict_types=1);

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('MediaResource binds to Media model under 内容', function (): void {
    expect(MediaResource::getModel())->toBe(Media::class)
        ->and(MediaResource::getNavigationGroup())->toBe('内容')
        ->and(MediaResource::getNavigationSort())->toBe(6);
});

test('super_admin can list media', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(MediaResource::getUrl('index'))
        ->assertSuccessful();
});

test('guests are redirected from media index', function (): void {
    $this->get(MediaResource::getUrl('index'))->assertRedirect('/console/login');
});

test('super_admin can delete a media row via resource', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $post = Post::factory()->create();
    $post->addMediaFromString('fake-binary-contents')
        ->usingFileName('test.txt')
        ->usingName('test')
        ->toMediaCollection();

    $media = $post->getFirstMedia();
    expect($media)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(ListMedia::class)
        ->callTableAction('delete', $media);

    expect(Media::find($media->id))->toBeNull();
});
