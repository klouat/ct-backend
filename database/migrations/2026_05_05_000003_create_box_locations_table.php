<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_locations', function (Blueprint $table) {
            $table->id('box_location_id');
            $table->foreignId('box_id')->constrained('boxes', 'box_id')->cascadeOnDelete();
            $table->string('location_name', 150)->index();
            $table->timestamp('recorded_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_locations');
    }
};
