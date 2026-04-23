<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('compound_id')->unique();
            $table->foreignId('journal_id')->constrained('catalog_journals')->cascadeOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('catalog_issues')->nullOnDelete();
            $table->string('journal_compound_id')->index();
            $table->string('issue_compound_id')->nullable()->index();
            $table->string('source_slug')->index();
            $table->string('remote_id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('authors')->nullable();
            $table->string('authors_string')->nullable()->index();
            $table->longText('abstract')->nullable();
            $table->json('keywords')->nullable();
            $table->string('doi')->nullable()->index();
            $table->string('pages')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('url')->nullable();
            $table->json('pdf')->nullable();
            $table->json('citations')->nullable();
            $table->string('license_url')->nullable();
            $table->json('references')->nullable();
            $table->string('section')->nullable();
            $table->text('search_blob')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['journal_id', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_articles');
    }
};
