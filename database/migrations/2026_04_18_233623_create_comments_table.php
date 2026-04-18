<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->morphs('commentable');
            $t->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guest_name', 100)->nullable();
            $t->string('guest_email')->nullable();
            $t->string('guest_ip_hash', 64)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->text('body');
            $t->text('body_html');
            $t->string('status', 20)->default('pending');
            $t->timestampTz('approved_at')->nullable();
            $t->timestampsTz();
            $t->index(['commentable_type', 'commentable_id', 'status']);
            $t->index('status');
            $t->index('parent_id');
            $t->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
