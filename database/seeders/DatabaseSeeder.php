<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $vendor = Vendor::firstOrCreate(
            ['vendor_name' => 'Default Vendor']
        );

        User::updateOrCreate([
            'username' => 'admin',
        ], [
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'ADMIN',
            'vendor_id' => null,
        ]);

        User::updateOrCreate([
            'username' => 'supervisor01',
        ], [
            'email' => 'supervisor01@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'SUPERVISOR',
            'vendor_id' => null,
        ]);

        User::updateOrCreate([
            'username' => 'petugasgudang01',
        ], [
            'email' => 'petugasgudang01@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'PETUGAS_GUDANG',
            'vendor_id' => null,
        ]);

        $invoice = Invoice::firstOrCreate([
            'po_number' => 'PO-001',
        ], [
            'invoice_code' => 'INV-PO-001',
            'status' => 'terverifikasi',
            'target_box_count' => 1,
            'estimated_arrival_date' => now()->toDateString(),
        ]);

        $box = Box::firstOrCreate([
            'invoice_id' => $invoice->invoice_id,
        ], [
            'vendor_id' => $vendor->vendor_id,
        ]);
    }
}
