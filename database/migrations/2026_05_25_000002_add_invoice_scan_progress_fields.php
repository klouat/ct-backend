<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('scanned_box_count')->default(0)->after('target_box_count');
            $table->timestamp('last_scanned_at')->nullable()->after('scanned_box_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['scanned_box_count', 'last_scanned_at']);
        });
    }
};
