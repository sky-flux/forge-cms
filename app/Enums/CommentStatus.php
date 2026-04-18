<?php

declare(strict_types=1);

namespace App\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Trash = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待审核',
            self::Approved => '已通过',
            self::Spam => '垃圾',
            self::Trash => '已删除',
        };
    }
}
