<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

test('the stderr channel is configured with JsonFormatter via env', function (): void {
    $envPath = base_path('.env');
    expect(file_exists($envPath))->toBeTrue();

    $contents = file_get_contents($envPath);

    expect($contents)->toContain('LOG_STDERR_FORMATTER=Monolog\\Formatter\\JsonFormatter');
});

test('the .env.example ships JsonFormatter for stderr', function (): void {
    $examplePath = base_path('.env.example');
    expect(file_exists($examplePath))->toBeTrue();

    $contents = file_get_contents($examplePath);

    expect($contents)->toContain('LOG_STDERR_FORMATTER=Monolog\\Formatter\\JsonFormatter');
});

test('a Monolog logger configured like the stderr channel emits one JSON record per line', function (): void {
    $stream = fopen('php://memory', 'w+');

    $handler = new StreamHandler($stream);
    $handler->setFormatter(new JsonFormatter);

    $logger = new Logger('stderr', [$handler]);
    $logger->info('post.published', ['post_id' => 'test-uuid', 'user_id' => 42]);

    rewind($stream);
    $output = stream_get_contents($stream);
    fclose($stream);

    expect($output)->not->toBe('');

    $lines = array_values(array_filter(explode("\n", $output), fn (string $line): bool => $line !== ''));
    expect($lines)->toHaveCount(1);

    $decoded = json_decode($lines[0], true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKeys(['message', 'context', 'level_name', 'channel', 'datetime']);
    expect($decoded['message'])->toBe('post.published');
    expect($decoded['context'])->toMatchArray([
        'post_id' => 'test-uuid',
        'user_id' => 42,
    ]);
    expect($decoded['level_name'])->toBe('INFO');
    expect($decoded['channel'])->toBe('stderr');
});

test('resolving the stderr log channel wires JsonFormatter onto its handler when configured', function (): void {
    config(['logging.channels.stderr.formatter' => JsonFormatter::class]);
    Log::forgetChannel('stderr');

    $handlers = Log::channel('stderr')->getLogger()->getHandlers();

    expect($handlers)->not->toBeEmpty();
    expect($handlers[0]->getFormatter())->toBeInstanceOf(JsonFormatter::class);

    Log::forgetChannel('stderr');
});
