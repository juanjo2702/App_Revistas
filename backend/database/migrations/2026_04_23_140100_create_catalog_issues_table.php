<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('compound_id')->unique();
            $table->foreignId('journal_id')->constrained('catalog_journals')->cascadeOnDelete();
            $table->string('journal_compound_id')->index();
            $table->string('source_slug')->index();
            $table->string('remote_id');
            $table->string('title');
            $table->string('volume')->nullable();
            $table->string('number')->nullable();
            $table->unsignedInteger('year')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['journal_id', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_issues');
    }
};
