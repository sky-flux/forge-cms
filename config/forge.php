<?php

declare(strict_types=1);

return [
    'comments' => [
        'ip_hmac_secret' => env('COMMENT_IP_HMAC_SECRET', ''),
        'require_moderation' => env('COMMENTS_REQUIRE_MODERATION', true),
        'allow_guests' => env('COMMENTS_ALLOW_GUESTS', true),
    ],
];
