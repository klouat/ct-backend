<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('counting_results');
        Schema::dropIfExists('scan_logs');
        Schema::dropIfExists('qr_logs');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('shipment_locations');
        Schema::dropIfExists('shipments');
    }

    public function down(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id('shipment_id');
            $table->string('shipment_code', 50)->unique();
            $table->foreignId('vendor_id')->constrained('vendors', 'vendor_id')->cascadeOnDelete();
            $table->date('shipment_date')->nullable()->index();
            $table->string('status', 50)->default('PENDING')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });

        Schema::create('shipment_locations', function (Blueprint $table) {
            $table->id('location_id');
            $table->foreignId('shipment_id')->constrained('shipments', 'shipment_id')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at')->useCurrent()->index();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id('package_id');
            $table->string('package_code', 50)->unique();
            $table->foreignId('box_id')->constrained('boxes', 'box_id')->cascadeOnDelete();
            $table->string('qr_text')->nullable()->unique();
            $table->unsignedInteger('qty');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });

        Schema::create('qr_logs', function (Blueprint $table) {
            $table->id('qr_log_id');
            $table->foreignId('package_id')->constrained('packages', 'package_id')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('qr_text');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id('scan_id');
            $table->foreignId('package_id')->constrained('packages', 'package_id')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('scan_time')->useCurrent()->index();
            $table->string('status', 20)->index();
        });

        Schema::create('counting_results', function (Blueprint $table) {
            $table->id('counting_id');
            $table->foreignId('package_id')->constrained('packages', 'package_id')->cascadeOnDelete();
            $table->unsignedInteger('counted_qty');
            $table->timestamp('counted_time')->useCurrent();
        });
    }
};
