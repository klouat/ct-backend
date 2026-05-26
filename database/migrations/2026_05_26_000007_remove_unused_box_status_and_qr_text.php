<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropUnique(['qr_text']);
            $table->dropColumn(['status', 'qr_text']);
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->index()->after('vendor_id');
            $table->string('qr_text', 255)->nullable()->unique()->after('status');
        });
    }
};
