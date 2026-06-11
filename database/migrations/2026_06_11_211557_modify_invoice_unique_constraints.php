<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_code_unique');
            $table->dropUnique('invoices_qr_text_unique');
            $table->unique(['invoice_code', 'product_id', 'qr_text'], 'invoice_product_qr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoice_product_qr_unique');
            $table->unique('invoice_code');
            $table->unique('qr_text');
        });
    }
};
