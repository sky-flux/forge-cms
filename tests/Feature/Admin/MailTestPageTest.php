<?php

declare(strict_types=1);

use App\Filament\Pages\MailTest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('MailTest page lives under 系统', function (): void {
    expect(MailTest::getNavigationGroup())->toBe('系统')
        ->and(MailTest::getNavigationSort())->toBe(8);
});

test('super_admin sends test email', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(MailTest::class)
        ->set('recipient', 'target@example.com')
        ->call('send');

    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    $sent = $messages[0]->getOriginalMessage();
    $addresses = array_map(
        static fn ($to) => $to->getAddress(),
        $sent->getTo(),
    );

    expect($addresses)->toContain('target@example.com')
        ->and($sent->getSubject())->toBe('ForgeCMS Test Email');
});

test('guest redirected from mail test page', function (): void {
    $this->get(MailTest::getUrl())->assertRedirect('/console/login');
});
