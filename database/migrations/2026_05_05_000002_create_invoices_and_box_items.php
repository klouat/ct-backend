<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->string('invoice_code', 100)->unique();
            $table->string('po_number', 100)->unique();
            $table->string('status', 50)->default('terverifikasi')->index();
            $table->unsignedInteger('target_box_count')->default(0);
            $table->date('estimated_arrival_date')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('box_code')->constrained('invoices', 'invoice_id')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('invoice_id')->constrained('vendors', 'vendor_id')->nullOnDelete();
            $table->string('qr_text', 255)->nullable()->unique()->after('vendor_id');
        });

        Schema::create('box_items', function (Blueprint $table) {
            $table->id('box_item_id');
            $table->foreignId('box_id')->constrained('boxes', 'box_id')->cascadeOnDelete();
            $table->string('sku', 150)->unique();
            $table->string('item_name', 150);
            $table->unsignedInteger('quantity');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });

        $shipments = DB::table('shipments')->get();

        foreach ($shipments as $shipment) {
            $invoiceId = DB::table('invoices')->insertGetId([
                'invoice_code' => 'INV-'.$shipment->shipment_code,
                'po_number' => $shipment->shipment_code,
                'status' => 'terverifikasi',
                'target_box_count' => DB::table('boxes')
                    ->where('shipment_id', $shipment->shipment_id)
                    ->count(),
                'estimated_arrival_date' => $shipment->shipment_date,
                'created_at' => $shipment->created_at,
                'deleted_at' => $shipment->deleted_at,
            ]);

            DB::table('boxes')
                ->where('shipment_id', $shipment->shipment_id)
                ->update([
                    'invoice_id' => $invoiceId,
                    'vendor_id' => $shipment->vendor_id,
                ]);
        }

        $boxes = DB::table('boxes')->select('box_id', 'box_code')->get();

        foreach ($boxes as $box) {
            DB::table('boxes')
                ->where('box_id', $box->box_id)
                ->update([
                    'qr_text' => 'BOX:'.$box->box_code,
                ]);
        }

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('box_code')->constrained('shipments', 'shipment_id')->nullOnDelete();
        });

        $invoices = DB::table('invoices')->get()->keyBy('po_number');
        $shipments = DB::table('shipments')->get();

        foreach ($shipments as $shipment) {
            $invoice = $invoices->get($shipment->shipment_code);

            if (! $invoice) {
                continue;
            }

            DB::table('boxes')
                ->where('invoice_id', $invoice->invoice_id)
                ->update([
                    'shipment_id' => $shipment->shipment_id,
                ]);
        }

        Schema::dropIfExists('box_items');

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropUnique(['qr_text']);
            $table->dropColumn(['invoice_id', 'vendor_id', 'qr_text']);
        });

        Schema::dropIfExists('invoices');
    }
};
