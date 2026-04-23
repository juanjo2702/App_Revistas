<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_installation_id')->constrained('device_installations')->cascadeOnDelete();
            $table->string('segment_type')->index();
            $table->string('segment_value')->index();
            $table->timestamps();

            $table->unique(['device_installation_id', 'segment_type', 'segment_value'], 'device_segments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_segments');
    }
};
