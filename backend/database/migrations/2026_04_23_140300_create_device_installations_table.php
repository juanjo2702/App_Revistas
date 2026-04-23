<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_installations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->string('platform')->nullable()->index();
            $table->string('app_version')->nullable();
            $table->string('locale')->nullable();
            $table->string('push_token')->nullable()->unique();
            $table->string('push_provider')->nullable();
            $table->boolean('notifications_enabled')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_installations');
    }
};
