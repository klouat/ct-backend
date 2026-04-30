<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Package;
use App\Models\Shipment;
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
            'password_hash' => Hash::make('password123'),
            'role' => 'ADMIN',
            'vendor_id' => null,
        ]);

        User::updateOrCreate([
            'username' => 'vendor01',
        ], [
            'password_hash' => Hash::make('password123'),
            'role' => 'VENDOR',
            'vendor_id' => $vendor->vendor_id,
        ]);

        User::updateOrCreate([
            'username' => 'operator01',
        ], [
            'password_hash' => Hash::make('password123'),
            'role' => 'OPERATOR',
            'vendor_id' => null,
        ]);

        User::updateOrCreate([
            'username' => 'driver01',
        ], [
            'password_hash' => Hash::make('password123'),
            'role' => 'DRIVER',
            'vendor_id' => $vendor->vendor_id,
        ]);

        $shipment = Shipment::firstOrCreate([
            'shipment_code' => 'SHIP-001',
        ], [
            'vendor_id' => $vendor->vendor_id,
            'shipment_date' => now()->toDateString(),
            'status' => 'PENDING',
        ]);

        $box = Box::firstOrCreate([
            'box_code' => 'BOX-001',
        ], [
            'shipment_id' => $shipment->shipment_id,
        ]);

        Package::firstOrCreate([
            'package_code' => 'PKG-001',
        ], [
            'box_id' => $box->box_id,
            'qty' => 10,
            'qr_text' => 'SHIP:SHIP-001|BOX:BOX-001|PKG:PKG-001',
        ]);
    }
}
