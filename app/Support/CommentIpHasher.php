<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class CommentIpHasher
{
    public function hash(string $ip): string
    {
        $secret = config('forge.comments.ip_hmac_secret');

        if ($secret === '' || $secret === null) {
            throw new RuntimeException('COMMENT_IP_HMAC_SECRET must be set in .env');
        }

        return hash_hmac('sha256', $ip, $secret);
    }
}
