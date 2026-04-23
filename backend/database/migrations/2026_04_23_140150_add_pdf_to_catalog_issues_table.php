<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_issues', function (Blueprint $table): void {
            $table->json('pdf')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_issues', function (Blueprint $table): void {
            $table->dropColumn('pdf');
        });
    }
};
