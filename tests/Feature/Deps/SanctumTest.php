<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('user can mint a personal access token via HasApiTokens', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('test-device');

    expect($token->accessToken)->toBeInstanceOf(PersonalAccessToken::class);
    expect($token->plainTextToken)->toBeString();
});
