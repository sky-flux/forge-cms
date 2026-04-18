<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('attaches a media item to a user via the library', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $user->addMedia(UploadedFile::fake()->image('avatar.png', 120, 120))
        ->toMediaCollection('avatars');

    expect($user->fresh()->getMedia('avatars'))->toHaveCount(1);
});

test('exposes the filament spatie media-library upload component', function (): void {
    expect(class_exists(SpatieMediaLibraryFileUpload::class))->toBeTrue();
});
