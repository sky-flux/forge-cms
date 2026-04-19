<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_id')
                ->constrained('dictionary_types')
                ->cascadeOnDelete();
            $table->string('label', 128);
            $table->string('value', 128);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['type_id', 'value']);
            $table->index(['type_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_items');
    }
};
