<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('po_number')->constrained('vendors', 'vendor_id')->nullOnDelete();
            $table->string('product_id', 100)->nullable()->after('vendor_id')->index();
            $table->string('product_name', 150)->nullable()->after('product_id');
            $table->string('qr_text', 255)->nullable()->after('product_name')->unique();
        });

        $invoices = DB::table('invoices')->select('invoice_id', 'invoice_code', 'vendor_id')->get();

        foreach ($invoices as $invoice) {
            $vendorFragment = $invoice->vendor_id ? (string) $invoice->vendor_id : 'UNKNOWN';

            DB::table('invoices')
                ->where('invoice_id', $invoice->invoice_id)
                ->update([
                    'qr_text' => sprintf(
                        'INV:%s|PRODUCT:%s|VENDOR:%s',
                        $invoice->invoice_code,
                        'UNKNOWN',
                        $vendorFragment
                    ),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropUnique(['qr_text']);
            $table->dropColumn(['vendor_id', 'product_id', 'product_name', 'qr_text']);
        });
    }
};
