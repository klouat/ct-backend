<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('match_box_count')->default(0)->after('scanned_box_count');
            $table->unsignedInteger('pending_box_count')->default(0)->after('match_box_count');
            $table->unsignedInteger('less_box_count')->default(0)->after('pending_box_count');
            $table->unsignedInteger('over_box_count')->default(0)->after('less_box_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'match_box_count',
                'pending_box_count',
                'less_box_count',
                'over_box_count',
            ]);
        });
    }
};
