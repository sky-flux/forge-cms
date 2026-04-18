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
        Schema::create('pages', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('excerpt', 500)->nullable();
            $t->text('body_html');
            $t->string('seo_title')->nullable();
            $t->string('seo_description', 500)->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestampTz('published_at')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_homepage')->default(false);
            $t->boolean('is_comments_enabled')->default(true);
            $t->jsonb('meta')->default('{}');
            $t->softDeletes();
            $t->timestampsTz();
            $t->index(['status', 'published_at']);
            $t->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
