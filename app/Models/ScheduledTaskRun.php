<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScheduledTaskRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledTaskRun extends Model
{
    /** @use HasFactory<ScheduledTaskRunFactory> */
    use HasFactory;

    protected $fillable = [
        'command',
        'started_at',
        'finished_at',
        'exit_code',
        'output',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exit_code' => 'integer',
        ];
    }
}
